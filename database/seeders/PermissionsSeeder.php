<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super-admin', 'admin', 'dispecer', 'gestionar-baza', 'sef-santier', 'sofer', 'muncitor', 'contabil', 'user'] as $role) {
            Role::findOrCreate($role);
        }
    }
}
