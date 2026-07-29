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
    'reported_by',
    'modified_by',
    'status',
    'revision',
    'reported_at',
    'modified_at',
    'notes',
    'correction_reason',
])]
class ConsumptionReport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'modified_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ConsumptionReportLine::class)->whereNull('superseded_at');
    }

    public function allLines(): HasMany
    {
        return $this->hasMany(ConsumptionReportLine::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ConsumptionReportRevision::class);
    }
}
