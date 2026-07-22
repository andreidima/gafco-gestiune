<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'code', 'name', 'address', 'manager_user_id', 'active', 'notes'])]
class Location extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function trackedAssets(): HasMany
    {
        return $this->hasMany(TrackedAsset::class, 'current_location_id');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_manager')
            ->withPivot(['active', 'is_primary'])
            ->withTimestamps();
    }

    public function activeManagers(): BelongsToMany
    {
        return $this->managers()->wherePivot('active', true);
    }

    public function transferApprovals(): HasMany
    {
        return $this->hasMany(TransferApproval::class);
    }
}
