<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['number', 'title', 'category', 'transfer_id', 'driver_request_id', 'created_by', 'source_location_id', 'destination_location_id', 'status', 'priority', 'manager_deadline', 'started_at', 'completed_at', 'cancelled_at', 'archived_at', 'notes'])]
class Task extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'manager_deadline' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function driverRequest(): BelongsTo
    {
        return $this->belongsTo(DriverRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(TaskAssignment::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->whereIn('status', ['pending', 'accepted', 'reassignment_requested'])
        );
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function isOverdue(): bool
    {
        return $this->manager_deadline !== null
            && $this->manager_deadline->isPast()
            && ! in_array($this->status, ['completed', 'cancelled', 'archived'], true);
    }
}
