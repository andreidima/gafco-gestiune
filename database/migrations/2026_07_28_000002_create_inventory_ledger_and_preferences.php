<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_reception_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 160)->nullable()->unique();
            $table->string('lot_code', 120)->nullable()->index();
            $table->string('document_number')->nullable()->index();
            $table->date('document_date')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->date('expires_at')->nullable()->index();
            $table->decimal('unit_price', 16, 4)->nullable();
            $table->char('currency', 3)->default('RON');
            $table->boolean('is_opening_balance')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_lot_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['inventory_lot_id', 'location_id']);
            $table->index(['location_id', 'quantity']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->uuid('group_uuid')->index();
            $table->foreignId('inventory_lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('movement_type', 40)->index();
            $table->string('reference_type', 60)->nullable()->index();
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->unsignedBigInteger('reference_line_id')->nullable()->index();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['catalog_item_id', 'location_id', 'occurred_at'], 'stock_movements_item_location_date');
            $table->index(['reference_type', 'reference_id', 'reference_line_id'], 'stock_movements_reference');
        });

        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 120);
            $table->json('value');
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });

        $this->createRolesAndPermissions();
        $this->backfillOpeningBalances();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_lot_balances');
        Schema::dropIfExists('inventory_lots');

        if (Schema::hasTable('roles')) {
            DB::table('roles')->where('name', 'manager')->where('guard_name', 'web')->delete();
        }

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')->whereIn('name', [
                'inventory.view',
                'inventory.view-commercial',
                'inventory.manage',
                'reception-documents.upload',
                'accounting.edit-operations',
            ])->where('guard_name', 'web')->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createRolesAndPermissions(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        DB::table('roles')->insertOrIgnore([
            'name' => 'manager',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            'inventory.view',
            'inventory.view-commercial',
            'inventory.manage',
            'reception-documents.upload',
            'accounting.edit-operations',
        ] as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'super-admin', 'admin', 'dispecer', 'manager',
                'sef-santier', 'gestionar-baza', 'contabil',
            ])
            ->pluck('id', 'name');
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'inventory.view',
                'inventory.view-commercial',
                'inventory.manage',
            ])
            ->pluck('id', 'name');

        foreach ($roleIds as $roleName => $roleId) {
            if (isset($permissionIds['inventory.view'])) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionIds['inventory.view'],
                    'role_id' => $roleId,
                ]);
            }

            if (in_array($roleName, ['super-admin', 'admin', 'dispecer', 'manager', 'contabil'], true)
                && isset($permissionIds['inventory.view-commercial'])) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionIds['inventory.view-commercial'],
                    'role_id' => $roleId,
                ]);
            }

            if (in_array($roleName, ['super-admin', 'admin', 'dispecer'], true)
                && isset($permissionIds['inventory.manage'])) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionIds['inventory.manage'],
                    'role_id' => $roleId,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function backfillOpeningBalances(): void
    {
        if (! Schema::hasTable('stock_levels')) {
            return;
        }

        $now = now();
        DB::table('stock_levels')
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->get()
            ->each(function (object $stock) use ($now): void {
                $lotId = DB::table('inventory_lots')->insertGetId([
                    'catalog_item_id' => $stock->catalog_item_id,
                    'source_key' => 'opening-stock-level:'.$stock->id,
                    'received_at' => null,
                    'currency' => 'RON',
                    'is_opening_balance' => true,
                    'notes' => 'Sold inițial preluat la activarea fișei de inventar.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('inventory_lot_balances')->insert([
                    'inventory_lot_id' => $lotId,
                    'location_id' => $stock->location_id,
                    'quantity' => $stock->quantity,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('stock_movements')->insert([
                    'group_uuid' => (string) Str::uuid(),
                    'inventory_lot_id' => $lotId,
                    'catalog_item_id' => $stock->catalog_item_id,
                    'location_id' => $stock->location_id,
                    'quantity' => $stock->quantity,
                    'movement_type' => 'opening_balance',
                    'reference_type' => 'stock_level',
                    'reference_id' => $stock->id,
                    'occurred_at' => $now,
                    'notes' => 'Sold inițial migrat din stocul existent.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
};
