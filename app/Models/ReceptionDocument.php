<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reception_intake_id',
    'supplier_reception_id',
    'uploaded_by',
    'document_type',
    'custom_label',
    'original_name',
    'stored_path',
    'mime_type',
    'size_bytes',
    'sha256',
])]
class ReceptionDocument extends Model
{
    use HasFactory;

    public const TYPE_LABELS = [
        'invoice' => 'Factură',
        'delivery_note' => 'Aviz',
        'conformity' => 'Declarație de conformitate',
        'goods_photo' => 'Fotografie marfă',
        'custom' => 'Alt document',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(ReceptionIntake::class, 'reception_intake_id');
    }

    public function reception(): BelongsTo
    {
        return $this->belongsTo(SupplierReception::class, 'supplier_reception_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function label(): string
    {
        return $this->document_type === 'custom' && filled($this->custom_label)
            ? $this->custom_label
            : (self::TYPE_LABELS[$this->document_type] ?? $this->document_type);
    }
}
