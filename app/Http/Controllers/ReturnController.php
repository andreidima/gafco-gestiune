<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function index(): View
    {
        return view('returns.index', [
            'returns' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver', 'lines.catalogItem', 'lines.trackedAsset'])
                ->where('type', 'site_to_base')
                ->latest()
                ->paginate(20),
            'sites' => Location::where('type', 'site')->where('active', true)->orderBy('name')->get(),
            'bases' => Location::where('type', 'base')->where('active', true)->orderBy('name')->get(),
            'drivers' => User::role('sofer')->orderBy('name')->get(),
            'items' => CatalogItem::where('tracking_type', 'quantity')->where('active', true)->orderBy('name')->get(),
            'assets' => TrackedAsset::with(['catalogItem', 'currentLocation'])
                ->whereIn('status', ['available', 'in_use'])
                ->orderBy('asset_code')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_location_id' => ['required', 'exists:locations,id'],
            'destination_location_id' => ['required', 'different:source_location_id', 'exists:locations,id'],
            'driver_id' => ['nullable', 'exists:users,id'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'catalog_item_id' => ['nullable', 'required_without:tracked_asset_id', 'exists:catalog_items,id'],
            'tracked_asset_id' => ['nullable', 'required_without:catalog_item_id', 'exists:tracked_assets,id'],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $asset = ! empty($data['tracked_asset_id'])
                ? TrackedAsset::with('catalogItem')->findOrFail($data['tracked_asset_id'])
                : null;
            $item = $asset?->catalogItem ?? CatalogItem::findOrFail($data['catalog_item_id']);

            $return = Transfer::create([
                'number' => 'RT-'.now()->format('Ymd-His'),
                'type' => 'site_to_base',
                'status' => 'pending_approval',
                'source_location_id' => $data['source_location_id'],
                'destination_location_id' => $data['destination_location_id'],
                'requested_by' => $request->user()->id,
                'driver_id' => $data['driver_id'] ?: null,
                'document_number' => $data['document_number'] ?? null,
                'requested_at' => now(),
                'assigned_at' => $data['driver_id'] ? now() : null,
                'notes' => $data['notes'] ?? null,
            ]);

            $return->lines()->create([
                'catalog_item_id' => $item->id,
                'tracked_asset_id' => $asset?->id,
                'quantity' => $asset ? 1 : ($data['quantity'] ?? 1),
                'unit' => $item->unit,
            ]);

            $asset?->update(['status' => 'in_transfer']);
        });

        return back()->with('status', 'Returul catre baza a fost creat.');
    }
}
