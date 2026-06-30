<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_assets', function (Blueprint $table) {
            $table->string('condition', 40)->default('good')->after('status');
            $table->string('photo_path')->nullable()->after('current_custodian_id');
            $table->timestamp('last_verified_at')->nullable()->after('photo_path');
        });

        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->after('driver_id')->constrained('users')->nullOnDelete();
            $table->string('document_number')->nullable()->after('confirmed_by');
            $table->string('document_path')->nullable()->after('document_number');
            $table->timestamp('approved_at')->nullable()->after('assigned_at');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['document_number', 'document_path', 'approved_at']);
        });

        Schema::table('tracked_assets', function (Blueprint $table) {
            $table->dropColumn(['condition', 'photo_path', 'last_verified_at']);
        });
    }
};
