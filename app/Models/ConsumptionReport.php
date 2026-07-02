<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'location_id', 'reported_by', 'status', 'reported_at', 'notes'])]
class ConsumptionReport extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ConsumptionReportLine::class);
    }
}
