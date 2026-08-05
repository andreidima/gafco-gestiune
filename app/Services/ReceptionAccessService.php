<?php

namespace App\Services;

use App\Models\ReceptionDocument;
use App\Models\ReceptionIntake;
use App\Models\SupplierReception;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ReceptionAccessService
{
    public function visibleReceptions(User $user): Builder
    {
        return SupplierReception::query()
            ->when(! $user->hasAbility('receptions.view'), fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when(! $user->hasGlobalAbility('receptions.view'), fn (Builder $query) => $query
                ->whereIn('location_id', $this->managedLocationIds($user)));
    }

    public function visibleIntakes(User $user): Builder
    {
        $scope = $user->abilityScope('reception-intakes.view');

        return ReceptionIntake::query()
            ->when($scope === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($scope !== 'global', function (Builder $query) use ($scope, $user): void {
                $managed = $this->managedLocationIds($user);
                $query->where(function (Builder $visible) use ($scope, $user, $managed): void {
                    $visible->where('submitted_by', $user->id);
                    if ($managed !== [] && $scope !== 'personal') {
                        $visible->orWhereIn('location_id', $managed);
                    }
                });
            });
    }

    public function canViewReception(User $user, SupplierReception $reception): bool
    {
        return $this->visibleReceptions($user)->whereKey($reception)->exists();
    }

    public function canViewIntake(User $user, ReceptionIntake $intake): bool
    {
        return $this->visibleIntakes($user)->whereKey($intake)->exists();
    }

    public function canProcessIntake(User $user, ReceptionIntake $intake): bool
    {
        return $intake->status === 'created'
            && $user->hasLocationAbility('receptions.create', (int) $intake->location_id);
    }

    public function canCancelIntake(User $user, ReceptionIntake $intake): bool
    {
        return $intake->status === 'created'
            && $user->hasLocationAbility('reception-intakes.cancel', (int) $intake->location_id);
    }

    public function canEditAllReceptionDetails(User $user, SupplierReception $reception): bool
    {
        return ($user->hasAbility('reception-details.edit-all')
                || $user->hasAbility('accounting.edit-operations'))
            && $this->canViewReception($user, $reception);
    }

    public function canEditReceptionExpiration(User $user, SupplierReception $reception): bool
    {
        return ($this->canEditAllReceptionDetails($user, $reception)
                || $user->hasLocationAbility('reception-details.edit-expiration', (int) $reception->location_id))
            && $this->canViewReception($user, $reception);
    }

    public function canViewDocument(User $user, ReceptionDocument $document): bool
    {
        if ($document->reception && $this->canViewReception($user, $document->reception)) {
            return true;
        }

        return $document->intake && $this->canViewIntake($user, $document->intake);
    }

    /** @return array<int, int> */
    private function managedLocationIds(User $user): array
    {
        return $user->activeManagedLocations()
            ->where('locations.active', true)
            ->pluck('locations.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
