<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_reception_id', 'catalog_item_id', 'tracked_asset_id', 'quantity', 'unit'])]
class SupplierReceptionLine extends Model
{
    use HasFactory;

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(SupplierReception::class, 'supplier_reception_id');
    }
}
