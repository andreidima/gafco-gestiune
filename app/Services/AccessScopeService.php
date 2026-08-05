<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AccessScopeService
{
    private const PRIORITY = [
        'global' => 100,
        'protected_identity' => 95,
        'assigned_locations' => 80,
        'destination_location' => 75,
        'selected_location' => 70,
        'visible_records' => 60,
        'assigned_records' => 50,
        'personal' => 40,
        'lookup' => 30,
    ];

    public function allows(User $user, string $ability): bool
    {
        if (! $user->active) {
            return false;
        }

        $definition = config('access.permissions', [])[$ability] ?? null;
        if (($definition['driver'] ?? 'permission') === 'gate') {
            return Gate::forUser($user)->allows($ability);
        }

        try {
            return $user->hasPermissionTo($ability);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    public function scope(User $user, string $ability): ?string
    {
        if (! $this->allows($user, $ability)) {
            return null;
        }

        $definition = config('access.permissions', [])[$ability] ?? [];
        if (($definition['driver'] ?? 'permission') === 'gate') {
            return $definition['direct_scope'] ?? 'protected_identity';
        }

        $user->loadMissing(['roles.permissions', 'permissions']);
        $scopes = collect();

        foreach ($user->roles as $role) {
            if ($role->permissions->contains('name', $ability)) {
                $scopes->push($definition['grants'][$role->name] ?? ($definition['direct_scope'] ?? 'visible_records'));
            }
        }

        if ($user->permissions->contains('name', $ability)) {
            $scopes->push($definition['direct_scope'] ?? 'visible_records');
        }

        return $scopes
            ->sortByDesc(fn (string $scope): int => self::PRIORITY[$scope] ?? 0)
            ->first();
    }

    public function isGlobal(User $user, string $ability): bool
    {
        return $this->scope($user, $ability) === 'global';
    }

    public function allowsManagedLocation(User $user, string $ability, int $locationId): bool
    {
        $scope = $this->scope($user, $ability);
        if ($scope === 'global') {
            return true;
        }

        if ($scope === 'selected_location') {
            return Location::query()->whereKey($locationId)->where('active', true)->exists();
        }

        if (! in_array($scope, ['assigned_locations', 'destination_location', 'visible_records'], true)) {
            return false;
        }

        return $user->activeManagedLocations()
            ->where('locations.active', true)
            ->whereKey($locationId)
            ->exists();
    }
}
