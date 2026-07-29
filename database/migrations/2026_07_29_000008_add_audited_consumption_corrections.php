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
        Schema::table('consumption_reports', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1)->after('status');
            $table->foreignId('modified_by')->nullable()->after('reported_by')->constrained('users')->nullOnDelete();
            $table->timestamp('modified_at')->nullable()->after('reported_at');
            $table->text('correction_reason')->nullable()->after('notes');
        });

        Schema::table('consumption_report_lines', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1)->after('consumption_report_id');
            $table->timestamp('superseded_at')->nullable()->after('notes')->index();
        });

        Schema::create('consumption_report_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumption_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('before_data');
            $table->json('after_data');
            $table->text('reason');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
            $table->unique(['consumption_report_id', 'revision'], 'consumption_report_revision_unique');
        });

        $this->createCorrectionPermission();
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            DB::table('permissions')
                ->where('name', 'consumption-reports.correct')
                ->where('guard_name', 'web')
                ->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::dropIfExists('consumption_report_revisions');

        Schema::table('consumption_report_lines', function (Blueprint $table) {
            $table->dropColumn(['revision', 'superseded_at']);
        });

        Schema::table('consumption_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modified_by');
            $table->dropColumn(['revision', 'modified_at', 'correction_reason']);
        });
    }

    private function createCorrectionPermission(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'consumption-reports.correct',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')
            ->where('name', 'consumption-reports.correct')
            ->where('guard_name', 'web')
            ->value('id');

        DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['super-admin', 'admin'])
            ->pluck('id')
            ->each(fn ($roleId) => DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
