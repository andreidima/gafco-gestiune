<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'location_id', 'supplier_id', 'received_by', 'document_type', 'document_number', 'document_photo_path', 'status', 'received_at', 'notes'])]
class SupplierReception extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierReceptionLine::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ReceptionDocument::class);
    }

    public function intakes(): HasMany
    {
        return $this->hasMany(ReceptionIntake::class);
    }
}
