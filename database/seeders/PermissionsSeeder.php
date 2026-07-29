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
            'reception-details.edit-all',
            'reception-details.edit-expiration',
            'accounting.edit-operations',
            'users.impersonate',
            'consumption-reports.correct',
            'suppliers.manage',
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

        foreach (['super-admin', 'admin', 'dispecer', 'contabil'] as $role) {
            Role::findByName($role)->givePermissionTo('suppliers.manage');
        }

        foreach (['super-admin', 'admin'] as $role) {
            Role::findByName($role)->givePermissionTo('users.impersonate');
            Role::findByName($role)->givePermissionTo('consumption-reports.correct');
        }

        foreach (['super-admin', 'admin', 'dispecer', 'gestionar-baza', 'sef-santier', 'muncitor'] as $role) {
            Role::findByName($role)->givePermissionTo('reception-documents.upload');
        }

        foreach (['super-admin', 'admin'] as $role) {
            Role::findByName($role)->givePermissionTo('reception-details.edit-all');
        }

        foreach (['super-admin', 'admin', 'gestionar-baza'] as $role) {
            Role::findByName($role)->givePermissionTo('reception-details.edit-expiration');
        }
    }
}
