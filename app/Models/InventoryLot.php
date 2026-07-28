<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'catalog_item_id', 'supplier_id', 'supplier_reception_line_id', 'source_key',
    'lot_code', 'document_number', 'document_date', 'received_at', 'expires_at',
    'unit_price', 'currency', 'is_opening_balance', 'notes',
])]
class InventoryLot extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'received_at' => 'datetime',
            'expires_at' => 'date',
            'unit_price' => 'decimal:4',
            'is_opening_balance' => 'boolean',
        ];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receptionLine(): BelongsTo
    {
        return $this->belongsTo(SupplierReceptionLine::class, 'supplier_reception_line_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryLotBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
