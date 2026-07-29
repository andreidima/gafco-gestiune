<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'login_code', 'email', 'phone', 'password', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

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

    public function operationalAlerts(): BelongsToMany
    {
        return $this->belongsToMany(OperationalAlert::class, 'operational_alert_user')
            ->withPivot(['last_notified_severity', 'notified_at'])
            ->withTimestamps();
    }

    public function isProtectedAdministrator(): bool
    {
        $protectedEmail = Str::lower(trim((string) config('roles.protected_admin_email')));

        return $protectedEmail !== ''
            && Str::lower(trim((string) $this->email)) === $protectedEmail;
    }

    public function isOperationsAdmin(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'dispecer']);
    }

    public function isManagementUser(): bool
    {
        return $this->isOperationsAdmin() || $this->hasAnyRole(['manager', 'sef-santier', 'gestionar-baza']);
    }

    public function hasGlobalOperationalReadAccess(): bool
    {
        return $this->isOperationsAdmin() || $this->hasRole('manager');
    }

    public function hasGlobalInventoryReadAccess(): bool
    {
        return $this->hasGlobalOperationalReadAccess() || $this->hasRole('contabil');
    }

    public function canViewCommercialInventory(): bool
    {
        return $this->hasPermissionTo('inventory.view-commercial')
            || $this->hasAnyRole(['super-admin', 'admin', 'dispecer', 'manager', 'contabil']);
    }

    public function canViewInventory(): bool
    {
        return $this->hasAnyRole([
            'super-admin', 'admin', 'dispecer', 'manager',
            'sef-santier', 'gestionar-baza', 'contabil',
        ]);
    }

    public function usesDriverWorkspace(): bool
    {
        return $this->hasRole('sofer') && ! $this->isManagementUser();
    }

    public function usesWorkerWorkspace(): bool
    {
        return $this->hasRole('muncitor') && ! $this->isManagementUser() && ! $this->usesDriverWorkspace();
    }

    public function canManageLocations(): bool
    {
        return $this->isOperationsAdmin();
    }

    public function canManageInventoryMasterData(): bool
    {
        return $this->isOperationsAdmin() || $this->hasRole('gestionar-baza');
    }

    public function canManageTrackedAssets(): bool
    {
        return $this->isOperationsAdmin();
    }

    public function scopeAssignableDrivers(Builder $query): Builder
    {
        return $query
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'sofer'))
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', [
                'super-admin', 'admin', 'dispecer', 'sef-santier', 'gestionar-baza',
            ]));
    }
}
