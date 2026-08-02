<?php

namespace App\Models;

use App\Models\Concerns\NormalizesInternalCodes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category', 'tracking_type', 'sku', 'barcode', 'name', 'unit', 'description', 'active'])]
class CatalogItem extends Model
{
    use HasFactory, NormalizesInternalCodes;

    protected function internalCodeAttributes(): array
    {
        return ['sku'];
    }

    protected function nullableInternalCodeAttributes(): array
    {
        return ['sku'];
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function trackedAssets(): HasMany
    {
        return $this->hasMany(TrackedAsset::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function materialCustodies(): HasMany
    {
        return $this->hasMany(MaterialCustody::class);
    }

    public function projectMaterialPlans(): HasMany
    {
        return $this->hasMany(ProjectMaterialPlan::class);
    }
}
