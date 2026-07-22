<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tracked_asset_id', 'from_user_id', 'to_user_id', 'status', 'qr_token', 'expires_at', 'from_approved_at', 'to_approved_at', 'accepted_at', 'rejected_at', 'rejected_by', 'notes'])]
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
        ];
    }

    public function trackedAsset(): BelongsTo
    {
        return $this->belongsTo(TrackedAsset::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
