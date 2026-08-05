<?php

namespace App\Services;

use Illuminate\Support\Collection;

class AccessCatalog
{
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
        return array_keys($this->roles());
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
        return config("roles.labels.{$role}", $role);
    }

    public function scopeLabel(string $scope): string
    {
        return config("access.scope_labels.{$scope}", $scope);
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
}
