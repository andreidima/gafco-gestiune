<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['number', 'site_id', 'requested_by', 'assigned_driver_id', 'status', 'needed_at', 'pickup_address', 'delivery_address', 'notes', 'assigned_at', 'closed_at'])]
class DriverRequest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'needed_at' => 'datetime',
            'assigned_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'site_id');
    }

    public function assignedDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_driver_id');
    }
}
