<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('transfers.view');
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $this->inScope($user, $transfer, 'transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('transfers.create');
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return ! in_array($transfer->status, ['in_transit', 'received', 'cancelled'], true)
            && $transfer->archived_at === null
            && $this->inScope($user, $transfer, 'transfers.update');
    }

    public function approve(User $user, Transfer $transfer): bool
    {
        if (! $user->active
            || in_array($transfer->status, ['received', 'cancelled'], true)
            || $transfer->archived_at !== null) {
            return false;
        }

        return $this->inScope($user, $transfer, 'transfers.approve');
    }

    public function cancel(User $user, Transfer $transfer): bool
    {
        return ! in_array($transfer->status, ['received', 'cancelled'], true)
            && $transfer->archived_at === null
            && $this->inScope($user, $transfer, 'transfers.cancel');
    }

    public function receive(User $user, Transfer $transfer): bool
    {
        if (! $user->active
            || ! in_array($transfer->status, ['in_transit', 'received'], true)
            || $transfer->archived_at !== null) {
            return false;
        }

        return $this->inScope($user, $transfer, 'transfers.receive');
    }

    public function archive(User $user, Transfer $transfer): bool
    {
        return in_array($transfer->status, ['received', 'cancelled'], true)
            && $transfer->archived_at === null
            && $this->inScope($user, $transfer, 'transfers.archive');
    }

    private function inScope(User $user, Transfer $transfer, string $ability): bool
    {
        $scope = $user->abilityScope($ability);
        if ($scope === 'global') {
            return true;
        }

        if ($scope === 'assigned_records') {
            if ($transfer->driver_id === $user->id) {
                return true;
            }

            return $transfer->task()
                ->where(function ($task) use ($user): void {
                    $task->whereHas('currentAssignment', fn ($assignment) => $assignment->where('driver_id', $user->id))
                        ->orWhereHas('assignments', fn ($assignment) => $assignment
                            ->where('driver_id', $user->id)
                            ->whereIn('status', ['accepted', 'reassignment_requested'])
                            ->whereHas('replacementCandidates', fn ($candidate) => $candidate->where('status', 'pending')));
                })
                ->exists();
        }

        if (! in_array($scope, ['assigned_locations', 'destination_location', 'visible_records'], true)) {
            return false;
        }

        if ($scope !== 'destination_location' && $transfer->requested_by === $user->id) {
            return true;
        }

        if ($scope !== 'destination_location'
            && ($transfer->driver_id === $user->id
                || $transfer->task()->whereHas('currentAssignment', fn ($assignment) => $assignment->where('driver_id', $user->id))->exists())) {
            return true;
        }

        $locationIds = $scope === 'destination_location'
            ? [$transfer->destination_location_id]
            : [$transfer->source_location_id, $transfer->destination_location_id];

        return $user->activeManagedLocations()->whereIn('locations.id', $locationIds)->exists();
    }
}
