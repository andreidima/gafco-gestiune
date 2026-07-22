<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['task_id', 'driver_id', 'assigned_by', 'replaced_assignment_id', 'status', 'driver_estimate_at', 'driver_estimate_note', 'response_notes', 'accepted_at', 'rejected_at', 'reassignment_requested_at', 'replaced_at'])]
class TaskAssignment extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'driver_estimate_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'reassignment_requested_at' => 'datetime',
            'replaced_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function replacedAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_assignment_id');
    }

    public function replacementCandidates(): HasMany
    {
        return $this->hasMany(self::class, 'replaced_assignment_id');
    }
}
