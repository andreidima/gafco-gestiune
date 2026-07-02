<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function index(): View
    {
        return view('transfers.index', [
            'transfers' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver', 'approver', 'confirmer', 'lines.catalogItem', 'lines.trackedAsset'])
                ->withCount('lines')
                ->latest()
                ->paginate(20),
            'locations' => Location::where('active', true)->orderBy('name')->get(),
            'drivers' => User::role('sofer')->orderBy('name')->get(),
            'items' => CatalogItem::where('active', true)->orderBy('name')->get(),
            'assets' => TrackedAsset::with(['catalogItem', 'currentLocation'])
                ->whereIn('status', ['available', 'in_use'])
                ->orderBy('asset_code')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:base_to_site,site_to_site,site_to_base'],
            'source_location_id' => ['required', 'exists:locations,id'],
            'destination_location_id' => ['required', 'different:source_location_id', 'exists:locations,id'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'catalog_item_id' => ['nullable', 'required_without:tracked_asset_id', 'exists:catalog_items,id'],
            'tracked_asset_id' => ['nullable', 'required_without:catalog_item_id', 'exists:tracked_assets,id'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $asset = ! empty($data['tracked_asset_id'])
                ? TrackedAsset::with('catalogItem')->findOrFail($data['tracked_asset_id'])
                : null;
            $item = $asset?->catalogItem ?? CatalogItem::findOrFail($data['catalog_item_id']);
            $transfer = Transfer::create([
                'number' => 'TR-'.now()->format('Ymd-His'),
                'type' => $data['type'],
                'status' => 'pending_approval',
                'source_location_id' => $data['source_location_id'],
                'destination_location_id' => $data['destination_location_id'],
                'driver_id' => $data['driver_id'] ?: null,
                'document_number' => $data['document_number'] ?? null,
                'requested_by' => $request->user()->id,
                'requested_at' => now(),
                'assigned_at' => $data['driver_id'] ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);
            $transfer->lines()->create([
                'catalog_item_id' => $item->id,
                'tracked_asset_id' => $asset?->id,
                'quantity' => $asset ? 1 : ($data['quantity'] ?? 1),
                'unit' => $item->unit,
            ]);

            $asset?->update(['status' => 'in_transfer']);
        });

        return back()->with('status', 'Transferul a fost creat.');
    }

    public function update(Request $request, Transfer $transfer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,pending_approval,approved,assigned,in_transit,received,cancelled'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'received_with_discrepancy' => ['nullable', 'boolean'],
            'discrepancy_notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request, $transfer) {
            $wasReceived = $transfer->status === 'received';
            $updates = [
                'status' => $data['status'],
                'document_number' => $data['document_number'] ?? $transfer->document_number,
                'received_with_discrepancy' => (bool) ($data['received_with_discrepancy'] ?? ! empty($data['discrepancy_notes'])),
                'discrepancy_notes' => $data['discrepancy_notes'] ?? $transfer->discrepancy_notes,
            ];

            if (array_key_exists('driver_id', $data)) {
                $updates['driver_id'] = $data['driver_id'] ?: null;
                if ($data['driver_id'] && ! $transfer->assigned_at) {
                    $updates['assigned_at'] = now();
                }
            }

            $updates += match ($data['status']) {
                'approved' => [
                    'approved_by' => $transfer->approved_by ?? $request->user()->id,
                    'approved_at' => $transfer->approved_at ?? now(),
                ],
                'assigned' => ['assigned_at' => $transfer->assigned_at ?? now()],
                'in_transit' => ['dispatched_at' => $transfer->dispatched_at ?? now()],
                'received' => [
                    'received_at' => $transfer->received_at ?? now(),
                    'confirmed_by' => $transfer->confirmed_by ?? $request->user()->id,
                ],
                default => [],
            };

            $transfer->update($updates);

            if ($data['status'] === 'received') {
                $transfer->load('lines.trackedAsset');
                foreach ($transfer->lines as $line) {
                    $line->trackedAsset?->update([
                        'status' => 'in_use',
                        'current_location_id' => $transfer->destination_location_id,
                        'last_verified_at' => now(),
                    ]);
                    if (! $wasReceived && ! $line->tracked_asset_id) {
                        $this->moveStock(
                            $line->catalog_item_id,
                            $transfer->source_location_id,
                            $transfer->destination_location_id,
                            (float) $line->quantity
                        );
                    }
                    $line->update(['received_status' => 'received']);
                }
            }

            if ($data['status'] === 'cancelled') {
                $transfer->load('lines.trackedAsset');
                foreach ($transfer->lines as $line) {
                    $line->trackedAsset?->update(['status' => 'available']);
                }
            }
        });

        return back()->with('status', 'Statusul transferului a fost actualizat.');
    }

    private function moveStock(int $catalogItemId, ?int $sourceLocationId, ?int $destinationLocationId, float $quantity): void
    {
        if ($sourceLocationId) {
            $sourceStock = StockLevel::firstOrCreate(
                ['location_id' => $sourceLocationId, 'catalog_item_id' => $catalogItemId],
                ['quantity' => 0]
            );
            $sourceStock->update(['quantity' => max(0, (float) $sourceStock->quantity - $quantity)]);
        }

        if ($destinationLocationId) {
            $destinationStock = StockLevel::firstOrCreate(
                ['location_id' => $destinationLocationId, 'catalog_item_id' => $catalogItemId],
                ['quantity' => 0]
            );
            $destinationStock->increment('quantity', $quantity);
        }
    }
}
