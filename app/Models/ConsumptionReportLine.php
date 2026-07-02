<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['consumption_report_id', 'catalog_item_id', 'quantity', 'unit', 'notes'])]
class ConsumptionReportLine extends Model
{
    use HasFactory;

    public function consumptionReport(): BelongsTo
    {
        return $this->belongsTo(ConsumptionReport::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }
}
