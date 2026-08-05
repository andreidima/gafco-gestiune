<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferLine;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\OperationalAlertSyncService;
use App\Services\ProjectAccessService;
use App\Services\ProjectMaterialPlanService;
use App\Services\TaskWorkflowService;
use App\Services\TransferWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function __construct(
        private readonly LocationAccessService $locationAccess,
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectMaterialPlanService $projectPlans,
        private readonly OperationalAlertSyncService $alerts,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Transfer::class);
        $user = $request->user();
        $canApproveGlobally = $user->hasGlobalAbility('transfers.approve');
        $managedActionLocationIds = $canApproveGlobally || $user->usesDriverWorkspace()
            ? collect()
            : $user->activeManagedLocations()->pluck('locations.id');
        $query = $this->visibleQuery($request->user())
            ->with([
                'sourceLocation', 'destinationLocation', 'task.currentAssignment.driver', 'task.currentAssignment.replacedAssignment.driver',
                'project',
                'lines.catalogItem', 'lines.trackedAsset',
                'approvals.decidedBy', 'approvals.expectedUser', 'approvals.location.activeManagers',
            ])
            ->withExists([
                'approvals as requires_my_action' => fn ($approval) => $approval
                    ->whereColumn('transfer_approvals.revision', 'transfers.revision')
                    ->where('transfer_approvals.status', 'pending')
                    ->whereNotIn('transfers.status', ['received', 'cancelled'])
                    ->whereNull('transfers.archived_at')
                    ->when($canApproveGlobally, fn ($eligible) => $eligible->where('scope', '!=', 'driver'))
                    ->when(! $canApproveGlobally, fn ($eligible) => $eligible->where(function ($actions) use ($user, $managedActionLocationIds): void {
                        $actions->where('expected_user_id', $user->id);
                        if (! $user->usesDriverWorkspace()) {
                            $actions->orWhereIn('location_id', $managedActionLocationIds);
                        }
                    })),
                'approvals as has_pending_current_approval' => fn ($approval) => $approval
                    ->whereColumn('transfer_approvals.revision', 'transfers.revision')
                    ->where('transfer_approvals.status', 'pending'),
                'task as has_overdue_task' => fn ($task) => $task
                    ->whereNotNull('manager_deadline')
                    ->where('manager_deadline', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled', 'archived']),
                'task as has_unassigned_task' => fn ($task) => $task
                    ->whereIn('status', ['unassigned', 'pending_acceptance']),
            ])
            ->withMax('task as task_manager_deadline', 'manager_deadline')
            ->withCount('lines');
        $this->applyFilters($query, $request);
        $this->applyUrgencyOrdering($query);
        $transfers = $query->paginate(20)->withQueryString();
        $projectsOnPage = $transfers->getCollection()
            ->pluck('project')
            ->filter()
            ->unique('id')
            ->values();

        return view('transfers.index', [
            'transfers' => $transfers,
            'locations' => $this->locationAccess->visibleLocations($user, 'transfers.view')->orderBy('name')->get(),
            'drivers' => $request->user()->usesDriverWorkspace()
                ? collect()
                : User::assignableDrivers()->where('active', true)->orderBy('name')->get(),
            'projects' => $this->projectAccess->canUse($user)
                ? $this->projectAccess->visibleProjects($user)->orderBy('code')->get()
                : collect(),
            'projectProgressById' => $this->projectPlans->progressForProjects($projectsOnPage),
            'totalTransfers' => $this->visibleQuery($request->user())->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Transfer::class);
        $parent = $request->filled('return_of')
            ? Transfer::with(['lines.catalogItem', 'lines.trackedAsset'])->findOrFail($request->integer('return_of'))
            : null;
        if ($parent) {
            $this->authorize('view', $parent);
            abort_unless(
                $parent->purpose === 'transfer' && $parent->status === 'received',
                422,
                'Returul poate fi initiat numai dintr-un transfer initial receptionat.'
            );
        }

        return view('transfers.form', $this->formData($request->user(), null, $parent));
    }

    public function sourceOptions(Request $request): JsonResponse
    {
        $this->authorize('create', Transfer::class);
        $data = $request->validate([
            'source_location_id' => ['required', 'integer'],
            'transfer_id' => ['nullable', 'integer', 'exists:transfers,id'],
        ]);

        $transfer = ! empty($data['transfer_id'])
            ? Transfer::findOrFail($data['transfer_id'])
            : null;
        if ($transfer) {
            $this->authorize('update', $transfer);
        }

        $location = $this->locationAccess->visibleLocations($request->user(), 'transfers.create')
            ->findOrFail($data['source_location_id']);
        $ignoreTransferId = $transfer?->id;

        $reservedMaterials = TransferLine::query()
            ->selectRaw('catalog_item_id, SUM(quantity) as reserved_quantity')
            ->whereNotNull('catalog_item_id')
            ->whereNull('tracked_asset_id')
            ->when($ignoreTransferId, fn ($query) => $query->where('transfer_id', '!=', $ignoreTransferId))
            ->whereHas('transfer', fn ($query) => $query
                ->whereNull('archived_at')
                ->whereNotIn('status', ['received', 'cancelled']))
            ->groupBy('catalog_item_id')
            ->pluck('reserved_quantity', 'catalog_item_id');

        $materials = StockLevel::query()
            ->where('location_id', $location->id)
            ->where('quantity', '>', 0)
            ->whereHas('catalogItem', fn ($query) => $query
                ->where('active', true)
                ->where('tracking_type', 'quantity'))
            ->with('catalogItem')
            ->get()
            ->map(function (StockLevel $stock) use ($reservedMaterials): array {
                $available = max(
                    0,
                    round((float) $stock->quantity - (float) ($reservedMaterials[$stock->catalog_item_id] ?? 0), 3),
                );

                return [
                    'id' => $stock->catalog_item_id,
                    'name' => $stock->catalogItem->name,
                    'sku' => $stock->catalogItem->sku,
                    'barcode' => $stock->catalogItem->barcode,
                    'unit' => $stock->catalogItem->unit,
                    'available' => number_format($available, 3, '.', ''),
                ];
            })
            ->filter(fn (array $material) => (float) $material['available'] > 0.0005)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $reservedAssetIds = TransferLine::query()
            ->whereNotNull('tracked_asset_id')
            ->when($ignoreTransferId, fn ($query) => $query->where('transfer_id', '!=', $ignoreTransferId))
            ->whereHas('transfer', fn ($query) => $query
                ->whereNull('archived_at')
                ->whereNotIn('status', ['received', 'cancelled']))
            ->pluck('tracked_asset_id');

        $assets = TrackedAsset::query()
            ->with('catalogItem')
            ->where('current_location_id', $location->id)
            ->whereIn('status', ['available', 'in_use'])
            ->whereNotIn('id', $reservedAssetIds)
            ->whereHas('catalogItem', fn ($query) => $query->where('active', true))
            ->orderBy('asset_code')
            ->get()
            ->map(fn (TrackedAsset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'qr_code' => $asset->qr_code,
                'serial_number' => $asset->serial_number,
                'name' => $asset->catalogItem?->name,
                'status' => $asset->status,
            ])
            ->values();

        return response()->json([
            'location' => ['id' => $location->id, 'code' => $location->code, 'name' => $location->name],
            'materials' => $materials,
            'assets' => $assets,
        ]);
    }

    public function store(Request $request, TransferWorkflowService $workflow, TaskWorkflowService $tasks): RedirectResponse
    {
        $this->authorize('create', Transfer::class);
        $data = $this->validatedData($request);
        $this->authorizeReturnParent($request, $data);
        $this->authorizeProjectAssociation($request, $data);
        $transfer = $workflow->create($data, $request->user(), $tasks);
        $this->alerts->sync(force: true);

        return redirect()->route('transfers.show', $transfer)->with('status', 'Transferul a fost creat si aprobarile au fost solicitate.');
    }

    public function show(Transfer $transfer): View
    {
        $this->authorize('view', $transfer);
        $transfer->load([
            'sourceLocation.activeManagers', 'destinationLocation.activeManagers', 'requester', 'driver',
            'project.location', 'project.creator', 'project.materialPlans.catalogItem',
            'lines.catalogItem', 'lines.trackedAsset', 'approvals.location.activeManagers', 'approvals.expectedUser',
            'approvals.decidedBy', 'revisions.changedBy', 'parentTransfer', 'returns',
            'task.assignments.driver', 'task.currentAssignment.driver', 'task.currentAssignment.replacedAssignment.driver', 'task.comments.user',
        ]);

        return view('transfers.show', [
            'transfer' => $transfer,
            'projectProgress' => $transfer->project && ! request()->user()->usesDriverWorkspace()
                ? $this->projectPlans->progress($transfer->project)
                : collect(),
        ]);
    }

    public function edit(Transfer $transfer): View
    {
        $this->authorize('update', $transfer);
        $transfer->load(['lines.catalogItem', 'lines.trackedAsset', 'task.currentAssignment']);

        return view('transfers.form', $this->formData(request()->user(), $transfer));
    }

    public function update(Request $request, Transfer $transfer, TransferWorkflowService $workflow, TaskWorkflowService $tasks): RedirectResponse
    {
        $this->authorize('update', $transfer);
        $data = $this->validatedData($request);
        $this->authorizeReturnParent($request, $data, $transfer);
        $this->authorizeProjectAssociation($request, $data, $transfer);
        $workflow->revise($transfer, $data, $request->user(), $tasks);
        $this->alerts->sync(force: true);

        return redirect()->route('transfers.show', $transfer)->with('status', 'Transferul a fost actualizat.');
    }

    public function receive(Request $request, Transfer $transfer, TransferWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('receive', $transfer);
        $data = $request->validate(['discrepancy_notes' => ['nullable', 'string']]);
        $workflow->receive($transfer, $request->user(), $data['discrepancy_notes'] ?? null);

        return back()->with('status', 'Primirea a fost confirmata si stocurile au fost actualizate.');
    }

    public function cancel(Request $request, Transfer $transfer, TransferWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('cancel', $transfer);
        $data = $request->validate(['notes' => ['required', 'string']]);
        $workflow->cancel($transfer, $request->user(), $data['notes']);
        $this->alerts->sync(force: true);

        return back()->with('status', 'Transferul a fost anulat si ramane in istoric.');
    }

    public function archive(Request $request, Transfer $transfer, TransferWorkflowService $workflow): RedirectResponse
    {
        $this->authorize('archive', $transfer);
        $workflow->archive($transfer, $request->user());

        return redirect()->route('transfers.index')->with('status', 'Transferul a fost arhivat.');
    }

    private function formData(User $user, ?Transfer $transfer = null, ?Transfer $parent = null): array
    {
        $projects = $this->projectAccess->visibleProjects($user)
            ->where(function (Builder $query) use ($transfer): void {
                $query->where('status', 'active');
                if ($transfer?->project_id) {
                    $query->orWhereKey($transfer->project_id);
                }
            })
            ->with(['location', 'materialPlans.catalogItem'])
            ->orderBy('code')
            ->get();
        $progress = $this->projectPlans->progressForProjects($projects, $transfer?->id);

        return [
            'transfer' => $transfer,
            'parent' => $parent,
            'locations' => $this->locationAccess->visibleLocations($user, 'transfers.create')->orderBy('type')->orderBy('name')->get(),
            'drivers' => User::assignableDrivers()->where('active', true)->orderBy('name')->get(),
            'projects' => $projects,
            'projectPlanData' => $projects->mapWithKeys(fn (Project $project) => [
                $project->id => [
                    'location_id' => $project->location_id,
                    'code' => $project->code,
                    'name' => $project->name,
                    'lines' => $progress->get($project->id, collect())->mapWithKeys(fn (array $line) => [
                        $line['catalog_item']->id => [
                            'planned' => $line['planned_quantity'],
                            'committed' => $line['committed_quantity'],
                            'unit' => $line['unit'],
                        ],
                    ]),
                ],
            ]),
            'sourceOptionsUrl' => route('transfers.source-options'),
        ];
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:transfer,return'],
            'parent_transfer_id' => ['nullable', 'required_if:purpose,return', 'exists:transfers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'destination_location_id' => ['required', 'different:source_location_id', 'exists:locations,id'],
            'driver_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('active', true)),
            ],
            'manager_deadline' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.catalog_item_id' => ['nullable', 'required_without:lines.*.tracked_asset_id', 'exists:catalog_items,id'],
            'lines.*.tracked_asset_id' => ['nullable', 'required_without:lines.*.catalog_item_id', 'exists:tracked_assets,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ], [
            'destination_location_id.different' => 'Locația de destinație trebuie să fie diferită de locația sursă.',
        ]);

        if ($data['purpose'] !== 'return') {
            $data['parent_transfer_id'] = null;
        } else {
            $data['project_id'] = null;
        }

        return $data;
    }

    private function authorizeReturnParent(Request $request, array $data, ?Transfer $transfer = null): void
    {
        if (($data['purpose'] ?? null) !== 'return') {
            return;
        }

        $parent = Transfer::findOrFail($data['parent_transfer_id']);
        $this->authorize('view', $parent);
        abort_unless(
            $parent->purpose === 'transfer'
                && $parent->status === 'received'
                && (! $transfer || ! $parent->is($transfer)),
            422,
            'Returul poate fi legat numai de un transfer initial receptionat.'
        );
    }

    private function authorizeProjectAssociation(Request $request, array $data, ?Transfer $transfer = null): void
    {
        if (empty($data['project_id'])) {
            return;
        }

        abort_unless(($data['purpose'] ?? null) === 'transfer', 422);
        $project = Project::findOrFail($data['project_id']);
        abort_unless($this->projectAccess->canView($request->user(), $project), 403);
        abort_unless((int) $project->location_id === (int) $data['destination_location_id'], 422);

        $keepsCurrentProject = $transfer
            && (int) $transfer->project_id === (int) $project->id;
        abort_unless($project->status === 'active' || $keepsCurrentProject, 422);
    }

    private function visibleQuery(User $user): Builder
    {
        $query = Transfer::query();
        $scope = $user->abilityScope('transfers.view');
        if ($scope === 'global') {
            return $query;
        }
        if ($scope === 'assigned_records') {
            return $query->where(function ($visible) use ($user): void {
                $visible->where('driver_id', $user->id)
                    ->orWhereHas('task.currentAssignment', fn ($assignment) => $assignment->where('driver_id', $user->id))
                    ->orWhereHas('task.assignments', fn ($assignment) => $assignment
                        ->where('driver_id', $user->id)
                        ->where('status', 'reassignment_requested'));
            });
        }
        if (! in_array($scope, ['assigned_locations', 'visible_records'], true)) {
            return $query->whereRaw('1 = 0');
        }

        $locationIds = $user->activeManagedLocations()->pluck('locations.id');

        return $query->where(function ($visible) use ($user, $locationIds): void {
            $visible->where('requested_by', $user->id)
                ->orWhereIn('source_location_id', $locationIds)
                ->orWhereIn('destination_location_id', $locationIds)
                ->orWhere('driver_id', $user->id)
                ->orWhereHas('task.currentAssignment', fn ($assignment) => $assignment->where('driver_id', $user->id));
        });
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->search, fn ($q, $search) => $q->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('number', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%");
            }))
            ->when($request->purpose, fn ($q, $purpose) => $q->where('purpose', $purpose))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->source_location_id, fn ($q, $id) => $q->where('source_location_id', $id))
            ->when($request->destination_location_id, fn ($q, $id) => $q->where('destination_location_id', $id))
            ->when($request->project_id, fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->driver_id, fn ($q, $id) => $q->whereHas('task.currentAssignment', fn ($assignment) => $assignment->where('driver_id', $id)))
            ->when($request->approval_status, function ($q, $status): void {
                if ($status === 'approved') {
                    $q->whereHas('approvals', fn ($approval) => $approval
                        ->whereColumn('transfer_approvals.revision', 'transfers.revision'))
                        ->whereDoesntHave('approvals', fn ($approval) => $approval
                            ->whereColumn('transfer_approvals.revision', 'transfers.revision')
                            ->where('transfer_approvals.status', '!=', 'approved'));

                    return;
                }

                $q->whereHas('approvals', fn ($approval) => $approval
                    ->whereColumn('transfer_approvals.revision', 'transfers.revision')
                    ->where('transfer_approvals.status', $status));
            })
            ->when($request->boolean('overdue'), fn ($q) => $q->whereHas('task', fn ($task) => $task
                ->whereNotNull('manager_deadline')->where('manager_deadline', '<', now())->whereNotIn('status', ['completed', 'cancelled', 'archived'])))
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'));
    }

    private function applyUrgencyOrdering(Builder $query): void
    {
        $query
            ->orderByRaw("CASE WHEN transfers.status IN ('received', 'cancelled') THEN 1 ELSE 0 END")
            ->orderByDesc('requires_my_action')
            ->orderByDesc('has_pending_current_approval')
            ->orderByDesc('has_overdue_task')
            ->orderByDesc('has_unassigned_task')
            ->orderByRaw('CASE WHEN task_manager_deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('task_manager_deadline')
            ->latest('transfers.id');
    }
}
