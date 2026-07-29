<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'consumption_report_id',
    'revision',
    'before_data',
    'after_data',
    'reason',
    'changed_by',
    'changed_at',
])]
class ConsumptionReportRevision extends Model
{
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'before_data' => 'array',
            'after_data' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ConsumptionReport::class, 'consumption_report_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
