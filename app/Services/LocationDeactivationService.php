<?php

namespace App\Services;

use App\Models\Location;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferApproval;
use Illuminate\Validation\ValidationException;

class LocationDeactivationService
{
    public function ensureCanDeactivate(Location $location): void
    {
        $trackedAssetsCount = TrackedAsset::query()
            ->where('current_location_id', $location->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->count();

        $positiveStockCount = StockLevel::query()
            ->where('location_id', $location->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'quantity'])
            ->filter(fn (StockLevel $stockLevel) => (float) $stockLevel->quantity > 0)
            ->count();

        $activeTransfers = Transfer::query()
            ->where(function ($query) use ($location): void {
                $query->where('source_location_id', $location->id)
                    ->orWhere('destination_location_id', $location->id);
            })
            ->whereNull('archived_at')
            ->whereNotIn('status', ['received', 'cancelled'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'revision']);

        $pendingApprovalsCount = $activeTransfers->isEmpty()
            ? 0
            : TransferApproval::query()
                ->whereIn('transfer_id', $activeTransfers->pluck('id'))
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'transfer_id', 'revision'])
                ->filter(function (TransferApproval $approval) use ($activeTransfers): bool {
                    $transfer = $activeTransfers->firstWhere('id', $approval->transfer_id);

                    return $transfer && (int) $approval->revision === (int) $transfer->revision;
                })
                ->count();

        $blockers = array_values(array_filter([
            $trackedAssetsCount > 0 ? $this->countLabel($trackedAssetsCount, 'echipament alocat', 'echipamente alocate') : null,
            $positiveStockCount > 0 ? $this->countLabel($positiveStockCount, 'material cu stoc pozitiv', 'materiale cu stoc pozitiv') : null,
            $pendingApprovalsCount > 0 ? $this->countLabel($pendingApprovalsCount, 'aprobare în așteptare', 'aprobări în așteptare') : null,
            $activeTransfers->isNotEmpty() ? $this->countLabel($activeTransfers->count(), 'transfer activ', 'transferuri active') : null,
        ]));

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                'active' => 'Locația nu poate fi dezactivată. Rezolvă mai întâi: '.implode('; ', $blockers).'.',
            ]);
        }
    }

    private function countLabel(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }
}
