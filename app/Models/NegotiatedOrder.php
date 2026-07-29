<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'number',
    'status',
    'location_id',
    'supplier_id',
    'created_by',
    'currency',
    'notes',
    'closure_type',
    'closure_reason',
    'closed_by',
    'closed_at',
])]
class NegotiatedOrder extends Model
{
    use HasFactory;

    public const STATUS_CREATED = 'created';

    public const STATUS_CLOSED = 'closed';

    public const CLOSURE_CANCELLED = 'cancelled';

    public const CLOSURE_RECEPTION = 'reception';

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(NegotiatedOrderLine::class);
    }

    public function reception(): HasOne
    {
        return $this->hasOne(SupplierReception::class);
    }

    public function isCreated(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }
}
