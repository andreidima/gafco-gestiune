<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reception_intakes', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supplier_reception_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('created')->index();
            $table->string('closure_type', 24)->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'status']);
        });

        Schema::create('reception_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reception_intake_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_reception_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 40)->index();
            $table->string('custom_label', 160)->nullable();
            $table->string('original_name');
            $table->string('stored_path')->unique();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->timestamps();
            $table->index(['supplier_reception_id', 'document_type'], 'reception_documents_reception_type');
            $table->index(['reception_intake_id', 'document_type'], 'reception_documents_intake_type');
        });

        Schema::table('supplier_reception_lines', function (Blueprint $table) {
            $table->string('lot_code', 120)->nullable()->after('unit');
            $table->date('expires_at')->nullable()->after('lot_code');
            $table->decimal('unit_price', 16, 4)->nullable()->after('expires_at');
            $table->char('currency', 3)->default('RON')->after('unit_price');
            $table->text('notes')->nullable()->after('currency');
        });

        $this->createPermissions();
    }

    public function down(): void
    {
        Schema::table('supplier_reception_lines', function (Blueprint $table) {
            $table->dropColumn(['lot_code', 'expires_at', 'unit_price', 'currency', 'notes']);
        });

        Schema::dropIfExists('reception_documents');
        Schema::dropIfExists('reception_intakes');

        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('guard_name', 'web')
                ->whereIn('name', [
                    'reception-documents.upload',
                    'reception-details.edit-all',
                    'reception-details.edit-expiration',
                ])
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createPermissions(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            'reception-documents.upload',
            'reception-details.edit-all',
            'reception-details.edit-expiration',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->pluck('id', 'name');
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('id', 'name');

        $this->grant(
            $roleIds,
            $permissionIds,
            'reception-documents.upload',
            ['super-admin', 'admin', 'dispecer', 'gestionar-baza', 'sef-santier', 'muncitor'],
        );
        $this->grant(
            $roleIds,
            $permissionIds,
            'reception-details.edit-all',
            ['super-admin', 'admin'],
        );
        $this->grant(
            $roleIds,
            $permissionIds,
            'reception-details.edit-expiration',
            ['super-admin', 'admin', 'gestionar-baza'],
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grant($roleIds, $permissionIds, string $permission, array $roles): void
    {
        if (! isset($permissionIds[$permission])) {
            return;
        }

        foreach ($roles as $role) {
            if (! isset($roleIds[$role])) {
                continue;
            }

            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionIds[$permission],
                'role_id' => $roleIds[$role],
            ]);
        }
    }
};
