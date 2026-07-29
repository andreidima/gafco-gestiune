<?php

namespace App\Services;

use App\Models\Location;
use App\Models\OperationalAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalAlertAccessService
{
    private const GLOBAL_ROLES = [
        'super-admin',
        'admin',
        'dispecer',
        'manager',
        'contabil',
    ];

    private const LOCAL_ROLES = [
        'gestionar-baza',
        'sef-santier',
    ];

    public function canUse(User $user): bool
    {
        return $user->hasAnyRole(array_merge(self::GLOBAL_ROLES, self::LOCAL_ROLES));
    }

    public function visibleAlerts(User $user): Builder
    {
        abort_unless($this->canUse($user), 403);

        return OperationalAlert::query()
            ->whereHas('recipients', fn (Builder $recipients) => $recipients->whereKey($user->id))
            ->when(! $user->hasAnyRole(self::GLOBAL_ROLES), function (Builder $query) use ($user): void {
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
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn(
                'name',
                array_merge(self::GLOBAL_ROLES, self::LOCAL_ROLES),
            ))
            ->where(function (Builder $query) use ($location): void {
                $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', self::GLOBAL_ROLES))
                    ->orWhere(function (Builder $local) use ($location): void {
                        $local->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', self::LOCAL_ROLES))
                            ->whereHas('activeManagedLocations', fn (Builder $locations) => $locations
                                ->whereKey($location->id)
                                ->where('locations.active', true));
                    });
            })
            ->with('roles')
            ->get();
    }
}
