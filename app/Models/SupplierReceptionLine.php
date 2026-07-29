<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'supplier_reception_id',
    'catalog_item_id',
    'tracked_asset_id',
    'quantity',
    'unit',
    'lot_code',
    'expires_at',
    'unit_price',
    'currency',
    'notes',
])]
class SupplierReceptionLine extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:4',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(SupplierReception::class, 'supplier_reception_id');
    }

    public function inventoryLot(): HasOne
    {
        return $this->hasOne(InventoryLot::class, 'supplier_reception_line_id');
    }
}
