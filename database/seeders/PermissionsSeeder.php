<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super-admin', 'admin', 'dispecer', 'manager', 'gestionar-baza', 'sef-santier', 'sofer', 'muncitor', 'contabil', 'user'] as $role) {
            Role::findOrCreate($role);
        }

        foreach ([
            'inventory.view',
            'inventory.view-commercial',
            'inventory.manage',
            'reception-documents.upload',
            'accounting.edit-operations',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach (['super-admin', 'admin', 'dispecer', 'manager', 'gestionar-baza', 'sef-santier', 'contabil'] as $role) {
            Role::findByName($role)->givePermissionTo('inventory.view');
        }

        foreach (['super-admin', 'admin', 'dispecer', 'manager', 'contabil'] as $role) {
            Role::findByName($role)->givePermissionTo('inventory.view-commercial');
        }

        foreach (['super-admin', 'admin', 'dispecer'] as $role) {
            Role::findByName($role)->givePermissionTo('inventory.manage');
        }
    }
}
