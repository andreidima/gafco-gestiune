<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\Task;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use App\Models\TransferLine;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TransferWorkflowService
{
    public function __construct(private readonly StockLedgerService $ledger) {}

    public function create(array $data, User $actor, TaskWorkflowService $tasks): Transfer
    {
        return DB::transaction(function () use ($data, $actor, $tasks): Transfer {
            $this->authorizeLocationScope($actor, (int) $data['source_location_id'], (int) $data['destination_location_id']);
            $this->validateReturnParent($data, $actor);
            $this->validateLinesAtSource($data['lines'], (int) $data['source_location_id']);
            $source = Location::findOrFail($data['source_location_id']);
            $destination = Location::findOrFail($data['destination_location_id']);
            $purpose = $data['purpose'];
            $transfer = Transfer::create([
                'number' => ($purpose === 'return' ? 'RT-' : 'TR-').now()->format('Ymd-His').'-'.strtoupper(str()->random(3)),
                'type' => $this->direction($source, $destination),
                'purpose' => $purpose,
                'parent_transfer_id' => $data['parent_transfer_id'] ?? null,
                'revision' => 1,
                'status' => 'pending_approval',
                'source_location_id' => $source->id,
                'destination_location_id' => $destination->id,
                'requested_by' => $actor->id,
                'document_number' => $data['document_number'] ?? null,
                'requested_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceLines($transfer, $data['lines']);

            $task = Task::create([
                'number' => 'TSK-TR-'.$transfer->id,
                'title' => ($purpose === 'return' ? 'Retur ' : 'Transfer ').$transfer->number,
                'category' => 'transport',
                'transfer_id' => $transfer->id,
                'created_by' => $actor->id,
                'source_location_id' => $source->id,
                'destination_location_id' => $destination->id,
                'status' => 'unassigned',
                'priority' => $data['priority'] ?? 'normal',
                'manager_deadline' => $data['manager_deadline'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            if (! empty($data['driver_id'])) {
                $tasks->assign($task, $this->activeDriver((int) $data['driver_id']), $actor);
                $transfer->refresh();
            }

            $this->createApprovalRequirements($transfer, $actor);
            $this->recordRevision($transfer, $actor, 'Transfer creat.');

            return $transfer;
        });
    }

    public function revise(Transfer $transfer, array $data, User $actor, TaskWorkflowService $tasks): void
    {
        DB::transaction(function () use ($transfer, $data, $actor, $tasks): void {
            Task::query()->where('transfer_id', $transfer->id)->lockForUpdate()->first();
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (in_array($transfer->status, ['in_transit', 'received', 'cancelled'], true) || $transfer->archived_at !== null) {
                throw ValidationException::withMessages([
                    'transfer' => 'Transferul nu mai poate fi modificat dupa inceperea transportului.',
                ]);
            }

            $this->authorizeLocationScope($actor, (int) $data['source_location_id'], (int) $data['destination_location_id']);
            $this->validateReturnParent($data, $actor, $transfer);
            $this->validateLinesAtSource($data['lines'], (int) $data['source_location_id'], $transfer->id);
            $transfer->loadMissing(['lines', 'task.currentAssignment']);
            $source = Location::findOrFail($data['source_location_id']);
            $destination = Location::findOrFail($data['destination_location_id']);
            $oldLineSignature = $this->lineSignature($transfer->lines->toArray());
            $newLineSignature = $this->lineSignature($data['lines']);
            $deadlineChanged = ($transfer->task?->manager_deadline?->timestamp)
                !== (! empty($data['manager_deadline']) ? Carbon::parse($data['manager_deadline'])->timestamp : null);
            $currentDriverId = $transfer->task?->currentAssignment?->driver_id
                ? (int) $transfer->task->currentAssignment->driver_id
                : null;
            $newDriverId = ! empty($data['driver_id']) ? (int) $data['driver_id'] : null;
            $driverChanged = $newDriverId !== $currentDriverId;
            $criticalChanged = (int) $transfer->source_location_id !== (int) $source->id
                || (int) $transfer->destination_location_id !== (int) $destination->id
                || $oldLineSignature !== $newLineSignature
                || $deadlineChanged
                || $driverChanged
                || $transfer->task?->priority !== ($data['priority'] ?? $transfer->task?->priority)
                || $transfer->document_number !== (($data['document_number'] ?? null) ?: null)
                || $transfer->notes !== (($data['notes'] ?? null) ?: null)
                || $transfer->purpose !== $data['purpose']
                || (int) ($transfer->parent_transfer_id ?? 0) !== (int) ($data['parent_transfer_id'] ?? 0);

            $updates = [
                'type' => $this->direction($source, $destination),
                'purpose' => $data['purpose'],
                'parent_transfer_id' => $data['parent_transfer_id'] ?? null,
                'source_location_id' => $source->id,
                'destination_location_id' => $destination->id,
                'document_number' => $data['document_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'revision' => $transfer->revision + 1,
            ];
            if ($criticalChanged) {
                $updates['status'] = 'pending_approval';
            }
            $previousRevision = $transfer->revision;
            $transfer->update($updates);
            $this->replaceLines($transfer, $data['lines']);
            $transfer->task?->update([
                'title' => ($data['purpose'] === 'return' ? 'Retur ' : 'Transfer ').$transfer->number,
                'source_location_id' => $source->id,
                'destination_location_id' => $destination->id,
                'manager_deadline' => $data['manager_deadline'] ?? null,
                'priority' => $data['priority'] ?? $transfer->task->priority,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($criticalChanged) {
                $this->createApprovalRequirements($transfer->refresh(), $actor);
            } else {
                $this->copyApprovalRequirements($transfer->refresh(), $previousRevision);
            }

            if ($driverChanged && $newDriverId !== null) {
                $tasks->assign($transfer->task, $this->activeDriver($newDriverId), $actor);
            } elseif ($driverChanged) {
                $tasks->unassign($transfer->task, $actor);
            }

            $this->recordRevision($transfer->refresh(), $actor, $criticalChanged ? 'Date esentiale modificate; aprobarile au fost relansate.' : 'Detalii actualizate.');
        });
    }

    public function decide(TransferApproval $approval, User $actor, string $decision, ?string $note): void
    {
        DB::transaction(function () use ($approval, $actor, $decision, $note): void {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($approval->transfer_id);
            $approval = TransferApproval::query()->lockForUpdate()->findOrFail($approval->id);
            $approval->loadMissing('location.activeManagers');
            abort_if($approval->scope === 'driver', 403);
            abort_unless($approval->revision === $transfer->revision, 403);
            if ($approval->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Aceasta aprobare a primit deja o decizie.']);
            }
            if (in_array($transfer->status, ['received', 'cancelled'], true) || $transfer->archived_at !== null) {
                throw ValidationException::withMessages(['decision' => 'Aprobarile unui transfer inchis nu mai pot fi modificate.']);
            }

            $allowed = $actor->isOperationsAdmin()
                || ($approval->location && $approval->location->activeManagers->contains($actor));
            abort_unless($allowed, 403);

            $approval->update([
                'status' => $decision,
                'decided_by_user_id' => $actor->id,
                'decision_note' => $note,
                'decided_at' => now(),
            ]);

            if ($decision === 'approved' && ! $transfer->currentApprovals()->where('status', '!=', 'approved')->exists()) {
                if ($transfer->status === 'pending_approval') {
                    $transfer->update([
                        'status' => 'approved',
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ]);
                }
            }
        });
    }

    public function receive(Transfer $transfer, User $actor, ?string $discrepancyNotes = null): void
    {
        DB::transaction(function () use ($transfer, $actor, $discrepancyNotes): void {
            Task::query()->where('transfer_id', $transfer->id)->lockForUpdate()->first();
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            abort_unless(
                $actor->isOperationsAdmin()
                    || (! $actor->usesDriverWorkspace()
                        && $actor->hasAnyRole(['sef-santier', 'gestionar-baza'])
                        && $actor->activeManagedLocations()
                            ->where('locations.id', $transfer->destination_location_id)
                            ->exists()),
                403
            );
            if ($transfer->status === 'received') {
                return;
            }
            if ($transfer->status !== 'in_transit' || $transfer->archived_at !== null) {
                throw ValidationException::withMessages([
                    'transfer' => 'Primirea poate fi confirmata numai pentru un transfer aflat in tranzit.',
                ]);
            }
            $transfer->load(['lines.trackedAsset']);
            foreach ($transfer->lines as $line) {
                if ($line->tracked_asset_id) {
                    $asset = TrackedAsset::query()->lockForUpdate()->findOrFail($line->tracked_asset_id);
                    $reservedElsewhere = TransferLine::query()
                        ->where('tracked_asset_id', $asset->id)
                        ->where('transfer_id', '!=', $transfer->id)
                        ->whereHas('transfer', fn ($query) => $query
                            ->whereNull('archived_at')
                            ->whereNotIn('status', ['received', 'cancelled']))
                        ->exists();
                    if ($reservedElsewhere
                        || (int) $asset->current_location_id !== (int) $transfer->source_location_id
                        || $asset->status !== 'in_transfer') {
                        throw ValidationException::withMessages([
                            'transfer' => 'Un echipament din transfer nu mai este disponibil exclusiv pentru aceasta operatiune.',
                        ]);
                    }
                    $asset->update([
                        'status' => $asset->current_custodian_id ? 'in_use' : 'available',
                        'current_location_id' => $transfer->destination_location_id,
                        'last_verified_at' => now(),
                    ]);
                } else {
                    $this->ledger->postTransfer(
                        $line,
                        (int) $transfer->source_location_id,
                        (int) $transfer->destination_location_id,
                        $actor,
                    );
                }
                $line->update(['received_status' => 'received']);
            }
            $transfer->update([
                'status' => 'received',
                'received_at' => now(),
                'confirmed_by' => $actor->id,
                'received_with_discrepancy' => filled($discrepancyNotes),
                'discrepancy_notes' => $discrepancyNotes,
            ]);
            $transfer->task?->update(['status' => 'completed', 'completed_at' => now()]);
        });
    }

    public function cancel(Transfer $transfer, User $actor, string $note): void
    {
        DB::transaction(function () use ($transfer, $actor, $note): void {
            Task::query()->where('transfer_id', $transfer->id)->lockForUpdate()->first();
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (in_array($transfer->status, ['received', 'cancelled'], true) || $transfer->archived_at !== null) {
                throw ValidationException::withMessages(['transfer' => 'Transferul nu mai poate fi anulat.']);
            }
            $transfer->load('lines.trackedAsset');
            $transfer->update(['status' => 'cancelled', 'cancelled_at' => now(), 'notes' => trim($transfer->notes."\nAnulare: ".$note)]);
            $transfer->task?->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $transfer->lines->each(function ($line): void {
                if ($line->trackedAsset?->status === 'in_transfer') {
                    $line->trackedAsset->update(['status' => $line->trackedAsset->current_custodian_id ? 'in_use' : 'available']);
                }
            });
            activity()->performedOn($transfer)->causedBy($actor)->withProperties(['reason' => $note])->log('Transfer anulat');
        });
    }

    public function archive(Transfer $transfer, User $actor): void
    {
        DB::transaction(function () use ($transfer, $actor): void {
            Task::query()->where('transfer_id', $transfer->id)->lockForUpdate()->first();
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if (! in_array($transfer->status, ['received', 'cancelled'], true) || $transfer->archived_at !== null) {
                throw ValidationException::withMessages(['transfer' => 'Numai transferurile inchise pot fi arhivate.']);
            }
            $transfer->update(['archived_at' => now()]);
            $transfer->task?->update(['status' => 'archived', 'archived_at' => now()]);
            activity()->performedOn($transfer)->causedBy($actor)->log('Transfer arhivat');
        });
    }

    private function createApprovalRequirements(Transfer $transfer, User $actor): void
    {
        $transfer->loadMissing(['sourceLocation.activeManagers', 'destinationLocation.activeManagers', 'task.currentAssignment.driver']);
        $requirements = [
            ['scope' => 'source_manager', 'location' => $transfer->sourceLocation, 'expected' => null],
            ['scope' => 'destination_manager', 'location' => $transfer->destinationLocation, 'expected' => null],
            ['scope' => 'driver', 'location' => null, 'expected' => $transfer->task?->currentAssignment?->driver],
        ];

        foreach ($requirements as $requirement) {
            $autoApproved = $requirement['location']?->activeManagers->contains($actor)
                || ($requirement['scope'] === 'driver' && $requirement['expected']?->is($actor));
            $approval = TransferApproval::updateOrCreate(
                ['transfer_id' => $transfer->id, 'revision' => $transfer->revision, 'scope' => $requirement['scope']],
                [
                    'location_id' => $requirement['location']?->id,
                    'expected_user_id' => $requirement['expected']?->id,
                    'status' => $autoApproved ? 'approved' : 'pending',
                    'decided_by_user_id' => $autoApproved ? $actor->id : null,
                    'decided_at' => $autoApproved ? now() : null,
                    'decision_note' => $autoApproved ? 'Aprobare automata pentru initiator.' : null,
                ]
            );

            $recipients = $requirement['location']?->activeManagers ?? collect([$requirement['expected']])->filter();
            Notification::send(
                $recipients->reject(fn (User $user) => $user->is($actor)),
                new WorkflowNotification(
                    $autoApproved ? 'Transfer initiat' : 'Aprobare necesara',
                    $autoApproved
                        ? $transfer->number.' a fost initiat si aprobarea locatiei este deja indeplinita.'
                        : $transfer->number.' necesita aprobarea ta.',
                    route('transfers.show', $transfer)
                )
            );
        }
    }

    private function copyApprovalRequirements(Transfer $transfer, int $previousRevision): void
    {
        $transfer->approvals()->where('revision', $previousRevision)->get()->each(function (TransferApproval $approval) use ($transfer): void {
            $transfer->approvals()->create([
                'revision' => $transfer->revision,
                'scope' => $approval->scope,
                'location_id' => $approval->location_id,
                'expected_user_id' => $approval->expected_user_id,
                'decided_by_user_id' => $approval->decided_by_user_id,
                'status' => $approval->status,
                'decision_note' => $approval->decision_note,
                'decided_at' => $approval->decided_at,
            ]);
        });
    }

    private function recordRevision(Transfer $transfer, User $actor, string $summary): void
    {
        $transfer->load(['lines', 'task.currentAssignment']);
        $transfer->revisions()->updateOrCreate(
            ['revision' => $transfer->revision],
            [
                'changed_by' => $actor->id,
                'snapshot' => [
                    'purpose' => $transfer->purpose,
                    'parent_transfer_id' => $transfer->parent_transfer_id,
                    'source_location_id' => $transfer->source_location_id,
                    'destination_location_id' => $transfer->destination_location_id,
                    'driver_id' => $transfer->task?->currentAssignment?->driver_id,
                    'manager_deadline' => $transfer->task?->manager_deadline?->toIso8601String(),
                    'priority' => $transfer->task?->priority,
                    'document_number' => $transfer->document_number,
                    'notes' => $transfer->notes,
                    'lines' => $transfer->lines->map->only(['catalog_item_id', 'tracked_asset_id', 'quantity', 'unit'])->values()->all(),
                ],
                'change_summary' => $summary,
            ]
        );
    }

    private function replaceLines(Transfer $transfer, array $lines): void
    {
        $transfer->lines()->delete();
        foreach ($lines as $line) {
            $asset = ! empty($line['tracked_asset_id']) ? TrackedAsset::with('catalogItem')->findOrFail($line['tracked_asset_id']) : null;
            $item = $asset?->catalogItem ?? CatalogItem::findOrFail($line['catalog_item_id']);
            $transfer->lines()->create([
                'catalog_item_id' => $item->id,
                'tracked_asset_id' => $asset?->id,
                'quantity' => $asset ? 1 : $line['quantity'],
                'unit' => $item->unit,
            ]);
        }
    }

    private function validateLinesAtSource(array $lines, int $sourceLocationId, ?int $ignoreTransferId = null): void
    {
        $assetLineIndexes = [];
        $materialTotals = [];
        $materialLineIndexes = [];

        foreach ($lines as $index => $line) {
            $catalogItemId = ! empty($line['catalog_item_id']) ? (int) $line['catalog_item_id'] : null;
            $trackedAssetId = ! empty($line['tracked_asset_id']) ? (int) $line['tracked_asset_id'] : null;
            if (($catalogItemId === null) === ($trackedAssetId === null)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.catalog_item_id" => 'Alege fie un material, fie un echipament, nu ambele.',
                ]);
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity" => 'Cantitatea trebuie sa fie mai mare decat zero.']);
            }

            if ($trackedAssetId !== null) {
                if (array_key_exists($trackedAssetId, $assetLineIndexes)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.tracked_asset_id" => 'Acelasi echipament nu poate aparea de doua ori in transfer.',
                    ]);
                }
                $assetLineIndexes[$trackedAssetId] = $index;

                continue;
            }

            $materialTotals[$catalogItemId] = ($materialTotals[$catalogItemId] ?? 0) + $quantity;
            $materialLineIndexes[$catalogItemId] ??= $index;
        }

        if ($assetLineIndexes !== []) {
            $assets = TrackedAsset::query()
                ->whereIn('id', array_keys($assetLineIndexes))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $reservedAssetIds = TransferLine::query()
                ->whereIn('tracked_asset_id', array_keys($assetLineIndexes))
                ->when($ignoreTransferId, fn ($query) => $query->where('transfer_id', '!=', $ignoreTransferId))
                ->whereHas('transfer', fn ($query) => $query
                    ->whereNull('archived_at')
                    ->whereNotIn('status', ['received', 'cancelled']))
                ->pluck('tracked_asset_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            foreach ($assetLineIndexes as $assetId => $index) {
                $asset = $assets->get($assetId);
                if (! $asset
                    || (int) $asset->current_location_id !== $sourceLocationId
                    || ! in_array($asset->status, ['available', 'in_use'], true)
                    || in_array($assetId, $reservedAssetIds, true)) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.tracked_asset_id" => 'Echipamentul nu este disponibil in locatia sursa.',
                    ]);
                }
            }
        }

        if ($materialTotals !== []) {
            $stocks = StockLevel::query()
                ->where('location_id', $sourceLocationId)
                ->whereIn('catalog_item_id', array_keys($materialTotals))
                ->pluck('quantity', 'catalog_item_id');
            foreach ($materialTotals as $catalogItemId => $quantity) {
                if ((float) ($stocks[$catalogItemId] ?? 0) < $quantity) {
                    $index = $materialLineIndexes[$catalogItemId];
                    throw ValidationException::withMessages([
                        "lines.{$index}.quantity" => 'Cantitatea totala solicitata depaseste stocul disponibil in sursa.',
                    ]);
                }
            }
        }
    }

    private function authorizeLocationScope(User $actor, int $sourceLocationId, int $destinationLocationId): void
    {
        if ($actor->isOperationsAdmin()) {
            return;
        }

        $allowedCount = $actor->activeManagedLocations()
            ->whereIn('locations.id', [$sourceLocationId, $destinationLocationId])
            ->count();
        abort_unless($allowedCount === count(array_unique([$sourceLocationId, $destinationLocationId])), 403);
    }

    private function validateReturnParent(array $data, User $actor, ?Transfer $transfer = null): void
    {
        if (($data['purpose'] ?? null) !== 'return') {
            if (! empty($data['parent_transfer_id'])) {
                throw ValidationException::withMessages([
                    'parent_transfer_id' => 'Un transfer obisnuit nu poate avea un transfer initial de retur.',
                ]);
            }

            return;
        }

        if (empty($data['parent_transfer_id'])) {
            throw ValidationException::withMessages(['parent_transfer_id' => 'Selecteaza transferul initial pentru retur.']);
        }

        $parent = Transfer::find($data['parent_transfer_id']);
        if (! $parent
            || $parent->purpose !== 'transfer'
            || $parent->status !== 'received'
            || ($transfer && $parent->is($transfer))) {
            throw ValidationException::withMessages([
                'parent_transfer_id' => 'Returul poate fi legat numai de un transfer initial receptionat.',
            ]);
        }

        abort_unless($actor->can('view', $parent), 403);
    }

    private function activeDriver(int $driverId): User
    {
        $driver = User::assignableDrivers()->where('active', true)->find($driverId);
        if (! $driver) {
            throw ValidationException::withMessages(['driver_id' => 'Soferul selectat nu este activ sau nu are rolul de sofer.']);
        }

        return $driver;
    }

    private function direction(Location $source, Location $destination): string
    {
        return $source->type.'_to_'.$destination->type;
    }

    private function lineSignature(array $lines): string
    {
        return collect($lines)->map(fn ($line) => [
            'catalog_item_id' => (int) ($line['catalog_item_id'] ?? 0),
            'tracked_asset_id' => (int) ($line['tracked_asset_id'] ?? 0),
            'quantity' => (float) ($line['quantity'] ?? 1),
        ])->sortBy(fn ($line) => implode(':', $line))->values()->toJson();
    }
}
