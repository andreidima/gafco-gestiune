<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAbility('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->inScope($user, $task, 'tasks.view');
    }

    public function create(User $user): bool
    {
        return $user->hasAbility('tasks.create');
    }

    public function edit(User $user, Task $task): bool
    {
        return $this->inScope($user, $task, 'tasks.update')
            && $task->transfer_id === null
            && ! $task->isOperationallyLocked();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->edit($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->inScope($user, $task, 'tasks.assign')
            && ! $task->isOperationallyLocked();
    }

    public function transition(User $user, Task $task): bool
    {
        return $this->inScope($user, $task, 'tasks.transition') && $task->status !== 'archived';
    }

    public function respond(User $user, Task $task): bool
    {
        if (! $user->active
            || ! $user->hasAbility('tasks.respond')
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
        return $this->inScope($user, $task, 'tasks.comment') && ! $task->isOperationallyLocked();
    }

    private function inScope(User $user, Task $task, string $ability): bool
    {
        $scope = $user->abilityScope($ability);
        if ($scope === 'global') {
            return true;
        }

        if ($scope === 'assigned_records') {
            return $task->currentAssignment?->driver_id === $user->id
                || $task->assignments()
                    ->where('driver_id', $user->id)
                    ->whereIn('status', ['accepted', 'reassignment_requested'])
                    ->whereHas('replacementCandidates', fn ($candidate) => $candidate->where('status', 'pending'))
                    ->exists();
        }

        if (! in_array($scope, ['assigned_locations', 'visible_records'], true)) {
            return false;
        }

        if ($task->created_by === $user->id) {
            return true;
        }

        if ($task->currentAssignment?->driver_id === $user->id) {
            return true;
        }

        return $user->activeManagedLocations()
            ->whereIn('locations.id', [$task->source_location_id, $task->destination_location_id])
            ->exists();
    }
}
