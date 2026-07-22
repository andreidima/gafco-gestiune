<?php

namespace Database\Seeders;

use App\Models\DriverRequest;
use App\Models\Task;
use App\Models\Transfer;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        Transfer::with('lines')->orderBy('id')->each(function (Transfer $transfer): void {
            $purpose = $transfer->type === 'site_to_base' ? 'return' : 'transfer';
            $transfer->update(['purpose' => $purpose]);
            $task = Task::firstOrCreate(
                ['transfer_id' => $transfer->id],
                [
                    'number' => 'TSK-TR-'.$transfer->id,
                    'title' => ($purpose === 'return' ? 'Retur ' : 'Transfer ').$transfer->number,
                    'category' => 'transport',
                    'created_by' => $transfer->requested_by,
                    'source_location_id' => $transfer->source_location_id,
                    'destination_location_id' => $transfer->destination_location_id,
                    'status' => match ($transfer->status) {
                        'received' => 'completed',
                        'in_transit' => 'in_progress',
                        'cancelled' => 'cancelled',
                        'assigned' => 'pending_acceptance',
                        default => $transfer->driver_id ? 'pending_acceptance' : 'unassigned',
                    },
                    'started_at' => $transfer->dispatched_at,
                    'completed_at' => $transfer->received_at,
                    'priority' => 'normal',
                    'notes' => $transfer->notes,
                ]
            );

            $driverAccepted = in_array($transfer->status, ['in_transit', 'received'], true);
            if ($transfer->driver_id && ! $task->assignments()->exists()) {
                $task->assignments()->create([
                    'driver_id' => $transfer->driver_id,
                    'assigned_by' => $transfer->requested_by,
                    'status' => $driverAccepted ? 'accepted' : 'pending',
                    'accepted_at' => $driverAccepted ? ($transfer->assigned_at ?? now()) : null,
                ]);
            }

            foreach ([
                ['scope' => 'source_manager', 'location_id' => $transfer->source_location_id, 'expected_user_id' => null, 'approved' => (bool) $transfer->approved_by],
                ['scope' => 'destination_manager', 'location_id' => $transfer->destination_location_id, 'expected_user_id' => null, 'approved' => (bool) $transfer->approved_by],
                ['scope' => 'driver', 'location_id' => null, 'expected_user_id' => $transfer->driver_id, 'approved' => $driverAccepted],
            ] as $approval) {
                $transfer->approvals()->updateOrCreate(
                    ['revision' => 1, 'scope' => $approval['scope']],
                    [
                        'location_id' => $approval['location_id'],
                        'expected_user_id' => $approval['expected_user_id'],
                        'status' => $approval['approved'] ? 'approved' : 'pending',
                        'decided_by_user_id' => $approval['approved'] ? ($approval['scope'] === 'driver' ? $transfer->driver_id : $transfer->approved_by) : null,
                        'decided_at' => $approval['approved'] ? ($transfer->approved_at ?? $transfer->assigned_at ?? now()) : null,
                    ]
                );
            }

            $transfer->revisions()->firstOrCreate(
                ['revision' => 1],
                [
                    'changed_by' => $transfer->requested_by,
                    'snapshot' => [
                        'purpose' => $purpose,
                        'source_location_id' => $transfer->source_location_id,
                        'destination_location_id' => $transfer->destination_location_id,
                        'driver_id' => $transfer->driver_id,
                        'lines' => $transfer->lines->map->only(['catalog_item_id', 'tracked_asset_id', 'quantity', 'unit'])->values()->all(),
                    ],
                    'change_summary' => 'Date demo importate.',
                ]
            );
        });

        DriverRequest::orderBy('id')->each(function (DriverRequest $request): void {
            $task = Task::firstOrCreate(
                ['driver_request_id' => $request->id],
                [
                    'number' => 'TSK-DR-'.$request->id,
                    'title' => 'Cerere sofer '.$request->number,
                    'category' => 'general',
                    'created_by' => $request->requested_by,
                    'destination_location_id' => $request->site_id,
                    'status' => match ($request->status) {
                        'closed' => 'completed',
                        'in_progress' => 'in_progress',
                        'cancelled' => 'cancelled',
                        'assigned' => 'pending_acceptance',
                        default => 'unassigned',
                    },
                    'manager_deadline' => $request->needed_at,
                    'completed_at' => $request->closed_at,
                    'priority' => 'normal',
                    'notes' => $request->notes,
                ]
            );
            if ($request->assigned_driver_id && ! $task->assignments()->exists()) {
                $accepted = in_array($request->status, ['in_progress', 'closed'], true);
                $task->assignments()->create([
                    'driver_id' => $request->assigned_driver_id,
                    'assigned_by' => $request->requested_by,
                    'status' => $accepted ? 'accepted' : 'pending',
                    'accepted_at' => $accepted ? ($request->assigned_at ?? now()) : null,
                ]);
            }
        });
    }
}
