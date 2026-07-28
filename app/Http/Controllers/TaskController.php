<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\LocationAccessService;
use App\Services\TaskWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);
        $user = $request->user();
        $assignmentLoader = $user->usesDriverWorkspace()
            ? fn ($assignments) => $assignments
                ->where('driver_id', $user->id)
                ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                ->latest()
            : fn ($assignments) => $assignments->where('status', 'rejected')->latest();
        $query = $this->visibleQuery($user)
            ->with([
                'sourceLocation', 'destinationLocation', 'creator', 'currentAssignment.driver',
                'assignments' => $assignmentLoader,
                'assignments.driver',
            ]);

        $this->applyFilters($query, $request);
        $this->applyUrgencyOrdering($query);

        $tasks = $query->paginate(20)->withQueryString();
        if ($user->usesDriverWorkspace()) {
            $tasks->getCollection()->each(fn (Task $task) => $this->useDriverAssignmentForPresentation($task, $user));
        }

        return view('tasks.index', [
            'tasks' => $tasks,
            'drivers' => $user->usesDriverWorkspace()
                ? collect()
                : User::assignableDrivers()->where('active', true)->orderBy('name')->get(),
            'locations' => $this->locationAccess->visibleLocations($user)->orderBy('name')->get(),
            'totalTasks' => $this->visibleQuery($user)->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        return view('tasks.form', $this->formData($request->user()));
    }

    public function store(Request $request, TaskWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('create', Task::class);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:general,transport,documente,aprovizionare,altele'],
            'source_location_id' => ['nullable', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'different:source_location_id', 'exists:locations,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'manager_deadline' => ['nullable', 'date'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->authorizeLocationScope(
            $request->user(),
            isset($data['source_location_id']) ? (int) $data['source_location_id'] : null,
            isset($data['destination_location_id']) ? (int) $data['destination_location_id'] : null,
        );

        $task = DB::transaction(function () use ($data, $request, $workflow): Task {
            $task = Task::create([
                'number' => 'TSK-'.now()->format('Ymd-His').'-'.strtoupper(str()->random(3)),
                'title' => $data['title'],
                'category' => $data['category'],
                'created_by' => $request->user()->id,
                'source_location_id' => $data['source_location_id'] ?? null,
                'destination_location_id' => $data['destination_location_id'] ?? null,
                'status' => 'unassigned',
                'priority' => $data['priority'],
                'manager_deadline' => $data['manager_deadline'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            if (! empty($data['driver_id'])) {
                $driver = User::assignableDrivers()->where('active', true)->whereKey($data['driver_id'])->firstOrFail();
                $workflow->assign($task, $driver, $request->user());
            }

            return $task;
        });

        return redirect()->route('tasks.show', $task)->with('status', 'Sarcina a fost creata.');
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);
        $task->load([
            'sourceLocation.activeManagers', 'destinationLocation.activeManagers', 'creator',
            'transfer.approvals.decidedBy', 'transfer.approvals.location',
            'assignments.driver', 'assignments.assigner', 'comments.user.roles',
        ]);
        if ($request->user()->usesDriverWorkspace()) {
            $this->useDriverAssignmentForPresentation($task, $request->user());
        }

        return view('tasks.show', [
            'task' => $task,
            'drivers' => $request->user()->usesDriverWorkspace() ? collect() : User::assignableDrivers()->where('active', true)->orderBy('name')->get(),
            'whatsAppRecipients' => $request->user()->usesDriverWorkspace() ? collect() : User::where('active', true)->whereNotNull('phone')->orderBy('name')->get(),
        ]);
    }

    public function edit(Task $task): View|RedirectResponse
    {
        $this->authorize('edit', $task);
        $task->load(['transfer', 'currentAssignment.driver']);
        if ($task->transfer) {
            return redirect()->route('transfers.edit', $task->transfer);
        }

        return view('tasks.form', $this->formData(request()->user(), $task));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('edit', $task);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:general,transport,documente,aprovizionare,altele'],
            'source_location_id' => ['nullable', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'different:source_location_id', 'exists:locations,id'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'manager_deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $this->authorizeLocationScope(
            $request->user(),
            isset($data['source_location_id']) ? (int) $data['source_location_id'] : null,
            isset($data['destination_location_id']) ? (int) $data['destination_location_id'] : null,
        );
        $deadlineChanged = (string) $task->manager_deadline !== (string) ($data['manager_deadline'] ?? null);
        $task->update($data);

        if ($deadlineChanged && $task->currentAssignment?->driver) {
            $task->currentAssignment->driver->notify(new WorkflowNotification(
                'Deadline modificat',
                'Deadline-ul original pentru '.$task->number.' a fost modificat.',
                route('tasks.show', $task),
            ));
        }

        return redirect()->route('tasks.show', $task)->with('status', 'Sarcina a fost actualizata.');
    }

    public function transition(Request $request, Task $task, TaskWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:in_progress,completed,cancelled,archived'],
            'notes' => ['nullable', 'string'],
        ]);
        if ($request->user()->usesDriverWorkspace()) {
            $this->authorize('respond', $task);
            abort_unless(in_array($data['status'], ['in_progress', 'completed'], true), 403);
            abort_unless($this->driverHasOperationalAssignment($task, $request->user()), 403);
        } else {
            $this->authorize('transition', $task);
        }
        $workflow->transition($task, $request->user(), $data['status'], $data['notes'] ?? null);

        return back()->with('status', 'Starea sarcinii a fost actualizata.');
    }

    public function dispatch(Request $request): View
    {
        $this->authorize('create', Task::class);
        $drivers = User::assignableDrivers()->where('active', true)
            ->with(['taskAssignments' => fn ($query) => $query
                ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                ->whereHas('task', fn ($task) => $task->whereNotIn('status', ['completed', 'cancelled', 'archived']))
                ->with(['task.sourceLocation', 'task.destinationLocation'])
                ->latest()])
            ->orderBy('name')
            ->get();

        $driverSummaries = $drivers
            ->map(function (User $driver): array {
                $assignments = $driver->taskAssignments
                    ->filter(fn ($assignment) => $assignment->task)
                    ->values();
                $working = $assignments
                    ->whereIn('status', ['accepted', 'reassignment_requested'])
                    ->sortBy(fn ($assignment) => implode('|', [
                        $assignment->task->status === 'in_progress' ? '0' : '1',
                        optional($assignment->driver_estimate_at ?? $assignment->task->manager_deadline)->format('YmdHi') ?? '999999999999',
                    ]))
                    ->values();
                $pending = $assignments
                    ->where('status', 'pending')
                    ->sortBy(fn ($assignment) => optional($assignment->task->manager_deadline)->format('YmdHi') ?? '999999999999')
                    ->values();
                $current = $working->first();
                $focus = $current ?? $pending->first();
                $availabilityAt = $current?->driver_estimate_at ?? $current?->task?->manager_deadline;

                [$state, $stateLabel, $rank] = match (true) {
                    ! $focus => ['free', 'Liber acum', 0],
                    ! $current && $pending->isNotEmpty() => ['pending', 'Asteapta raspuns', 2],
                    $current?->task?->isOverdue() => ['overdue', 'Sarcina intarziata', 4],
                    $current && $availabilityAt && $availabilityAt->isFuture() && $availabilityAt->lte(now()->addHours(4)) => ['soon', 'Liber in curand', 1],
                    default => ['busy', 'Ocupat', 3],
                };

                return [
                    'driver' => $driver,
                    'currentAssignment' => $focus,
                    'currentTask' => $focus?->task,
                    'availabilityAt' => $availabilityAt,
                    'state' => $state,
                    'stateLabel' => $stateLabel,
                    'sortRank' => $rank,
                    'queueCount' => max(0, $assignments->count() - ($focus ? 1 : 0)),
                    'activeCount' => $assignments->count(),
                ];
            })
            ->sortBy(fn (array $summary) => sprintf(
                '%d|%s|%s',
                $summary['sortRank'],
                optional($summary['availabilityAt'])->format('YmdHi') ?? '999999999999',
                $summary['driver']->name,
            ))
            ->values();

        $unassignedQuery = $this->visibleQuery($request->user())
            ->where('status', 'unassigned')
            ->when($request->search, fn ($query, $search) => $query->where(function ($filtered) use ($search): void {
                $filtered->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%");
            }))
            ->when($request->boolean('overdue'), fn ($query) => $query->whereNotNull('manager_deadline')->where('manager_deadline', '<', now()))
            ->with(['sourceLocation', 'destinationLocation'])
            ->orderByRaw('CASE WHEN manager_deadline IS NOT NULL AND manager_deadline < ? THEN 0 WHEN manager_deadline IS NOT NULL THEN 1 ELSE 2 END', [now()])
            ->orderBy('manager_deadline')
            ->latest('id');

        return view('tasks.dispatch', [
            'driverSummaries' => $driverSummaries,
            'unassignedTasks' => $unassignedQuery->paginate(25, ['*'], 'unassigned_page')->withQueryString(),
            'unassignedTotal' => $this->visibleQuery($request->user())->where('status', 'unassigned')->count(),
        ]);
    }

    private function visibleQuery(User $user): Builder
    {
        $query = Task::query();
        if ($user->hasGlobalOperationalReadAccess()) {
            return $query;
        }
        if ($user->usesDriverWorkspace()) {
            return $query->where(function ($visible) use ($user): void {
                $visible->whereHas('currentAssignment', fn ($assignment) => $assignment->where('driver_id', $user->id))
                    ->orWhereHas('assignments', fn ($assignment) => $assignment
                        ->where('driver_id', $user->id)
                        ->whereIn('status', ['accepted', 'reassignment_requested'])
                        ->whereHas('replacementCandidates', fn ($candidate) => $candidate->where('status', 'pending')));
            });
        }
        $locationIds = $user->activeManagedLocations()->pluck('locations.id');

        return $query->where(function ($visible) use ($user, $locationIds): void {
            $visible->where('created_by', $user->id)
                ->orWhereIn('source_location_id', $locationIds)
                ->orWhereIn('destination_location_id', $locationIds);
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->search, fn ($q, $search) => $q->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%");
            }))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->priority, fn ($q, $priority) => $q->where('priority', $priority))
            ->when($request->driver_id, fn ($q, $driverId) => $q->whereHas('currentAssignment', fn ($a) => $a->where('driver_id', $driverId)))
            ->when($request->location_id, fn ($q, $locationId) => $q->where(fn ($locations) => $locations
                ->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId)))
            ->when($request->boolean('overdue'), fn ($q) => $q->whereNotNull('manager_deadline')
                ->where('manager_deadline', '<', now())->whereNotIn('status', ['completed', 'cancelled', 'archived']))
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'));
    }

    private function applyUrgencyOrdering(Builder $query): void
    {
        $query
            ->orderByRaw("CASE
                WHEN tasks.status IN ('completed', 'cancelled', 'archived') THEN 4
                WHEN tasks.status IN ('unassigned', 'pending_acceptance') THEN 0
                WHEN tasks.manager_deadline IS NOT NULL AND tasks.manager_deadline < ? THEN 1
                WHEN tasks.manager_deadline IS NOT NULL THEN 2
                ELSE 3
            END", [now()])
            ->orderByRaw('CASE WHEN tasks.manager_deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tasks.manager_deadline')
            ->latest('tasks.id');
    }

    private function formData(User $user, ?Task $task = null): array
    {
        return [
            'task' => $task,
            'drivers' => User::assignableDrivers()->where('active', true)->orderBy('name')->get(),
            'locations' => $this->locationAccess->visibleLocations($user)->orderBy('name')->get(),
        ];
    }

    private function authorizeLocationScope(User $actor, ?int $sourceLocationId, ?int $destinationLocationId): void
    {
        if ($actor->isOperationsAdmin()) {
            return;
        }

        $locationIds = collect([$sourceLocationId, $destinationLocationId])->filter()->unique();
        if ($locationIds->isEmpty()) {
            return;
        }

        abort_unless(
            $actor->hasAnyRole(['sef-santier', 'gestionar-baza'])
                && $actor->activeManagedLocations()->whereIn('locations.id', $locationIds)->count() === $locationIds->count(),
            403
        );
    }

    private function driverHasOperationalAssignment(Task $task, User $user): bool
    {
        $current = $task->currentAssignment;
        if ($current?->driver_id === $user->id
            && in_array($current->status, ['accepted', 'reassignment_requested'], true)) {
            return true;
        }

        return $task->assignments()
            ->where('driver_id', $user->id)
            ->whereIn('status', ['accepted', 'reassignment_requested'])
            ->whereHas('replacementCandidates', fn ($candidate) => $candidate->where('status', 'pending'))
            ->exists();
    }

    private function useDriverAssignmentForPresentation(Task $task, User $user): void
    {
        $assignment = $task->relationLoaded('assignments')
            ? $task->assignments
                ->where('driver_id', $user->id)
                ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                ->sortByDesc('id')
                ->first()
            : $task->assignments()
                ->with('driver')
                ->where('driver_id', $user->id)
                ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                ->latest('id')
                ->first();

        if ($assignment) {
            $task->setRelation('currentAssignment', $assignment);
        }
    }
}
