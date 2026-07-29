<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->active
            && $user->hasAnyRole(['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza', 'sofer']);
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->active) {
            return false;
        }

        if ($user->hasGlobalOperationalReadAccess()) {
            return true;
        }

        if ($user->usesDriverWorkspace()) {
            return $task->currentAssignment?->driver_id === $user->id
                || $task->assignments()
                    ->where('driver_id', $user->id)
                    ->whereIn('status', ['accepted', 'reassignment_requested'])
                    ->whereHas('replacementCandidates', fn ($candidate) => $candidate->where('status', 'pending'))
                    ->exists();
        }

        if (! $user->hasAnyRole(['sef-santier', 'gestionar-baza'])) {
            return false;
        }

        if ($task->created_by === $user->id) {
            return true;
        }

        $managedLocationIds = $user->activeManagedLocations()->pluck('locations.id');

        return $managedLocationIds->contains($task->source_location_id)
            || $managedLocationIds->contains($task->destination_location_id);
    }

    public function create(User $user): bool
    {
        return $user->active
            && ($user->isOperationsAdmin() || $user->hasAnyRole(['sef-santier', 'gestionar-baza']));
    }

    public function edit(User $user, Task $task): bool
    {
        return $this->canManage($user, $task)
            && $task->transfer_id === null
            && ! $task->isOperationallyLocked();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->edit($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->canManage($user, $task)
            && ! $task->isOperationallyLocked();
    }

    public function transition(User $user, Task $task): bool
    {
        return $this->canManage($user, $task) && $task->status !== 'archived';
    }

    public function respond(User $user, Task $task): bool
    {
        if (! $user->active
            || ! $user->usesDriverWorkspace()
            || $task->isOperationallyLocked()) {
            return false;
        }

        return $task->currentAssignment?->driver_id === $user->id
            || $task->assignments()
                ->where('driver_id', $user->id)
                ->whereIn('status', ['accepted', 'reassignment_requested'])
                ->whereHas('replacementCandidates', fn ($candidate) => $candidate->where('status', 'pending'))
                ->exists();
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task) && ! $task->isOperationallyLocked();
    }

    private function canManage(User $user, Task $task): bool
    {
        return $user->active
            && ($user->isOperationsAdmin() || $user->hasAnyRole(['sef-santier', 'gestionar-baza']))
            && $this->view($user, $task);
    }
}
