<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('purpose', 24)->default('transfer')->after('type');
            $table->foreignId('parent_transfer_id')->nullable()->after('purpose')->constrained('transfers')->nullOnDelete();
            $table->unsignedInteger('revision')->default(1)->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('received_at');
            $table->timestamp('archived_at')->nullable()->after('cancelled_at');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->string('title');
            $table->string('category', 40)->default('general');
            $table->foreignId('transfer_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('driver_request_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('status', 32)->default('unassigned');
            $table->string('priority', 24)->default('normal');
            $table->timestamp('manager_deadline')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'manager_deadline']);
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('replaced_assignment_id')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('driver_estimate_at')->nullable();
            $table->text('driver_estimate_note')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('reassignment_requested_at')->nullable();
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();
            $table->index(['driver_id', 'status']);
        });

        Schema::create('task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32)->default('observation');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('transfer_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision')->default(1);
            $table->string('scope', 40);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('expected_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('pending');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->unique(['transfer_id', 'revision', 'scope']);
        });

        Schema::create('transfer_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->text('change_summary')->nullable();
            $table->timestamps();
            $table->unique(['transfer_id', 'revision']);
        });

        $now = now();
        DB::table('transfers')->orderBy('id')->get()->each(function (object $transfer) use ($now): void {
            $purpose = $transfer->type === 'site_to_base' ? 'return' : 'transfer';
            DB::table('transfers')->where('id', $transfer->id)->update(['purpose' => $purpose]);

            $taskStatus = match ($transfer->status) {
                'received' => 'completed',
                'in_transit' => 'in_progress',
                'cancelled' => 'cancelled',
                'assigned' => 'pending_acceptance',
                default => $transfer->driver_id ? 'pending_acceptance' : 'unassigned',
            };

            $taskId = DB::table('tasks')->insertGetId([
                'number' => 'TSK-TR-'.$transfer->id,
                'title' => 'Transfer '.$transfer->number,
                'category' => 'transport',
                'transfer_id' => $transfer->id,
                'created_by' => $transfer->requested_by,
                'source_location_id' => $transfer->source_location_id,
                'destination_location_id' => $transfer->destination_location_id,
                'status' => $taskStatus,
                'priority' => 'normal',
                'started_at' => $transfer->dispatched_at,
                'completed_at' => $transfer->received_at,
                'cancelled_at' => $transfer->status === 'cancelled' ? ($transfer->updated_at ?? $now) : null,
                'notes' => $transfer->notes,
                'created_at' => $transfer->created_at ?? $now,
                'updated_at' => $transfer->updated_at ?? $now,
            ]);

            $driverAccepted = in_array($transfer->status, ['in_transit', 'received'], true);
            if ($transfer->driver_id) {
                DB::table('task_assignments')->insert([
                    'task_id' => $taskId,
                    'driver_id' => $transfer->driver_id,
                    'assigned_by' => $transfer->requested_by,
                    'status' => $driverAccepted ? 'accepted' : 'pending',
                    'accepted_at' => $driverAccepted ? ($transfer->assigned_at ?? $transfer->updated_at ?? $now) : null,
                    'created_at' => $transfer->assigned_at ?? $transfer->created_at ?? $now,
                    'updated_at' => $transfer->updated_at ?? $now,
                ]);
            }

            foreach ([
                ['scope' => 'source_manager', 'location_id' => $transfer->source_location_id, 'expected_user_id' => null],
                ['scope' => 'destination_manager', 'location_id' => $transfer->destination_location_id, 'expected_user_id' => null],
                ['scope' => 'driver', 'location_id' => null, 'expected_user_id' => $transfer->driver_id],
            ] as $requirement) {
                $approved = $requirement['scope'] === 'driver' ? $driverAccepted : (bool) $transfer->approved_by;
                DB::table('transfer_approvals')->insert([
                    'transfer_id' => $transfer->id,
                    'revision' => 1,
                    'scope' => $requirement['scope'],
                    'location_id' => $requirement['location_id'],
                    'expected_user_id' => $requirement['expected_user_id'],
                    'decided_by_user_id' => $approved ? ($requirement['scope'] === 'driver' ? $transfer->driver_id : $transfer->approved_by) : null,
                    'status' => $approved ? 'approved' : 'pending',
                    'decided_at' => $approved ? ($transfer->approved_at ?? $transfer->assigned_at ?? $now) : null,
                    'created_at' => $transfer->created_at ?? $now,
                    'updated_at' => $transfer->updated_at ?? $now,
                ]);
            }

            DB::table('transfer_revisions')->insert([
                'transfer_id' => $transfer->id,
                'revision' => 1,
                'changed_by' => $transfer->requested_by,
                'snapshot' => json_encode([
                    'type' => $transfer->type,
                    'purpose' => $purpose,
                    'source_location_id' => $transfer->source_location_id,
                    'destination_location_id' => $transfer->destination_location_id,
                    'driver_id' => $transfer->driver_id,
                    'document_number' => $transfer->document_number,
                    'notes' => $transfer->notes,
                ], JSON_THROW_ON_ERROR),
                'change_summary' => 'Importat din fluxul existent.',
                'created_at' => $transfer->created_at ?? $now,
                'updated_at' => $transfer->updated_at ?? $now,
            ]);
        });

        DB::table('driver_requests')->orderBy('id')->get()->each(function (object $request) use ($now): void {
            $taskStatus = match ($request->status) {
                'closed' => 'completed',
                'in_progress' => 'in_progress',
                'cancelled' => 'cancelled',
                'assigned' => 'pending_acceptance',
                default => 'unassigned',
            };
            $taskId = DB::table('tasks')->insertGetId([
                'number' => 'TSK-DR-'.$request->id,
                'title' => 'Cerere sofer '.$request->number,
                'category' => 'general',
                'driver_request_id' => $request->id,
                'created_by' => $request->requested_by,
                'destination_location_id' => $request->site_id,
                'status' => $taskStatus,
                'priority' => 'normal',
                'manager_deadline' => $request->needed_at,
                'started_at' => $request->status === 'in_progress' ? ($request->assigned_at ?? $request->updated_at) : null,
                'completed_at' => $request->closed_at,
                'cancelled_at' => $request->status === 'cancelled' ? ($request->updated_at ?? $now) : null,
                'notes' => trim(implode("\n", array_filter([
                    $request->pickup_address ? 'Ridicare: '.$request->pickup_address : null,
                    $request->delivery_address ? 'Livrare: '.$request->delivery_address : null,
                    $request->notes,
                ]))),
                'created_at' => $request->created_at ?? $now,
                'updated_at' => $request->updated_at ?? $now,
            ]);

            if ($request->assigned_driver_id) {
                $accepted = in_array($request->status, ['in_progress', 'closed'], true);
                DB::table('task_assignments')->insert([
                    'task_id' => $taskId,
                    'driver_id' => $request->assigned_driver_id,
                    'assigned_by' => $request->requested_by,
                    'status' => $accepted ? 'accepted' : 'pending',
                    'accepted_at' => $accepted ? ($request->assigned_at ?? $request->updated_at ?? $now) : null,
                    'created_at' => $request->assigned_at ?? $request->created_at ?? $now,
                    'updated_at' => $request->updated_at ?? $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_revisions');
        Schema::dropIfExists('transfer_approvals');
        Schema::dropIfExists('task_comments');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');

        Schema::table('transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_transfer_id');
            $table->dropColumn(['purpose', 'revision', 'cancelled_at', 'archived_at']);
        });
    }
};
