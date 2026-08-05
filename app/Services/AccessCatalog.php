<?php

namespace App\Services;

use App\Models\AccessRoleProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class AccessCatalog
{
    /** @var array<string, string> */
    private array $customRoleLabels = [];

    /** @var Collection<int, AccessRoleProfile>|null */
    private ?Collection $customRoleProfiles = null;

    /** @return array<string, array<string, mixed>> */
    public function permissions(): array
    {
        return config('access.permissions', []);
    }

    /** @return array<string, array<string, mixed>> */
    public function seedablePermissions(): array
    {
        return array_filter(
            $this->permissions(),
            fn (array $permission): bool => ($permission['driver'] ?? 'permission') === 'permission',
        );
    }

    /** @return array<string, array<string, mixed>> */
    public function roles(): array
    {
        return config('access.roles', []);
    }

    /** @return array<int, string> */
    public function roleNames(): array
    {
        $names = collect(array_keys($this->roles()));
        if (! Schema::hasTable('access_role_profiles')) {
            return $names->all();
        }

        return $names
            ->merge($this->customRoleProfiles()->pluck('role.name'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function roleRequiresLocations(string $role): bool
    {
        $configured = config("access.roles.{$role}.requires_locations");
        if (is_bool($configured)) {
            return $configured;
        }

        if (! Schema::hasTable('access_role_profiles')) {
            return false;
        }

        return (bool) $this->customRoleProfiles()
            ->first(fn (AccessRoleProfile $profile): bool => $profile->role?->name === $role)
            ?->requires_locations;
    }

    /** @return array<int, string> */
    public function rolesRequiringLocations(): array
    {
        return collect($this->roleNames())
            ->filter(fn (string $role): bool => $this->roleRequiresLocations($role))
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    public function permissionsForRole(string $role): array
    {
        return collect($this->seedablePermissions())
            ->filter(fn (array $permission): bool => array_key_exists($role, $permission['grants'] ?? []))
            ->keys()
            ->values()
            ->all();
    }

    public function roleLabel(string $role): string
    {
        $configured = config("roles.labels.{$role}");
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (array_key_exists($role, $this->customRoleLabels)) {
            return $this->customRoleLabels[$role];
        }

        if (! Schema::hasTable('access_role_profiles')) {
            return $role;
        }

        return $this->customRoleLabels[$role] = $this->customRoleProfiles()
            ->first(fn (AccessRoleProfile $profile): bool => $profile->role?->name === $role)
            ?->label ?? $role;
    }

    public function scopeLabel(string $scope): string
    {
        return config("access.scope_labels.{$scope}", $scope);
    }

    /** @return array<int, string> */
    public function reservedPermissions(): array
    {
        return config('access.reserved_permissions', []);
    }

    /** @return array<string, array<string, mixed>> */
    public function directAssignablePermissions(): array
    {
        $allowed = array_flip(config('access.direct_exception_permissions', []));

        return array_filter(
            $this->seedablePermissions(),
            fn (array $definition, string $ability): bool => isset($allowed[$ability])
                && ! in_array($ability, $this->reservedPermissions(), true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * @return Collection<string, Collection<string, array{ability: string, definition: array<string, mixed>}>>
     */
    public function permissionsByModule(): Collection
    {
        $order = array_flip(config('access.module_order', []));

        return collect($this->permissions())
            ->map(fn (array $definition, string $ability): array => compact('ability', 'definition'))
            ->groupBy(fn (array $entry): string => $entry['definition']['module'])
            ->sortBy(fn (Collection $permissions, string $module): int => $order[$module] ?? 999)
            ->map(fn (Collection $permissions): Collection => $permissions
                ->sortBy(fn (array $entry): string => $entry['definition']['label'])
                ->values());
    }

    /** @return Collection<int, AccessRoleProfile> */
    private function customRoleProfiles(): Collection
    {
        if ($this->customRoleProfiles !== null) {
            return $this->customRoleProfiles;
        }

        if (! Schema::hasTable('access_role_profiles')) {
            return $this->customRoleProfiles = collect();
        }

        return $this->customRoleProfiles = AccessRoleProfile::query()
            ->with('role:id,name,guard_name')
            ->get()
            ->filter(fn (AccessRoleProfile $profile): bool => $profile->role?->guard_name === 'web')
            ->values();
    }
}
