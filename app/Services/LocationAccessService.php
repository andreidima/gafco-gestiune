<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class LocationAccessService
{
    public function visibleLocations(User $user, string $ability = 'locations.view'): Builder
    {
        $visibleIds = $this->visibleLocationIds($user, $ability);

        return Location::query()
            ->where('active', true)
            ->when($visibleIds !== null, fn (Builder $query) => $query->whereIn('id', $visibleIds));
    }

    /** @return array<int, int>|null */
    public function visibleLocationIds(User $user, string $ability = 'locations.view'): ?array
    {
        $scope = $user->abilityScope($ability);

        return match ($scope) {
            'global', 'selected_location' => null,
            'assigned_locations', 'destination_location', 'visible_records' => $this->managedLocationIds($user),
            default => [],
        };
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

    public function canView(User $user, int $locationId, string $ability = 'locations.view'): bool
    {
        return $user->hasLocationAbility($ability, $locationId);
    }

    public function canWrite(User $user, int $locationId, string $ability = 'custody.manage'): bool
    {
        return $user->hasLocationAbility($ability, $locationId);
    }
}
