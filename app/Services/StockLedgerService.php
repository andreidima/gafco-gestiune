<?php

namespace App\Services;

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
                'currency' => 'RON',
                'notes' => $reception?->notes,
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

    public function postConsumption(ConsumptionReportLine $line, int $locationId, ?User $actor): void
    {
        DB::transaction(function () use ($line, $locationId, $actor): void {
            if (StockMovement::where('reference_type', 'consumption_report')
                ->where('reference_line_id', $line->id)
                ->exists()) {
                return;
            }

            $this->reconcileUntrackedStock($locationId, (int) $line->catalog_item_id, $actor);
            $allocations = $this->allocateLots($locationId, (int) $line->catalog_item_id, (float) $line->quantity);
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
