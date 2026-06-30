<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transfer_id', 'catalog_item_id', 'tracked_asset_id', 'quantity', 'unit', 'received_status', 'notes'])]
class TransferLine extends Model
{
    use HasFactory;

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function trackedAsset(): BelongsTo
    {
        return $this->belongsTo(TrackedAsset::class);
    }
}
