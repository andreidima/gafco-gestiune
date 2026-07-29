<?php

use App\Models\Supplier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('normalized_cui', 32)->nullable()->unique()->after('cui');
            $table->string('registration_number', 80)->nullable()->after('normalized_cui');
            $table->text('address')->nullable()->after('registration_number');
            $table->string('contact_person')->nullable()->after('address');
            $table->text('notes')->nullable()->after('phone');
        });

        $suppliers = DB::table('suppliers')
            ->whereNotNull('cui')
            ->orderBy('id')
            ->get(['id', 'cui']);

        $uniqueCuis = $suppliers
            ->groupBy(fn ($supplier) => Supplier::normalizeCui($supplier->cui))
            ->filter(fn ($group, $normalizedCui) => filled($normalizedCui) && $group->count() === 1);

        foreach ($uniqueCuis as $normalizedCui => $group) {
            DB::table('suppliers')
                ->where('id', $group->first()->id)
                ->update([
                    'cui' => Supplier::formatCui($group->first()->cui),
                    'normalized_cui' => $normalizedCui,
                ]);
        }

        $this->addPermission();
    }

    public function down(): void
    {
        $this->removePermission();

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['normalized_cui']);
            $table->dropColumn([
                'normalized_cui',
                'registration_number',
                'address',
                'contact_person',
                'notes',
            ]);
        });
    }

    private function addPermission(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $timestamp = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'suppliers.manage',
            'guard_name' => 'web',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'suppliers.manage')
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['super-admin', 'admin', 'dispecer', 'contabil'])
            ->pluck('id')
            ->each(fn ($roleId) => DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function removePermission(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'suppliers.manage')
            ->where('guard_name', 'web')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('model_has_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
