<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'cui',
    'normalized_cui',
    'registration_number',
    'address',
    'contact_person',
    'email',
    'phone',
    'notes',
    'active',
])]
class Supplier extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function receptions(): HasMany
    {
        return $this->hasMany(SupplierReception::class);
    }

    public function negotiatedOrders(): HasMany
    {
        return $this->hasMany(NegotiatedOrder::class);
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class);
    }

    public static function formatCui(?string $cui): ?string
    {
        $formatted = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(trim((string) $cui)));

        return filled($formatted) ? $formatted : null;
    }

    public static function normalizeCui(?string $cui): ?string
    {
        $normalized = self::formatCui($cui);

        if ($normalized && str_starts_with($normalized, 'RO')) {
            $normalized = substr($normalized, 2);
        }

        return filled($normalized) ? $normalized : null;
    }
}
