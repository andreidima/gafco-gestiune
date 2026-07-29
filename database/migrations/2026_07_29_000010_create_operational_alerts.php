<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alert_rules')) {
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
        }

        if (! Schema::hasTable('operational_alerts')) {
            Schema::create('operational_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('alert_type', 48)->index();
                $table->string('fingerprint', 190)->unique();
                $table->string('severity', 16)->default('warning')->index();
                $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
                $table->string('alertable_type', 120)->nullable();
                $table->unsignedBigInteger('alertable_id')->nullable();
                $table->index(['alertable_type', 'alertable_id']);
                $table->string('title');
                $table->text('message');
                $table->string('url', 500);
                $table->json('metadata')->nullable();
                $table->dateTime('triggered_at')->index();
                $table->dateTime('due_at')->nullable()->index();
                $table->dateTime('last_detected_at')->index();
                $table->dateTime('resolved_at')->nullable()->index();
                $table->timestamps();
                $table->index(['location_id', 'resolved_at']);
            });
        }

        if (! Schema::hasTable('operational_alert_user')) {
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
        }

        $now = now();
        foreach ([
            'lot_expiration' => 30,
            'reception_pending' => 2,
        ] as $alertType => $thresholdDays) {
            DB::table('alert_rules')->updateOrInsert(
                [
                    'alert_type' => $alertType,
                    'scope_key' => 'system',
                ],
                [
                    'scope_type' => 'system',
                    'role_name' => null,
                    'location_id' => null,
                    'enabled' => true,
                    'threshold_days' => $thresholdDays,
                    'changed_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_alert_user');
        Schema::dropIfExists('operational_alerts');
        Schema::dropIfExists('alert_rules');
    }
};
