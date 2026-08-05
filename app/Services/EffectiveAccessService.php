<?php

namespace App\Services;

use App\Authorization\AccessDecision;
use App\Models\Location;
use App\Models\User;
use Illuminate\Support\Collection;

class EffectiveAccessService
{
    /** @var array<int, string>|null */
    private ?array $selectableLocations = null;

    public function __construct(
        private readonly AccessCatalog $catalog,
        private readonly AccessScopeService $scopes,
    ) {}

    /** @return Collection<int, AccessDecision> */
    public function decisions(User $user): Collection
    {
        $user->loadMissing(['roles.permissions', 'permissions', 'permissionExceptions.permission', 'activeManagedLocations']);
        $directPermissions = $user->permissions->pluck('name');
        $exceptionContexts = $user->permissionExceptions->keyBy('permission.name');
        $locations = $user->activeManagedLocations
            ->sortBy('name')
            ->map(fn ($location): string => "{$location->code} - {$location->name}")
            ->values()
            ->all();
        $selectableLocations = $this->selectableLocations();

        return collect($this->catalog->permissions())
            ->map(function (array $definition, string $ability) use ($user, $directPermissions, $exceptionContexts, $locations, $selectableLocations): AccessDecision {
                $sources = collect();

                foreach ($user->roles as $role) {
                    if ($role->permissions->contains('name', $ability)) {
                        $sources->push([
                            'type' => 'role',
                            'label' => 'Rol: '.$this->catalog->roleLabel($role->name),
                            'scope' => $definition['grants'][$role->name] ?? ($definition['direct_scope'] ?? 'visible_records'),
                        ]);
                    }
                }

                if ($directPermissions->contains($ability)) {
                    $context = $exceptionContexts->get($ability);
                    $sources->push([
                        'type' => 'direct',
                        'label' => 'Excepție atribuită direct',
                        'scope' => $definition['direct_scope'] ?? 'visible_records',
                        'reason' => $context?->reason ?? 'Justificarea nu este disponibilă.',
                    ]);
                }

                if (($definition['driver'] ?? 'permission') === 'gate') {
                    if ($this->scopes->allows($user, $ability)) {
                        $sources = collect([[
                            'type' => 'protected',
                            'label' => 'Identitatea contului protejat',
                            'scope' => $definition['direct_scope'] ?? 'protected_identity',
                        ]]);
                    }
                }

                $allowed = $this->scopes->allows($user, $ability);
                $scope = $this->scopes->scope($user, $ability)
                    ?? ($definition['direct_scope'] ?? 'visible_records');
                $condition = $definition['condition'] ?? null;
                $conditional = $allowed && ($scope !== 'global' || $condition !== null);

                $reason = match (true) {
                    ! $user->active => 'Contul este inactiv, deci accesul operațional este oprit.',
                    ! $allowed => 'Niciun rol și nicio excepție directă nu acordă această capabilitate.',
                    $sources->where('type', 'direct')->isNotEmpty() => 'Acces acordat printr-o excepție directă, în limitele domeniului afișat.',
                    default => 'Acces moștenit din '.mb_strtolower($sources->pluck('label')->join(', ')).'.',
                };

                return new AccessDecision(
                    ability: $ability,
                    module: $definition['module'],
                    label: $definition['label'],
                    description: $definition['description'],
                    risk: $definition['risk'] ?? 'normal',
                    allowed: $allowed,
                    conditional: $conditional,
                    scope: $scope,
                    scopeLabel: $this->catalog->scopeLabel($scope),
                    reason: $reason,
                    sources: $sources->values()->all(),
                    locations: match ($scope) {
                        'assigned_locations', 'destination_location', 'visible_records' => $locations,
                        'selected_location' => $selectableLocations,
                        default => [],
                    },
                    condition: $condition,
                );
            })
            ->sortBy(function (AccessDecision $decision): string {
                $position = array_search($decision->module, config('access.module_order', []), true);

                return sprintf('%03d-%s', $position === false ? 999 : $position, $decision->label);
            })
            ->values();
    }

    /** @return Collection<string, Collection<int, AccessDecision>> */
    public function groupedDecisions(User $user): Collection
    {
        return $this->decisions($user)->groupBy('module');
    }

    /** @return array{allowed: int, global: int, conditional: int, denied: int, direct: int} */
    public function summary(User $user): array
    {
        $decisions = $this->decisions($user);

        return [
            'allowed' => $decisions->where('allowed', true)->count(),
            'global' => $decisions->where('allowed', true)->where('scope', 'global')->count(),
            'conditional' => $decisions->where('allowed', true)->where('conditional', true)->count(),
            'denied' => $decisions->where('allowed', false)->count(),
            'direct' => $user->permissions->count(),
        ];
    }

    /** @return Collection<int, array{severity: string, message: string}> */
    public function warnings(User $user): Collection
    {
        $user->loadMissing(['roles', 'permissions', 'permissionExceptions.permission', 'activeManagedLocations']);
        $roles = $user->roles->pluck('name');
        $warnings = collect();

        if (! $user->active) {
            $warnings->push(['severity' => 'secondary', 'message' => 'Cont inactiv: accesul operațional este oprit.']);
        }

        if ($roles->isEmpty()) {
            $warnings->push(['severity' => 'warning', 'message' => 'Nu are niciun rol operațional.']);
        }

        $unknownRoles = $roles->diff($this->catalog->roleNames());
        if ($unknownRoles->isNotEmpty()) {
            $warnings->push(['severity' => 'danger', 'message' => 'Există roluri neînregistrate în catalog: '.$unknownRoles->join(', ').'.']);
        }

        if ($roles->intersect($this->catalog->rolesRequiringLocations())->isNotEmpty()
            && $user->activeManagedLocations->isEmpty()) {
            $warnings->push(['severity' => 'warning', 'message' => 'Rol local fără nicio locație administrată.']);
        }

        if ($user->activeManagedLocations->isNotEmpty()
            && $roles->intersect(['super-admin', 'admin', 'dispecer', 'sef-santier', 'gestionar-baza'])->isEmpty()) {
            $warnings->push(['severity' => 'danger', 'message' => 'Are responsabilități de locație, dar nu mai are un rol eligibil.']);
        }

        if ($roles->contains('sofer') && $roles->intersect(['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza'])->isNotEmpty()) {
            $warnings->push(['severity' => 'warning', 'message' => 'Rolul de management are prioritate; spațiul și acțiunile de șofer nu sunt active.']);
        }

        if ($roles->contains('muncitor') && ($roles->contains('sofer')
            || $roles->intersect(['super-admin', 'admin', 'dispecer', 'manager', 'sef-santier', 'gestionar-baza'])->isNotEmpty())) {
            $warnings->push(['severity' => 'warning', 'message' => 'Rolul de muncitor este secundar și nu controlează spațiul principal de lucru.']);
        }

        if ($user->permissions->isNotEmpty()) {
            $warnings->push([
                'severity' => 'info',
                'message' => $user->permissions->count().' '.($user->permissions->count() === 1 ? 'excepție este atribuită' : 'excepții sunt atribuite').' direct.',
            ]);
        }

        $documentedPermissions = $user->permissionExceptions->pluck('permission.name');
        $undocumented = $user->permissions->pluck('name')->diff($documentedPermissions);
        if ($undocumented->isNotEmpty()) {
            $warnings->push([
                'severity' => 'warning',
                'message' => 'Există excepții fără justificare înregistrată: '.$undocumented->join(', ').'.',
            ]);
        }

        $registered = collect(array_keys($this->catalog->permissions()));
        $unknown = $user->getAllPermissions()->pluck('name')->diff($registered);
        if ($unknown->isNotEmpty()) {
            $warnings->push(['severity' => 'danger', 'message' => 'Există drepturi neînregistrate în catalog: '.$unknown->join(', ').'.']);
        }

        return $warnings;
    }

    /** @return array<int, string> */
    private function selectableLocations(): array
    {
        return $this->selectableLocations ??= Location::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn (Location $location): string => "{$location->code} - {$location->name}")
            ->all();
    }
}
