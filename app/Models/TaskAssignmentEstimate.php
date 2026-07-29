<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['task_assignment_id', 'driver_id', 'estimated_at', 'note', 'correctable_until'])]
class TaskAssignmentEstimate extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'estimated_at' => 'datetime',
            'correctable_until' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TaskAssignment::class, 'task_assignment_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function canBeCorrected(): bool
    {
        return $this->correctable_until !== null
            && now()->lte($this->correctable_until);
    }

    public function correctionDeadline(): ?Carbon
    {
        return $this->correctable_until;
    }
}
