<?php

namespace App\Services;

use App\Models\ConsumptionReport;
use App\Models\ConsumptionReportLine;
use App\Models\InventoryLot;
use App\Models\InventoryLotBalance;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\SupplierReceptionLine;
use App\Models\TransferLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockLedgerService
{
    public function postReception(SupplierReceptionLine $line, ?User $actor): InventoryLot
    {
        $line->loadMissing(['catalogItem', 'reception.supplier']);
        $reception = $line->reception;

        return DB::transaction(function () use ($line, $reception, $actor): InventoryLot {
            $existing = InventoryLot::where('source_key', 'reception-line:'.$line->id)->first();
            if ($existing) {
                return $existing;
            }

            $lot = InventoryLot::create([
                'catalog_item_id' => $line->catalog_item_id,
                'supplier_id' => $reception?->supplier_id,
                'supplier_reception_line_id' => $line->id,
                'source_key' => 'reception-line:'.$line->id,
                'document_number' => $reception?->document_number,
                'received_at' => $reception?->received_at ?? now(),
                'lot_code' => $line->lot_code,
                'expires_at' => $line->expires_at,
                'unit_price' => $line->unit_price,
                'currency' => $line->currency ?: 'RON',
                'notes' => $line->notes ?: $reception?->notes,
            ]);

            $group = (string) Str::uuid();
            $this->changeLotBalance($lot, (int) $reception->location_id, (float) $line->quantity);
            $this->changeStockLevel((int) $reception->location_id, (int) $line->catalog_item_id, (float) $line->quantity);
            $this->recordMovement(
                $group,
                $lot,
                (int) $reception->location_id,
                (float) $line->quantity,
                'reception',
                'supplier_reception',
                (int) $reception->id,
                (int) $line->id,
                $actor,
                $reception->received_at ?? now(),
                $reception->notes,
            );

            return $lot;
        });
    }

    /**
     * @param  array<int, array{inventory_lot_id:int, quantity:float|int|string}>|null  $requestedAllocations
     */
    public function postConsumption(
        ConsumptionReportLine $line,
        int $locationId,
        ?User $actor,
        ?array $requestedAllocations = null,
    ): void {
        DB::transaction(function () use ($line, $locationId, $actor, $requestedAllocations): void {
            if (StockMovement::where('reference_type', 'consumption_report')
                ->where('reference_line_id', $line->id)
                ->exists()) {
                return;
            }

            $this->reconcileUntrackedStock($locationId, (int) $line->catalog_item_id, $actor);
            $allocations = $requestedAllocations === null
                ? $this->allocateLots($locationId, (int) $line->catalog_item_id, (float) $line->quantity)
                : $this->validateRequestedAllocations(
                    $locationId,
                    (int) $line->catalog_item_id,
                    (float) $line->quantity,
                    $requestedAllocations,
                );
            $group = (string) Str::uuid();

            foreach ($allocations as $allocation) {
                $this->changeLotBalance($allocation['lot'], $locationId, -$allocation['quantity']);
                $this->recordMovement(
                    $group,
                    $allocation['lot'],
                    $locationId,
                    -$allocation['quantity'],
                    'consumption',
                    'consumption_report',
                    (int) $line->consumption_report_id,
                    (int) $line->id,
                    $actor,
                    $line->consumptionReport?->reported_at ?? now(),
                    $line->notes,
                );
            }

            $this->changeStockLevel($locationId, (int) $line->catalog_item_id, -(float) $line->quantity);
        });
    }

    /**
     * @return Collection<int, array{lot: InventoryLot, available: float, quantity: float}>
     */
    public function suggestConsumptionAllocations(
        int $locationId,
        int $catalogItemId,
        float $requestedQuantity,
        array $virtualLotQuantities = [],
    ): Collection {
        $remaining = round($requestedQuantity, 3);
        if ($remaining <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Introdu o cantitate mai mare decât zero.']);
        }

        $allocations = collect();
        $balances = InventoryLotBalance::query()
            ->where('location_id', $locationId)
            ->where(function ($query) use ($virtualLotQuantities): void {
                $query->where('quantity', '>', 0);
                if ($virtualLotQuantities !== []) {
                    $query->orWhereIn('inventory_lot_id', array_keys($virtualLotQuantities));
                }
            })
            ->whereHas('lot', fn ($query) => $query->where('catalog_item_id', $catalogItemId))
            ->with(['lot.supplier'])
            ->join('inventory_lots', 'inventory_lots.id', '=', 'inventory_lot_balances.inventory_lot_id')
            ->orderByRaw('CASE WHEN inventory_lots.expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('inventory_lots.expires_at')
            ->orderBy('inventory_lots.received_at')
            ->orderBy('inventory_lot_balances.id')
            ->select('inventory_lot_balances.*')
            ->get();

        foreach ($balances as $balance) {
            if ($remaining <= 0.0005) {
                break;
            }

            $available = round(
                (float) $balance->quantity + (float) ($virtualLotQuantities[$balance->inventory_lot_id] ?? 0),
                3,
            );
            if ($available <= 0.0005) {
                continue;
            }

            $quantity = min($remaining, $available);
            $allocations->push([
                'lot' => $balance->lot,
                'available' => $available,
                'quantity' => $quantity,
            ]);
            $remaining = round($remaining - $quantity, 3);
        }

        if ($remaining > 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Cantitatea depășește stocul disponibil pe loturi pentru această locație.',
            ]);
        }

        return $allocations;
    }

    /** @return array<int, float> */
    public function virtualCorrectionLotQuantities(
        ConsumptionReport $report,
        int $locationId,
        int $catalogItemId,
    ): array {
        if ((int) $report->location_id !== $locationId) {
            return [];
        }

        $lineIds = $report->lines()
            ->where('catalog_item_id', $catalogItemId)
            ->pluck('id');
        if ($lineIds->isEmpty()) {
            return [];
        }

        $quantities = StockMovement::query()
            ->selectRaw('inventory_lot_id, ABS(SUM(quantity)) as quantity')
            ->where('reference_type', 'consumption_report')
            ->where('reference_id', $report->id)
            ->whereIn('reference_line_id', $lineIds)
            ->where('movement_type', 'consumption')
            ->where('quantity', '<', 0)
            ->groupBy('inventory_lot_id')
            ->pluck('quantity', 'inventory_lot_id')
            ->mapWithKeys(fn ($quantity, $lotId) => [(int) $lotId => (float) $quantity])
            ->all();

        $reportedQuantity = round((float) $report->lines()
            ->where('catalog_item_id', $catalogItemId)
            ->sum('quantity'), 3);
        $missingLegacyQuantity = round($reportedQuantity - array_sum($quantities), 3);
        if ($missingLegacyQuantity <= 0.0005) {
            return $quantities;
        }

        $openingLotId = InventoryLot::query()
            ->where('catalog_item_id', $catalogItemId)
            ->where('is_opening_balance', true)
            ->whereHas('balances', fn ($query) => $query->where('location_id', $locationId))
            ->oldest('id')
            ->value('id');
        if ($openingLotId) {
            $quantities[(int) $openingLotId] = round(
                ($quantities[(int) $openingLotId] ?? 0) + $missingLegacyQuantity,
                3,
            );
        }

        return $quantities;
    }

    public function reverseConsumption(
        ConsumptionReport $report,
        User $actor,
        string $reason,
    ): void {
        $report->loadMissing('lines');

        foreach ($report->lines as $line) {
            $movements = StockMovement::query()
                ->where('reference_type', 'consumption_report')
                ->where('reference_id', $report->id)
                ->where('reference_line_id', $line->id)
                ->where('movement_type', 'consumption')
                ->where('quantity', '<', 0)
                ->lockForUpdate()
                ->get();

            $postedQuantity = round(abs((float) $movements->sum('quantity')), 3);
            if ($movements->isEmpty()) {
                $lot = InventoryLot::query()
                    ->where('catalog_item_id', $line->catalog_item_id)
                    ->where('is_opening_balance', true)
                    ->whereHas('balances', fn ($query) => $query->where('location_id', $report->location_id))
                    ->oldest('id')
                    ->first();
                $lot ??= InventoryLot::create([
                    'catalog_item_id' => $line->catalog_item_id,
                    'source_key' => 'legacy-consumption-correction:'.$report->id.':'.$line->id,
                    'currency' => 'RON',
                    'is_opening_balance' => true,
                    'notes' => 'Cantitate istorică restabilită pentru corectarea unui consum fără mișcări pe loturi.',
                ]);
                $quantity = (float) $line->quantity;
                $this->changeLotBalance($lot, (int) $report->location_id, $quantity);
                $this->recordMovement(
                    (string) Str::uuid(),
                    $lot,
                    (int) $report->location_id,
                    $quantity,
                    'consumption_correction_reversal',
                    'consumption_report_revision',
                    (int) $report->id,
                    (int) $line->id,
                    $actor,
                    now(),
                    'Corecție consum istoric: '.$reason,
                );
                $this->changeStockLevel(
                    (int) $report->location_id,
                    (int) $line->catalog_item_id,
                    $quantity,
                );

                continue;
            }

            if (abs($postedQuantity - (float) $line->quantity) > 0.0005) {
                throw ValidationException::withMessages([
                    'correction_reason' => 'Istoricul de stoc al unei poziții nu este complet. Corecția a fost oprită pentru verificare.',
                ]);
            }

            $group = (string) Str::uuid();
            foreach ($movements as $movement) {
                $lot = InventoryLot::findOrFail($movement->inventory_lot_id);
                $quantity = abs((float) $movement->quantity);
                $this->changeLotBalance($lot, (int) $movement->location_id, $quantity);
                $this->recordMovement(
                    $group,
                    $lot,
                    (int) $movement->location_id,
                    $quantity,
                    'consumption_correction_reversal',
                    'consumption_report_revision',
                    (int) $report->id,
                    (int) $line->id,
                    $actor,
                    now(),
                    $reason,
                );
            }

            $this->changeStockLevel(
                (int) $report->location_id,
                (int) $line->catalog_item_id,
                (float) $line->quantity,
            );
        }
    }

    public function postTransfer(TransferLine $line, int $sourceLocationId, int $destinationLocationId, ?User $actor): void
    {
        DB::transaction(function () use ($line, $sourceLocationId, $destinationLocationId, $actor): void {
            if (StockMovement::where('reference_type', 'transfer')
                ->where('reference_line_id', $line->id)
                ->exists()) {
                return;
            }

            $this->reconcileUntrackedStock($sourceLocationId, (int) $line->catalog_item_id, $actor);
            $allocations = $this->allocateLots($sourceLocationId, (int) $line->catalog_item_id, (float) $line->quantity);
            $group = (string) Str::uuid();

            foreach ($allocations as $allocation) {
                $this->changeLotBalance($allocation['lot'], $sourceLocationId, -$allocation['quantity']);
                $this->changeLotBalance($allocation['lot'], $destinationLocationId, $allocation['quantity']);
                $this->recordMovement(
                    $group,
                    $allocation['lot'],
                    $sourceLocationId,
                    -$allocation['quantity'],
                    'transfer_out',
                    'transfer',
                    (int) $line->transfer_id,
                    (int) $line->id,
                    $actor,
                    now(),
                    $line->notes,
                );
                $this->recordMovement(
                    $group,
                    $allocation['lot'],
                    $destinationLocationId,
                    $allocation['quantity'],
                    'transfer_in',
                    'transfer',
                    (int) $line->transfer_id,
                    (int) $line->id,
                    $actor,
                    now(),
                    $line->notes,
                );
            }

            $this->changeStockLevel($sourceLocationId, (int) $line->catalog_item_id, -(float) $line->quantity);
            $this->changeStockLevel($destinationLocationId, (int) $line->catalog_item_id, (float) $line->quantity);
        });
    }

    private function reconcileUntrackedStock(int $locationId, int $catalogItemId, ?User $actor): void
    {
        $stock = StockLevel::firstOrCreate(
            ['location_id' => $locationId, 'catalog_item_id' => $catalogItemId],
            ['quantity' => 0]
        );
        $stock = StockLevel::whereKey($stock->id)->lockForUpdate()->firstOrFail();
        $tracked = (float) InventoryLotBalance::where('location_id', $locationId)
            ->whereHas('lot', fn ($query) => $query->where('catalog_item_id', $catalogItemId))
            ->sum('quantity');
        $difference = round((float) $stock->quantity - $tracked, 3);

        if ($difference < -0.0005) {
            throw ValidationException::withMessages([
                'stock' => 'Fișa de inventar conține mai mult stoc decât totalul locației. Operațiunea a fost oprită pentru verificare.',
            ]);
        }

        if ($difference <= 0.0005) {
            return;
        }

        $lot = InventoryLot::create([
            'catalog_item_id' => $catalogItemId,
            'source_key' => 'reconciliation:'.$locationId.':'.$catalogItemId.':'.Str::uuid(),
            'currency' => 'RON',
            'is_opening_balance' => true,
            'notes' => 'Sold existent reconciliat automat în fișa de inventar.',
        ]);
        $this->changeLotBalance($lot, $locationId, $difference);
        $this->recordMovement(
            (string) Str::uuid(),
            $lot,
            $locationId,
            $difference,
            'opening_balance',
            'stock_reconciliation',
            $stock->id,
            null,
            $actor,
            now(),
            'Diferență preluată din stocul agregat existent.',
        );
    }

    /** @return Collection<int, array{lot: InventoryLot, quantity: float}> */
    private function allocateLots(int $locationId, int $catalogItemId, float $requestedQuantity): Collection
    {
        $remaining = round($requestedQuantity, 3);
        $allocations = collect();
        $balances = InventoryLotBalance::query()
            ->where('location_id', $locationId)
            ->where('quantity', '>', 0)
            ->whereHas('lot', fn ($query) => $query->where('catalog_item_id', $catalogItemId))
            ->with('lot')
            ->join('inventory_lots', 'inventory_lots.id', '=', 'inventory_lot_balances.inventory_lot_id')
            ->orderByRaw('CASE WHEN inventory_lots.expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('inventory_lots.expires_at')
            ->orderBy('inventory_lots.received_at')
            ->orderBy('inventory_lot_balances.id')
            ->select('inventory_lot_balances.*')
            ->lockForUpdate()
            ->get();

        foreach ($balances as $balance) {
            if ($remaining <= 0.0005) {
                break;
            }

            $quantity = min($remaining, (float) $balance->quantity);
            $allocations->push(['lot' => $balance->lot, 'quantity' => $quantity]);
            $remaining = round($remaining - $quantity, 3);
        }

        if ($remaining > 0.0005) {
            throw ValidationException::withMessages([
                'quantity' => 'Cantitatea depășește stocul disponibil pe loturi pentru această locație.',
            ]);
        }

        return $allocations;
    }

    /**
     * @param  array<int, array{inventory_lot_id:int, quantity:float|int|string}>  $requestedAllocations
     * @return Collection<int, array{lot: InventoryLot, quantity: float}>
     */
    private function validateRequestedAllocations(
        int $locationId,
        int $catalogItemId,
        float $requestedQuantity,
        array $requestedAllocations,
    ): Collection {
        $normalized = collect($requestedAllocations)
            ->map(fn (array $allocation) => [
                'inventory_lot_id' => (int) ($allocation['inventory_lot_id'] ?? 0),
                'quantity' => round((float) ($allocation['quantity'] ?? 0), 3),
            ])
            ->filter(fn (array $allocation) => $allocation['quantity'] > 0.0005)
            ->values();

        if ($normalized->isEmpty()
            || $normalized->pluck('inventory_lot_id')->duplicates()->isNotEmpty()
            || abs($normalized->sum('quantity') - round($requestedQuantity, 3)) > 0.0005) {
            throw ValidationException::withMessages([
                'allocations' => 'Cantitățile alocate pe loturi trebuie să fie distincte și să însumeze consumul solicitat.',
            ]);
        }

        $balances = InventoryLotBalance::query()
            ->where('location_id', $locationId)
            ->whereIn('inventory_lot_id', $normalized->pluck('inventory_lot_id'))
            ->whereHas('lot', fn ($query) => $query->where('catalog_item_id', $catalogItemId))
            ->with('lot')
            ->lockForUpdate()
            ->get()
            ->keyBy('inventory_lot_id');

        if ($balances->count() !== $normalized->count()) {
            throw ValidationException::withMessages([
                'allocations' => 'Unul dintre loturile selectate nu mai este disponibil în locația aleasă.',
            ]);
        }

        return $normalized->map(function (array $allocation) use ($balances): array {
            $balance = $balances->get($allocation['inventory_lot_id']);
            if (! $balance || $allocation['quantity'] - (float) $balance->quantity > 0.0005) {
                throw ValidationException::withMessages([
                    'allocations' => 'Cantitatea aleasă depășește soldul disponibil al unui lot.',
                ]);
            }

            return [
                'lot' => $balance->lot,
                'quantity' => $allocation['quantity'],
            ];
        });
    }

    private function changeLotBalance(InventoryLot $lot, int $locationId, float $quantity): void
    {
        $balance = InventoryLotBalance::firstOrCreate(
            ['inventory_lot_id' => $lot->id, 'location_id' => $locationId],
            ['quantity' => 0]
        );
        $balance = InventoryLotBalance::whereKey($balance->id)->lockForUpdate()->firstOrFail();
        $newQuantity = round((float) $balance->quantity + $quantity, 3);
        if ($newQuantity < -0.0005) {
            throw ValidationException::withMessages(['stock' => 'Cantitatea lotului nu poate deveni negativă.']);
        }
        $balance->update(['quantity' => max(0, $newQuantity)]);
    }

    private function changeStockLevel(int $locationId, int $catalogItemId, float $quantity): void
    {
        $stock = StockLevel::firstOrCreate(
            ['location_id' => $locationId, 'catalog_item_id' => $catalogItemId],
            ['quantity' => 0]
        );
        $stock = StockLevel::whereKey($stock->id)->lockForUpdate()->firstOrFail();
        $newQuantity = round((float) $stock->quantity + $quantity, 3);
        if ($newQuantity < -0.0005) {
            throw ValidationException::withMessages(['stock' => 'Stocul locației nu poate deveni negativ.']);
        }
        $stock->update(['quantity' => max(0, $newQuantity)]);
    }

    private function recordMovement(
        string $group,
        InventoryLot $lot,
        int $locationId,
        float $quantity,
        string $movementType,
        ?string $referenceType,
        ?int $referenceId,
        ?int $referenceLineId,
        ?User $actor,
        mixed $occurredAt,
        ?string $notes,
    ): void {
        StockMovement::create([
            'group_uuid' => $group,
            'inventory_lot_id' => $lot->id,
            'catalog_item_id' => $lot->catalog_item_id,
            'location_id' => $locationId,
            'quantity' => $quantity,
            'movement_type' => $movementType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reference_line_id' => $referenceLineId,
            'posted_by' => $actor?->id,
            'occurred_at' => $occurredAt ?? now(),
            'notes' => $notes,
        ]);
    }
}
