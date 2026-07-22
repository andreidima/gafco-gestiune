<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('login_code', 40)->nullable()->unique()->after('name');
            $table->string('phone', 32)->nullable()->after('email');
        });

        DB::table('users')->orderBy('id')->get(['id', 'email'])->each(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update([
                'login_code' => strtolower((string) $user->email) === 'andrei.dima@usm.ro'
                    ? 'ANDREI'
                    : 'USR-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
            ]);
        });

        Schema::create('location_manager', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['location_id', 'user_id']);
        });

        DB::table('locations')
            ->whereNotNull('manager_user_id')
            ->orderBy('id')
            ->get(['id', 'manager_user_id'])
            ->each(function (object $location): void {
                DB::table('location_manager')->insertOrIgnore([
                    'location_id' => $location->id,
                    'user_id' => $location->manager_user_id,
                    'active' => true,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('location_manager');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['login_code']);
            $table->dropColumn(['login_code', 'phone']);
        });
    }
};
