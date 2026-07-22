<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custody_transfers', function (Blueprint $table) {
            $table->timestamp('from_approved_at')->nullable()->after('expires_at');
            $table->timestamp('to_approved_at')->nullable()->after('from_approved_at');
            $table->timestamp('rejected_at')->nullable()->after('accepted_at');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->nullOnDelete();
        });

        DB::table('custody_transfers')->orderBy('id')->get()->each(function (object $transfer): void {
            $acceptedAt = $transfer->accepted_at ?: null;
            DB::table('custody_transfers')->where('id', $transfer->id)->update([
                'from_approved_at' => $acceptedAt ?: $transfer->created_at,
                'to_approved_at' => $acceptedAt,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('custody_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['from_approved_at', 'to_approved_at', 'rejected_at']);
        });
    }
};
