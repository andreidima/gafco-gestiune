<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\NormalizesInternalCodes;
use App\Services\AccessScopeService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'login_code', 'email', 'phone', 'password', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasRoles, NormalizesInternalCodes, Notifiable;

    protected function internalCodeAttributes(): array
    {
        return ['login_code'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function managedLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_manager')
            ->withPivot(['active', 'is_primary'])
            ->withTimestamps();
    }

    public function activeManagedLocations(): BelongsToMany
    {
        return $this->managedLocations()->wherePivot('active', true);
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, 'driver_id');
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(UserPreference::class);
    }

    public function permissionExceptions(): HasMany
    {
        return $this->hasMany(AccessPermissionException::class);
    }

    public function operationalAlerts(): BelongsToMany
    {
        return $this->belongsToMany(OperationalAlert::class, 'operational_alert_user')
            ->withPivot(['last_notified_severity', 'notified_at'])
            ->withTimestamps();
    }

    public function materialCustodies(): HasMany
    {
        return $this->hasMany(MaterialCustody::class);
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function isProtectedAdministrator(): bool
    {
        $protectedEmail = Str::lower(trim((string) config('roles.protected_admin_email')));

        return $protectedEmail !== ''
            && Str::lower(trim((string) $this->email)) === $protectedEmail;
    }

    public function isOperationsAdmin(): bool
    {
        return $this->hasGlobalAbility('inventory.manage');
    }

    public function isManagementUser(): bool
    {
        return $this->hasAbility('tracked-assets.browse')
            || $this->hasAbility('projects.view')
            || $this->hasAbility('reports.view');
    }

    public function hasGlobalOperationalReadAccess(): bool
    {
        return collect(['transfers.view', 'tasks.view', 'reception-intakes.view'])
            ->contains(fn (string $ability): bool => $this->hasGlobalAbility($ability));
    }

    public function hasGlobalInventoryReadAccess(): bool
    {
        return $this->hasGlobalAbility('inventory.view');
    }

    public function canViewCommercialInventory(): bool
    {
        return $this->hasAbility('inventory.view-commercial');
    }

    public function canViewInventory(): bool
    {
        return $this->hasAbility('inventory.view');
    }

    public function usesDriverWorkspace(): bool
    {
        return $this->abilityScope('tasks.respond') === 'assigned_records'
            && $this->abilityScope('tasks.view') === 'assigned_records';
    }

    public function usesWorkerWorkspace(): bool
    {
        return $this->abilityScope('custody.view') === 'personal'
            && ! $this->usesDriverWorkspace();
    }

    public function canManageLocations(): bool
    {
        return $this->hasAbility('locations.manage');
    }

    public function canManageInventoryMasterData(): bool
    {
        return $this->hasAbility('catalog.manage');
    }

    public function canManageTrackedAssets(): bool
    {
        return $this->hasAbility('tracked-assets.manage');
    }

    public function hasAbility(string $ability): bool
    {
        return app(AccessScopeService::class)->allows($this, $ability);
    }

    public function abilityScope(string $ability): ?string
    {
        return app(AccessScopeService::class)->scope($this, $ability);
    }

    public function hasGlobalAbility(string $ability): bool
    {
        return app(AccessScopeService::class)->isGlobal($this, $ability);
    }

    public function hasLocationAbility(string $ability, int $locationId): bool
    {
        return app(AccessScopeService::class)->allowsManagedLocation($this, $ability, $locationId);
    }

    public function scopeAssignableDrivers(Builder $query): Builder
    {
        $rolesWithBroaderTaskAccess = collect(
            (config('access.permissions', [])['tasks.view'] ?? [])['grants'] ?? []
        )
            ->reject(fn (string $scope): bool => $scope === 'assigned_records')
            ->keys()
            ->all();

        return $query
            ->permission('tasks.respond')
            ->where(function (Builder $withTaskAccess): void {
                $withTaskAccess
                    ->whereHas('roles.permissions', fn (Builder $permissions) => $permissions->where('name', 'tasks.view'))
                    ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('name', 'tasks.view'));
            })
            ->when($rolesWithBroaderTaskAccess !== [], fn (Builder $drivers) => $drivers
                ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', $rolesWithBroaderTaskAccess)));
    }
}
