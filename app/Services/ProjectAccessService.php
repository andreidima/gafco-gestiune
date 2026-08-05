<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectAccessService
{
    public function canUse(User $user): bool
    {
        return $user->hasAbility('projects.view');
    }

    public function canManage(User $user): bool
    {
        return $user->hasAbility('projects.manage');
    }

    public function visibleProjects(User $user): Builder
    {
        abort_unless($this->canUse($user), 403);

        return Project::query()
            ->when(! $user->hasGlobalAbility('projects.view'), fn (Builder $query) => $query
                ->whereIn('location_id', $user->activeManagedLocations()
                    ->where('locations.active', true)
                    ->pluck('locations.id')));
    }

    public function canView(User $user, Project $project): bool
    {
        return $this->canUse($user)
            && $this->visibleProjects($user)->whereKey($project)->exists();
    }
}
