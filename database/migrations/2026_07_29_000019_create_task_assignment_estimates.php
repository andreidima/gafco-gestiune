<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignment_estimates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('estimated_at');
            $table->text('note')->nullable();
            $table->dateTime('correctable_until')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['task_assignment_id', 'created_at'], 'task_assignment_estimates_history_index');
        });

        if (DB::connection()->pretending()) {
            return;
        }

        DB::table('task_assignments')
            ->whereNotNull('driver_estimate_at')
            ->orderBy('id')
            ->chunkById(250, function ($assignments): void {
                $rows = $assignments->map(function ($assignment): array {
                    $recordedAt = $assignment->updated_at ?? $assignment->created_at ?? now();

                    return [
                        'task_assignment_id' => $assignment->id,
                        'driver_id' => $assignment->driver_id,
                        'estimated_at' => $assignment->driver_estimate_at,
                        'note' => $assignment->driver_estimate_note,
                        'correctable_until' => null,
                        'created_at' => $recordedAt,
                        'updated_at' => $recordedAt,
                    ];
                })->all();

                if ($rows !== []) {
                    DB::table('task_assignment_estimates')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignment_estimates');
    }
};
