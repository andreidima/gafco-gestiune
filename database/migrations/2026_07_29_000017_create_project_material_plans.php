<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['location_id', 'status']);
        });

        Schema::create('project_material_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->restrictOnDelete();
            $table->decimal('planned_quantity', 14, 3);
            $table->string('unit', 24);
            $table->timestamps();
            $table->unique(['project_id', 'catalog_item_id']);
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('parent_transfer_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('project_material_plans');
        Schema::dropIfExists('projects');
    }
};
