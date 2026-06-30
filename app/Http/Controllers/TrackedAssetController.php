<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrackedAssetController extends Controller
{
    public function index(Request $request): View
    {
        return view('tracked-assets.index', [
            'assets' => TrackedAsset::with(['catalogItem', 'currentLocation', 'currentCustodian'])
                ->when($request->location_id, fn ($query, $locationId) => $query->where('current_location_id', $locationId))
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('qr_code', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('catalogItem', fn ($itemQuery) => $itemQuery->where('name', 'like', "%{$search}%"));
                }))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'locations' => Location::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('tracking_type', 'serialized')->where('active', true)->orderBy('name')->get(),
            'custodians' => User::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'catalog_item_id' => ['required', 'exists:catalog_items,id'],
            'asset_code' => ['required', 'string', 'max:80', 'unique:tracked_assets,asset_code'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:available,in_use,in_transfer,maintenance,lost'],
            'condition' => ['required', 'in:good,used,damaged,needs_service'],
            'current_location_id' => ['nullable', 'exists:locations,id'],
            'current_custodian_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        TrackedAsset::create($data + [
            'qr_code' => 'QR-'.$data['asset_code'],
            'last_verified_at' => now(),
        ]);

        return back()->with('status', 'Echipamentul a fost adaugat.');
    }

    public function show(TrackedAsset $trackedAsset): View
    {
        $trackedAsset->load(['catalogItem', 'currentLocation', 'currentCustodian']);

        return view('tracked-assets.show', [
            'asset' => $trackedAsset,
            'history' => Transfer::query()
                ->whereHas('lines', fn ($query) => $query->where('tracked_asset_id', $trackedAsset->id))
                ->with(['sourceLocation', 'destinationLocation', 'driver', 'approver', 'confirmer'])
                ->latest()
                ->get(),
        ]);
    }
}
