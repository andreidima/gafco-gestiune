<?php

namespace App\Services;

use App\Models\Location;
use App\Models\OperationalAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalAlertAccessService
{
    public function canUse(User $user): bool
    {
        return $user->hasAbility('alerts.view');
    }

    public function visibleAlerts(User $user): Builder
    {
        abort_unless($this->canUse($user), 403);

        return OperationalAlert::query()
            ->whereHas('recipients', fn (Builder $recipients) => $recipients->whereKey($user->id))
            ->when(! $user->hasGlobalAbility('alerts.view'), function (Builder $query) use ($user): void {
                $query->whereIn('location_id', $user->activeManagedLocations()
                    ->where('locations.active', true)
                    ->pluck('locations.id'));
            });
    }

    public function canView(User $user, OperationalAlert $alert): bool
    {
        return $this->visibleAlerts($user)->whereKey($alert)->exists();
    }

    /**
     * @return Collection<int, User>
     */
    public function eligibleUsers(Location $location): Collection
    {
        return User::query()
            ->where('active', true)
            ->permission('alerts.view')
            ->with(['roles.permissions', 'permissions', 'activeManagedLocations'])
            ->get()
            ->filter(fn (User $user): bool => $user->hasLocationAbility('alerts.view', $location->id))
            ->values();
    }
}
