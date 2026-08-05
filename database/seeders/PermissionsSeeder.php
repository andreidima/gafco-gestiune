<?php

namespace Database\Seeders;

use App\Services\AccessCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = app(AccessCatalog::class);

        foreach ($catalog->roleNames() as $role) {
            Role::findOrCreate($role);
        }

        foreach (array_keys($catalog->seedablePermissions()) as $permission) {
            Permission::findOrCreate($permission);
        }

        foreach ($catalog->roleNames() as $role) {
            Role::findByName($role)->syncPermissions($catalog->permissionsForRole($role));
        }
    }
}
