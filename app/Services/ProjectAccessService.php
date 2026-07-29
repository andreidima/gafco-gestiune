<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectAccessService
{
    private const VIEW_ROLES = [
        'super-admin',
        'admin',
        'dispecer',
        'manager',
        'sef-santier',
        'gestionar-baza',
    ];

    private const PLANNING_ROLES = [
        'super-admin',
        'admin',
        'manager',
    ];

    public function canUse(User $user): bool
    {
        return $user->active && $user->hasAnyRole(self::VIEW_ROLES);
    }

    public function canManage(User $user): bool
    {
        return $user->active && $user->hasAnyRole(self::PLANNING_ROLES);
    }

    public function visibleProjects(User $user): Builder
    {
        abort_unless($this->canUse($user), 403);

        return Project::query()
            ->when(! $user->hasGlobalOperationalReadAccess(), fn (Builder $query) => $query
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
