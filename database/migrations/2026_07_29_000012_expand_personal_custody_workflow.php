<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custody_transfers', function (Blueprint $table) {
            $table->foreignId('tracked_asset_id')->nullable()->change();
            $table->foreignId('to_user_id')->nullable()->change();
            $table->string('operation_type', 24)->default('handoff')->after('id');
            $table->foreignId('catalog_item_id')->nullable()->after('tracked_asset_id')->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 3)->nullable()->after('catalog_item_id');
            $table->string('unit', 24)->nullable()->after('quantity');
            $table->foreignId('location_id')->nullable()->after('to_user_id')->constrained()->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->after('location_id')->constrained('users')->nullOnDelete();
            $table->foreignId('manager_approved_by')->nullable()->after('to_approved_at')->constrained('users')->nullOnDelete();
            $table->string('return_condition', 32)->nullable()->after('manager_approved_by');
            $table->text('response_notes')->nullable()->after('return_condition');
            $table->index(['operation_type', 'status'], 'custody_operation_status_idx');
            $table->index(['catalog_item_id', 'location_id', 'status'], 'custody_material_location_status_idx');
        });

        Schema::create('material_custodies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit', 24);
            $table->timestamps();
            $table->unique(['user_id', 'catalog_item_id', 'location_id'], 'material_custody_holder_item_location_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_custodies');

        Schema::table('custody_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_approved_by');
            $table->dropConstrainedForeignId('initiated_by');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('catalog_item_id');
            $table->dropIndex('custody_material_location_status_idx');
            $table->dropIndex('custody_operation_status_idx');
            $table->dropColumn([
                'operation_type',
                'quantity',
                'unit',
                'return_condition',
                'response_notes',
            ]);
            $table->foreignId('tracked_asset_id')->nullable(false)->change();
            $table->foreignId('to_user_id')->nullable(false)->change();
        });
    }
};
