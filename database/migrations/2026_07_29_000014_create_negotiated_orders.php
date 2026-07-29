<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('negotiated_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('status', 32)->default('created')->index();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('currency', 3)->default('RON');
            $table->text('notes')->nullable();
            $table->string('closure_type', 32)->nullable();
            $table->text('closure_reason')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('negotiated_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('negotiated_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 24);
            $table->decimal('unit_price', 14, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('supplier_receptions', function (Blueprint $table) {
            $table->foreignId('negotiated_order_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_receptions', function (Blueprint $table) {
            $table->dropForeign(['negotiated_order_id']);
            $table->dropUnique(['negotiated_order_id']);
            $table->dropColumn('negotiated_order_id');
        });

        Schema::dropIfExists('negotiated_order_lines');
        Schema::dropIfExists('negotiated_orders');
    }
};
