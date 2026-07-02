<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_reports', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('posted');
            $table->timestamp('reported_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('consumption_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumption_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('unit', 24)->default('buc');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->boolean('received_with_discrepancy')->default(false)->after('received_at');
            $table->text('discrepancy_notes')->nullable()->after('received_with_discrepancy');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['received_with_discrepancy', 'discrepancy_notes']);
        });

        Schema::dropIfExists('consumption_report_lines');
        Schema::dropIfExists('consumption_reports');
    }
};
