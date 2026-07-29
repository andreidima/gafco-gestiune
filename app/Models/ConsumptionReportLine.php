<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consumption_report_id',
    'revision',
    'catalog_item_id',
    'quantity',
    'unit',
    'notes',
    'superseded_at',
])]
class ConsumptionReportLine extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'revision' => 'integer',
            'superseded_at' => 'datetime',
        ];
    }

    public function consumptionReport(): BelongsTo
    {
        return $this->belongsTo(ConsumptionReport::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
