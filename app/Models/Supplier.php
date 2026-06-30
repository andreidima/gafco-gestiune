<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'cui', 'email', 'phone', 'active'])]
class Supplier extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
