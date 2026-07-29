<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'location_id',
    'submitted_by',
    'processed_by',
    'supplier_reception_id',
    'status',
    'closure_type',
    'closed_at',
    'notes',
])]
class ReceptionIntake extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(SupplierReception::class, 'supplier_reception_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ReceptionDocument::class);
    }
}
