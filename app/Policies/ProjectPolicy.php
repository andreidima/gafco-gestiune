<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAccessService;

class ProjectPolicy
{
    public function __construct(private readonly ProjectAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $this->access->canUse($user);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->access->canView($user, $project);
    }

    public function create(User $user): bool
    {
        return $this->access->canManage($user);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->access->canManage($user)
            && $project->status !== 'archived';
    }
}
