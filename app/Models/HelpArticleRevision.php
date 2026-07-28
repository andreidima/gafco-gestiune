<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'help_article_id',
    'revision',
    'title',
    'summary',
    'body_markdown',
    'change_summary',
    'source',
    'created_by',
    'published_at',
])]
class HelpArticleRevision extends Model
{
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
