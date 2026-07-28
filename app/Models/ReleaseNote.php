<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'slug',
    'version',
    'title',
    'summary',
    'body_markdown',
    'audience_roles',
    'affected_modules',
    'requires_action',
    'status',
    'released_at',
    'published_at',
    'created_by',
    'updated_by',
])]
class ReleaseNote extends Model
{
    protected function casts(): array
    {
        return [
            'audience_roles' => 'array',
            'affected_modules' => 'array',
            'requires_action' => 'boolean',
            'released_at' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
