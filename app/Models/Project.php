<?php

namespace App\Models;

use App\Models\Concerns\NormalizesInternalCodes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'location_id',
    'created_by',
    'status',
    'starts_on',
    'ends_on',
    'notes',
])]
class Project extends Model
{
    use HasFactory, NormalizesInternalCodes;

    protected function internalCodeAttributes(): array
    {
        return ['code'];
    }

    public const STATUS_LABELS = [
        'draft' => 'Ciornă',
        'active' => 'Activ',
        'completed' => 'Finalizat',
        'archived' => 'Arhivat',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function materialPlans(): HasMany
    {
        return $this->hasMany(ProjectMaterialPlan::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }
}
