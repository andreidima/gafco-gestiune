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

#[Fillable(['number', 'type', 'purpose', 'parent_transfer_id', 'project_id', 'revision', 'status', 'source_location_id', 'destination_location_id', 'requested_by', 'approved_by', 'driver_id', 'confirmed_by', 'document_number', 'document_path', 'requested_at', 'assigned_at', 'approved_at', 'dispatched_at', 'received_at', 'cancelled_at', 'archived_at', 'received_with_discrepancy', 'discrepancy_notes', 'notes'])]
class Transfer extends Model
{
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'assigned_at' => 'datetime',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'archived_at' => 'datetime',
            'received_with_discrepancy' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransferLine::class);
    }

    public function task(): HasOne
    {
        return $this->hasOne(Task::class);
    }

    public function parentTransfer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_transfer_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'parent_transfer_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TransferApproval::class);
    }

    public function currentApprovals(): HasMany
    {
        return $this->approvals()->where('revision', $this->revision);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TransferRevision::class);
    }
}
