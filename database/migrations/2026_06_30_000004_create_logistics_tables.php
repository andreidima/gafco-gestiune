<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('address')->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cui', 32)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);
            $table->string('tracking_type', 32);
            $table->string('sku', 80)->nullable()->unique();
            $table->string('barcode', 120)->nullable()->unique();
            $table->string('name');
            $table->string('unit', 24)->default('buc');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('tracked_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->string('asset_code', 80)->unique();
            $table->string('qr_code', 120)->unique();
            $table->string('serial_number')->nullable();
            $table->string('status', 32)->default('available');
            $table->foreignId('current_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('current_custodian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['location_id', 'catalog_item_id']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('type', 40);
            $table->string('status', 32)->default('draft');
            $table->foreignId('source_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracked_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit', 24)->default('buc');
            $table->string('received_status', 32)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('driver_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('site_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->timestamp('needed_at')->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('delivery_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_receptions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 32)->default('aviz');
            $table->string('document_number')->nullable();
            $table->string('document_photo_path')->nullable();
            $table->string('status', 32)->default('posted');
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_reception_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_reception_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracked_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 3)->default(1);
            $table->string('unit', 24)->default('buc');
            $table->timestamps();
        });

        Schema::create('custody_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('qr_token', 120)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custody_transfers');
        Schema::dropIfExists('supplier_reception_lines');
        Schema::dropIfExists('supplier_receptions');
        Schema::dropIfExists('driver_requests');
        Schema::dropIfExists('transfer_lines');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('tracked_assets');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('locations');
    }
};
