<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type', 48);
            $table->string('scope_key', 80);
            $table->string('scope_type', 16);
            $table->string('role_name', 80)->nullable();
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('threshold_days');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['alert_type', 'scope_key']);
            $table->index(['scope_type', 'role_name']);
        });

        Schema::create('operational_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type', 48)->index();
            $table->string('fingerprint', 190)->unique();
            $table->string('severity', 16)->default('warning')->index();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('alertable');
            $table->string('title');
            $table->text('message');
            $table->string('url', 500);
            $table->json('metadata')->nullable();
            $table->timestamp('triggered_at')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('last_detected_at')->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
            $table->index(['location_id', 'resolved_at']);
        });

        Schema::create('operational_alert_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('last_notified_severity', 16)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['operational_alert_id', 'user_id']);
            $table->index(['user_id', 'operational_alert_id']);
        });

        $now = now();
        DB::table('alert_rules')->insert([
            [
                'alert_type' => 'lot_expiration',
                'scope_key' => 'system',
                'scope_type' => 'system',
                'role_name' => null,
                'location_id' => null,
                'enabled' => true,
                'threshold_days' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'alert_type' => 'reception_pending',
                'scope_key' => 'system',
                'scope_type' => 'system',
                'role_name' => null,
                'location_id' => null,
                'enabled' => true,
                'threshold_days' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_alert_user');
        Schema::dropIfExists('operational_alerts');
        Schema::dropIfExists('alert_rules');
    }
};
