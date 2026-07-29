<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_type', 'tracked_asset_id', 'catalog_item_id', 'quantity', 'unit',
    'from_user_id', 'to_user_id', 'location_id', 'initiated_by', 'status', 'qr_token',
    'expires_at', 'from_approved_at', 'to_approved_at', 'manager_approved_by',
    'return_condition', 'response_notes', 'accepted_at', 'rejected_at', 'rejected_by', 'notes',
])]
class CustodyTransfer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'from_approved_at' => 'datetime',
            'to_approved_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'quantity' => 'decimal:3',
        ];
    }

    public function trackedAsset(): BelongsTo
    {
        return $this->belongsTo(TrackedAsset::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function isMaterial(): bool
    {
        return $this->catalog_item_id !== null;
    }

    public function itemLabel(): string
    {
        if ($this->isMaterial()) {
            return $this->catalogItem?->name ?? 'Material';
        }

        return trim(($this->trackedAsset?->asset_code ?? 'Echipament').' · '.($this->trackedAsset?->catalogItem?->name ?? ''));
    }
}
