<?php

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active
            && $user->hasAnyRole(['super-admin', 'admin', 'dispecer', 'sef-santier', 'gestionar-baza', 'sofer']);
    }

    public function view(User $user, Transfer $transfer): bool
    {
        if (! $user->active) {
            return false;
        }

        if ($user->isOperationsAdmin()) {
            return true;
        }

        if ($user->usesDriverWorkspace()) {
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

        if (! $user->hasAnyRole(['sef-santier', 'gestionar-baza'])) {
            return false;
        }

        if ($transfer->requested_by === $user->id) {
            return true;
        }

        $managedLocationIds = $user->activeManagedLocations()->pluck('locations.id');

        return $managedLocationIds->contains($transfer->source_location_id)
            || $managedLocationIds->contains($transfer->destination_location_id);
    }

    public function create(User $user): bool
    {
        return $user->active
            && ($user->isOperationsAdmin() || $user->hasAnyRole(['sef-santier', 'gestionar-baza']));
    }

    public function update(User $user, Transfer $transfer): bool
    {
        return ! in_array($transfer->status, ['in_transit', 'received', 'cancelled'], true)
            && $transfer->archived_at === null
            && $this->canManage($user, $transfer);
    }

    public function approve(User $user, Transfer $transfer): bool
    {
        if (! $user->active
            || in_array($transfer->status, ['received', 'cancelled'], true)
            || $transfer->archived_at !== null) {
            return false;
        }

        if ($user->isOperationsAdmin()) {
            return true;
        }

        return $user->hasAnyRole(['sef-santier', 'gestionar-baza'])
            && $user->activeManagedLocations()
                ->whereIn('locations.id', [$transfer->source_location_id, $transfer->destination_location_id])
                ->exists();
    }

    public function cancel(User $user, Transfer $transfer): bool
    {
        return ! in_array($transfer->status, ['received', 'cancelled'], true)
            && $transfer->archived_at === null
            && $this->canManage($user, $transfer);
    }

    public function receive(User $user, Transfer $transfer): bool
    {
        if (! $user->active
            || ! in_array($transfer->status, ['in_transit', 'received'], true)
            || $transfer->archived_at !== null) {
            return false;
        }

        return $user->isOperationsAdmin()
            || ($user->hasAnyRole(['sef-santier', 'gestionar-baza'])
                && $user->activeManagedLocations()
                    ->where('locations.id', $transfer->destination_location_id)
                    ->exists());
    }

    public function archive(User $user, Transfer $transfer): bool
    {
        return in_array($transfer->status, ['received', 'cancelled'], true)
            && $transfer->archived_at === null
            && $this->canManage($user, $transfer);
    }

    private function canManage(User $user, Transfer $transfer): bool
    {
        return $user->active
            && ($user->isOperationsAdmin() || $user->hasAnyRole(['sef-santier', 'gestionar-baza']))
            && $this->view($user, $transfer);
    }
}
