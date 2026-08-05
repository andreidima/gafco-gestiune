<?php

namespace App\Services;

use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LocationResponsibilityService
{
    public const ELIGIBLE_ROLES = [
        'super-admin',
        'admin',
        'dispecer',
        'sef-santier',
        'gestionar-baza',
    ];

    public function eligibleUsers(): Collection
    {
        return User::query()
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $this->eligibleRoleQuery($query))
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /** @param array<int, int|string> $managerIds */
    public function assertEligible(array $managerIds): void
    {
        $managerIds = collect($managerIds)->map(fn ($id): int => (int) $id)->unique()->values();
        if ($managerIds->isEmpty()) {
            return;
        }

        $eligibleIds = User::query()
            ->whereKey($managerIds)
            ->where('active', true)
            ->whereHas('roles', fn ($query) => $this->eligibleRoleQuery($query))
            ->pluck('id');

        if ($eligibleIds->count() !== $managerIds->count()) {
            throw ValidationException::withMessages([
                'manager_user_ids' => 'Responsabilii trebuie să fie activi și să aibă un rol eligibil pentru administrarea locațiilor.',
            ]);
        }
    }

    /** @return array<int, string> */
    public function reconcile(User $user): array
    {
        $stillEligible = $user->active && $user->roles()
            ->where(fn ($query) => $this->eligibleRoleQuery($query))
            ->exists();
        if ($stillEligible) {
            return [];
        }

        $locations = $user->activeManagedLocations()->get();
        if ($locations->isEmpty()) {
            return [];
        }

        DB::table('location_manager')
            ->where('user_id', $user->id)
            ->where('active', true)
            ->update([
                'active' => false,
                'is_primary' => false,
                'updated_at' => now(),
            ]);

        foreach ($locations as $location) {
            $this->refreshPrimaryManager($location, $user);
        }

        return $locations
            ->sortBy('name')
            ->map(fn (Location $location): string => "{$location->code} - {$location->name}")
            ->values()
            ->all();
    }

    private function refreshPrimaryManager(Location $location, User $removedUser): void
    {
        $replacement = $location->activeManagers()
            ->where('users.active', true)
            ->whereKeyNot($removedUser->id)
            ->whereHas('roles', fn ($query) => $this->eligibleRoleQuery($query))
            ->orderByDesc('location_manager.is_primary')
            ->orderBy('users.name')
            ->first();

        DB::table('location_manager')
            ->where('location_id', $location->id)
            ->where('active', true)
            ->update(['is_primary' => false, 'updated_at' => now()]);

        if ($replacement) {
            DB::table('location_manager')
                ->where('location_id', $location->id)
                ->where('user_id', $replacement->id)
                ->update(['is_primary' => true, 'updated_at' => now()]);
        }

        $location->update(['manager_user_id' => $replacement?->id]);
    }

    private function eligibleRoleQuery($query): void
    {
        $query->where(function ($eligible): void {
            $eligible->whereIn('name', self::ELIGIBLE_ROLES);
            if (Schema::hasTable('access_role_profiles')) {
                $eligible->orWhereIn('roles.id', fn ($profiles) => $profiles
                    ->select('role_id')
                    ->from('access_role_profiles')
                    ->where('requires_locations', true));
            }
        });
    }
}
