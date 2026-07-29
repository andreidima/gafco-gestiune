<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskAssignmentEstimate;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\TransferLine;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TaskWorkflowService
{
    public function assign(Task $task, User $driver, User $actor): TaskAssignment
    {
        $this->ensureAssignableDriver($driver);

        return DB::transaction(function () use ($task, $driver, $actor): TaskAssignment {
            $task = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            if (in_array($task->status, ['completed', 'cancelled', 'archived'], true)) {
                throw ValidationException::withMessages(['driver_id' => 'O sarcina finalizata nu mai poate fi realocata.']);
            }

            $task->load(['currentAssignment.replacedAssignment', 'transfer']);
            $current = $task->currentAssignment;

            if ($current?->driver_id === $driver->id && in_array($current->status, ['pending', 'accepted', 'reassignment_requested'], true)) {
                return $current;
            }

            $incumbent = $this->incumbentAssignment($task);
            if ($current?->status === 'pending') {
                $current->update(['status' => 'replaced', 'replaced_at' => now()]);
            }

            if ($incumbent?->driver_id === $driver->id) {
                $incumbent->update([
                    'status' => 'accepted',
                    'reassignment_requested_at' => null,
                ]);
                if ($task->status !== 'in_progress') {
                    $task->update(['status' => 'accepted']);
                }
                if ($task->transfer) {
                    $task->transfer->update(['driver_id' => $driver->id, 'assigned_at' => $task->transfer->assigned_at ?? now()]);
                    $this->resetDriverApproval($task, $driver, approved: true);
                }

                return $incumbent;
            }

            $assignment = $task->assignments()->create([
                'driver_id' => $driver->id,
                'assigned_by' => $actor->id,
                'replaced_assignment_id' => $incumbent?->id,
                'status' => 'pending',
            ]);
            if (! $incumbent) {
                $task->update(['status' => 'pending_acceptance']);
            } elseif (! in_array($task->status, ['accepted', 'in_progress'], true)) {
                $task->update(['status' => 'accepted']);
            }

            if ($task->transfer) {
                if (! $incumbent) {
                    $task->transfer->update(['driver_id' => $driver->id, 'assigned_at' => now()]);
                }
                $this->resetDriverApproval($task, $driver);
            }

            $driver->notify(new WorkflowNotification(
                'Sarcina noua',
                $task->number.' - '.$task->title.' asteapta raspunsul tau.',
                route('tasks.show', $task),
            ));

            return $assignment;
        });
    }

    public function respond(TaskAssignment $assignment, User $driver, string $decision, ?string $notes): void
    {
        DB::transaction(function () use ($assignment, $driver, $decision, $notes): void {
            $task = Task::query()->lockForUpdate()->findOrFail($assignment->task_id);
            $assignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            abort_unless($assignment->driver_id === $driver->id, 403);
            abort_unless($assignment->task_id === $task->id, 403);
            if ($assignment->status !== 'pending' || ! in_array($decision, ['accepted', 'rejected'], true)) {
                throw ValidationException::withMessages(['decision' => 'Solicitarea nu mai asteapta un raspuns.']);
            }

            $task->load(['currentAssignment', 'transfer']);
            if ($task->currentAssignment?->id !== $assignment->id
                || in_array($task->status, ['completed', 'cancelled', 'archived'], true)) {
                throw ValidationException::withMessages(['decision' => 'Solicitarea nu mai este alocarea curenta.']);
            }
            $previous = $assignment->replaced_assignment_id
                ? TaskAssignment::query()->lockForUpdate()->find($assignment->replaced_assignment_id)
                : null;

            if ($decision === 'accepted') {
                if ($previous) {
                    $previous->update([
                        'status' => 'replaced',
                        'replaced_at' => now(),
                    ]);
                }
                $assignment->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'rejected_at' => null,
                    'response_notes' => $notes,
                ]);
                $task->update(['status' => $previous && $task->status === 'in_progress' ? 'in_progress' : 'accepted']);

                if ($task->transfer) {
                    $task->transfer->update(['driver_id' => $driver->id, 'assigned_at' => now()]);
                    $this->resetDriverApproval($task, $driver, approved: true);
                }
            } else {
                $assignment->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'response_notes' => $notes,
                ]);

                if ($previous) {
                    $previous?->update([
                        'status' => 'accepted',
                        'reassignment_requested_at' => null,
                    ]);
                    $task->update(['status' => $task->status === 'in_progress' ? 'in_progress' : 'accepted']);
                    if ($task->transfer && $previous?->driver) {
                        $task->transfer->update(['driver_id' => $previous->driver_id]);
                        $this->resetDriverApproval($task, $previous->driver, approved: true);
                    }
                } else {
                    $task->update(['status' => 'unassigned']);
                    if ($task->transfer) {
                        $task->transfer->update(['driver_id' => null]);
                        $this->resetDriverApproval($task, null);
                    }
                }
            }

            $task->comments()->create([
                'user_id' => $driver->id,
                'type' => $decision === 'accepted' ? 'acceptance' : 'rejection',
                'body' => $notes ?: ($decision === 'accepted' ? 'Sarcina a fost acceptata.' : 'Sarcina a fost refuzata.'),
            ]);
            $this->notifyManagers($task, $driver, 'Raspuns sarcina', $driver->name.' a '.($decision === 'accepted' ? 'acceptat' : 'refuzat').' '.$task->number.'.');
        });
    }

    public function unassign(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor): void {
            $task = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            if (in_array($task->status, ['in_progress', 'completed', 'cancelled', 'archived'], true)) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Soferul nu poate fi eliminat dupa inceperea sarcinii.',
                ]);
            }

            $task->load('transfer');
            $task->assignments()
                ->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
                ->update(['status' => 'replaced', 'replaced_at' => now()]);
            $task->update(['status' => 'unassigned']);

            if ($task->transfer) {
                $task->transfer->update(['driver_id' => null, 'assigned_at' => null]);
                $this->resetDriverApproval($task, null);
                activity()->performedOn($task->transfer)->causedBy($actor)->log('Sofer eliminat din transfer');
            }
        });
    }

    public function updateEstimate(TaskAssignment $assignment, User $driver, mixed $estimate, ?string $note): TaskAssignmentEstimate
    {
        return DB::transaction(function () use ($assignment, $driver, $estimate, $note): TaskAssignmentEstimate {
            $task = Task::query()->lockForUpdate()->findOrFail($assignment->task_id);
            $assignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $task->load('currentAssignment.replacedAssignment');
            abort_unless($assignment->driver_id === $driver->id, 403);
            if (! in_array($task->status, ['accepted', 'in_progress'], true)
                || ! in_array($assignment->status, ['accepted', 'reassignment_requested'], true)
                || $this->incumbentAssignment($task)?->id !== $assignment->id) {
                throw ValidationException::withMessages([
                    'driver_estimate_at' => 'Estimarea nu mai poate fi modificata pentru aceasta sarcina.',
                ]);
            }
            $latestEstimate = TaskAssignmentEstimate::query()
                ->where('task_assignment_id', $assignment->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();
            $isCorrection = $latestEstimate?->canBeCorrected() ?? false;

            if ($isCorrection) {
                $latestEstimate->update([
                    'estimated_at' => $estimate,
                    'note' => $note,
                ]);
                $savedEstimate = $latestEstimate->refresh();
            } else {
                $savedEstimate = $assignment->estimates()->create([
                    'driver_id' => $driver->id,
                    'estimated_at' => $estimate,
                    'note' => $note,
                    'correctable_until' => now()->addMinutes(5),
                ]);
            }

            $assignment->update([
                'driver_estimate_at' => $estimate,
                'driver_estimate_note' => $note,
            ]);
            $estimateLabel = $savedEstimate->estimated_at->format('d.m.Y H:i');
            $commentBody = ($isCorrection ? 'Estimare corectata: ' : 'Estimare comunicata: ').$estimateLabel.'.';
            if ($note) {
                $commentBody .= ' '.$note;
            }
            $task->comments()->create([
                'user_id' => $driver->id,
                'type' => 'estimate',
                'body' => $commentBody,
            ]);
            $this->notifyManagers(
                $task,
                $driver,
                $isCorrection ? 'Estimare sofer corectata' : 'Estimare sofer noua',
                $driver->name.($isCorrection ? ' a corectat estimarea pentru ' : ' a comunicat o estimare pentru ').$task->number.'.'
            );

            return $savedEstimate;
        });
    }

    public function requestReassignment(TaskAssignment $assignment, User $driver, string $note): void
    {
        DB::transaction(function () use ($assignment, $driver, $note): void {
            $task = Task::query()->lockForUpdate()->findOrFail($assignment->task_id);
            $assignment = TaskAssignment::query()->lockForUpdate()->findOrFail($assignment->getKey());
            $task->load('currentAssignment.replacedAssignment');
            abort_unless($assignment->driver_id === $driver->id, 403);
            if (! in_array($task->status, ['accepted', 'in_progress'], true)
                || $assignment->status !== 'accepted'
                || $this->incumbentAssignment($task)?->id !== $assignment->id) {
                throw ValidationException::withMessages([
                    'notes' => 'Realocarea nu mai poate fi solicitata pentru aceasta sarcina.',
                ]);
            }
            $assignment->update([
                'status' => 'reassignment_requested',
                'reassignment_requested_at' => now(),
                'response_notes' => $note,
            ]);
            $task->comments()->create([
                'user_id' => $driver->id,
                'type' => 'reassignment',
                'body' => $note,
            ]);
            $this->notifyManagers(
                $task,
                $driver,
                'Realocare solicitata',
                $driver->name.' solicita realocarea sarcinii '.$task->number.'.'
            );
        });
    }

    public function transition(Task $task, User $actor, string $status, ?string $note = null): void
    {
        DB::transaction(function () use ($task, $actor, $status, $note): void {
            $task = Task::query()->lockForUpdate()->findOrFail($task->getKey());
            $task->load(['currentAssignment.replacedAssignment.driver', 'currentAssignment.driver', 'transfer']);
            if ($task->transfer && in_array($status, ['cancelled', 'archived'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Anularea si arhivarea se fac din fluxul transferului.',
                ]);
            }

            $allowedTransitions = [
                'unassigned' => ['cancelled'],
                'pending_acceptance' => ['cancelled'],
                'accepted' => ['in_progress', 'cancelled'],
                'in_progress' => ['completed', 'cancelled'],
                'completed' => ['archived'],
                'cancelled' => ['archived'],
                'archived' => [],
            ];
            if (! in_array($status, $allowedTransitions[$task->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Tranzitia solicitata nu este permisa din starea curenta.',
                ]);
            }

            $responsibleAssignment = $this->incumbentAssignment($task);
            if ($actor->usesDriverWorkspace() && $responsibleAssignment?->driver_id !== $actor->id) {
                abort(403);
            }

            $updates = ['status' => $status];
            $updates += match ($status) {
                'in_progress' => ['started_at' => $task->started_at ?? now()],
                'completed' => ['completed_at' => $task->completed_at ?? now()],
                'cancelled' => ['cancelled_at' => $task->cancelled_at ?? now()],
                'archived' => ['archived_at' => $task->archived_at ?? now()],
                default => [],
            };
            $task->update($updates);

            if ($note) {
                $task->comments()->create(['user_id' => $actor->id, 'type' => 'status', 'body' => $note]);
            }

            if (in_array($status, ['completed', 'cancelled'], true)) {
                $task->assignments()->where('status', 'pending')->update([
                    'status' => 'replaced',
                    'replaced_at' => now(),
                ]);
            }

            if ($task->transfer && $status === 'in_progress') {
                $transfer = Transfer::query()->lockForUpdate()->findOrFail($task->transfer->id);
                $assetLines = $transfer->lines()->whereNotNull('tracked_asset_id')->get();
                $assetIds = $assetLines->pluck('tracked_asset_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
                $assets = TrackedAsset::query()
                    ->whereIn('id', $assetIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                foreach ($assetIds as $assetId) {
                    $asset = $assets->get($assetId);
                    $reservedElsewhere = TransferLine::query()
                        ->where('tracked_asset_id', $assetId)
                        ->where('transfer_id', '!=', $transfer->id)
                        ->whereHas('transfer', fn ($query) => $query
                            ->whereNull('archived_at')
                            ->whereNotIn('status', ['received', 'cancelled']))
                        ->exists();
                    if (! $asset
                        || $reservedElsewhere
                        || (int) $asset->current_location_id !== (int) $transfer->source_location_id
                        || ! in_array($asset->status, ['available', 'in_use'], true)) {
                        throw ValidationException::withMessages([
                            'status' => 'Un echipament nu mai este disponibil exclusiv in locatia sursa.',
                        ]);
                    }
                }

                $transfer->update([
                    'status' => 'in_transit',
                    'dispatched_at' => $transfer->dispatched_at ?? now(),
                ]);
                $assets->each(fn (TrackedAsset $asset) => $asset->update(['status' => 'in_transfer']));

                if ($transfer->currentApprovals()->where('status', '!=', 'approved')->exists()) {
                    activity()->performedOn($transfer)->causedBy($actor)->log('Pornit cu aprobari in asteptare');
                }
            }

            if ($task->transfer && $status === 'completed' && $responsibleAssignment?->driver) {
                $task->transfer->update(['driver_id' => $responsibleAssignment->driver_id]);
                $this->resetDriverApproval($task, $responsibleAssignment->driver, approved: true);
            }
        });
    }

    private function resetDriverApproval(Task $task, ?User $driver, bool $approved = false): void
    {
        $transfer = $task->transfer;
        if (! $transfer) {
            return;
        }

        TransferApproval::updateOrCreate(
            ['transfer_id' => $transfer->id, 'revision' => $transfer->revision, 'scope' => 'driver'],
            [
                'expected_user_id' => $driver?->id,
                'status' => $approved ? 'approved' : 'pending',
                'decided_by_user_id' => $approved ? $driver?->id : null,
                'decided_at' => $approved ? now() : null,
                'decision_note' => null,
            ]
        );

        if (! $approved && $transfer->status === 'approved') {
            $transfer->update([
                'status' => 'pending_approval',
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }

        if ($approved
            && $transfer->status === 'pending_approval'
            && ! $transfer->currentApprovals()->where('status', '!=', 'approved')->exists()) {
            $transfer->update([
                'status' => 'approved',
                'approved_by' => $driver?->id,
                'approved_at' => now(),
            ]);
        }
    }

    private function incumbentAssignment(Task $task): ?TaskAssignment
    {
        $current = $task->currentAssignment;
        if (! $current) {
            return null;
        }
        if (in_array($current->status, ['accepted', 'reassignment_requested'], true)) {
            return $current;
        }
        if ($current->status !== 'pending') {
            return null;
        }

        $previous = $current->relationLoaded('replacedAssignment')
            ? $current->replacedAssignment
            : $current->replacedAssignment()->first();
        if ($previous && in_array($previous->status, ['accepted', 'reassignment_requested'], true)) {
            return $previous;
        }

        return $task->assignments()
            ->whereKeyNot($current->id)
            ->whereIn('status', ['accepted', 'reassignment_requested'])
            ->latest('id')
            ->first();
    }

    private function ensureAssignableDriver(User $driver): void
    {
        if (! $driver->active || ! $driver->usesDriverWorkspace()) {
            throw ValidationException::withMessages([
                'driver_id' => 'Poate fi alocat doar un sofer activ.',
            ]);
        }
    }

    private function notifyManagers(Task $task, User $actor, string $title, string $message): void
    {
        $users = $this->managerRecipients($task)->reject(fn (User $user) => $user->is($actor));
        Notification::send($users, new WorkflowNotification($title, $message, route('tasks.show', $task)));
    }

    private function managerRecipients(Task $task): Collection
    {
        $task->loadMissing(['creator', 'sourceLocation.activeManagers', 'destinationLocation.activeManagers']);

        return collect([$task->creator])
            ->merge($task->sourceLocation?->activeManagers ?? [])
            ->merge($task->destinationLocation?->activeManagers ?? [])
            ->filter()
            ->unique('id')
            ->values();
    }
}
