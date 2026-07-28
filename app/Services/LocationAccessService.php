<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LocationAccessService
{
    public function visibleLocations(User $user): Builder
    {
        return Location::query()
            ->where('active', true)
            ->when(! $user->hasGlobalInventoryReadAccess(), fn (Builder $query) => $query
                ->whereIn('id', $this->managedLocationIds($user)));
    }

    /** @return array<int, int>|null */
    public function visibleLocationIds(User $user): ?array
    {
        return $user->hasGlobalInventoryReadAccess()
            ? null
            : $this->managedLocationIds($user);
    }

    /** @return array<int, int> */
    public function managedLocationIds(User $user): array
    {
        return $user->activeManagedLocations()
            ->where('locations.active', true)
            ->pluck('locations.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function canView(User $user, int $locationId): bool
    {
        return $user->hasGlobalInventoryReadAccess()
            || in_array($locationId, $this->managedLocationIds($user), true);
    }

    public function canWrite(User $user, int $locationId): bool
    {
        return $user->isOperationsAdmin()
            || ($user->hasAnyRole(['sef-santier', 'gestionar-baza'])
                && in_array($locationId, $this->managedLocationIds($user), true));
    }
}
