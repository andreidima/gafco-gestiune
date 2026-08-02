<?php

namespace App\Models;

use App\Support\RomanianUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'alert_type',
    'fingerprint',
    'severity',
    'location_id',
    'alertable_type',
    'alertable_id',
    'title',
    'message',
    'url',
    'metadata',
    'triggered_at',
    'due_at',
    'last_detected_at',
    'resolved_at',
])]
class OperationalAlert extends Model
{
    use HasFactory;

    public const TYPE_LABELS = [
        'lot_expiration' => 'Expirare lot',
        'reception_pending' => 'Recepție neprocesată',
        'project_plan_overrun' => 'Plan de materiale depășit',
    ];

    public const SEVERITY_LABELS = [
        'warning' => 'Necesită atenție',
        'danger' => 'Critic',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'triggered_at' => 'datetime',
            'due_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function alertable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'operational_alert_user')
            ->withPivot(['last_notified_severity', 'notified_at'])
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    public function isActive(): bool
    {
        return $this->resolved_at === null;
    }

    public function localizedUrl(): string
    {
        return app(RomanianUrl::class)->translate($this->url) ?? $this->url;
    }
}
