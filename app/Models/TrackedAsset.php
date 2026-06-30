<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catalog_item_id', 'asset_code', 'qr_code', 'serial_number', 'status', 'condition', 'current_location_id', 'current_custodian_id', 'photo_path', 'last_verified_at', 'notes'])]
class TrackedAsset extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['last_verified_at' => 'datetime'];
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function currentCustodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_custodian_id');
    }
}
