<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'title',
    'summary',
    'body_markdown',
    'section',
    'audience_roles',
    'sort_order',
    'status',
    'current_revision',
    'created_by',
    'updated_by',
    'published_at',
])]
class HelpArticle extends Model
{
    protected function casts(): array
    {
        return [
            'audience_roles' => 'array',
            'sort_order' => 'integer',
            'current_revision' => 'integer',
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

    public function revisions(): HasMany
    {
        return $this->hasMany(HelpArticleRevision::class);
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
