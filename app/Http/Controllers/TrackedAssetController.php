<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use App\Services\LocationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TrackedAssetController extends Controller
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function index(Request $request): View
    {
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($request->user());

        return view('tracked-assets.index', [
            'assets' => TrackedAsset::with(['catalogItem', 'currentLocation', 'currentCustodian'])
                ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('current_location_id', $visibleLocationIds))
                ->when($request->catalog_item_id, fn ($query, $catalogItemId) => $query->where('catalog_item_id', $catalogItemId))
                ->when($request->location_id, fn ($query, $locationId) => $query->where('current_location_id', $locationId))
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->when($request->condition, fn ($query, $condition) => $query->where('condition', $condition))
                ->when($request->search, fn ($query, $search) => $query->where(function ($query) use ($search) {
                    $query->where('asset_code', 'like', "%{$search}%")
                        ->orWhere('qr_code', 'like', "%{$search}%")
                        ->orWhere('serial_number', 'like', "%{$search}%")
                        ->orWhereHas('catalogItem', fn ($itemQuery) => $itemQuery->where('name', 'like', "%{$search}%"));
                }))
                ->orderByRaw("CASE WHEN status IN ('maintenance', 'lost') OR `condition` IN ('damaged', 'needs_service') THEN 0 ELSE 1 END")
                ->orderByRaw('last_verified_at IS NULL DESC')
                ->orderBy('last_verified_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'totalAssets' => TrackedAsset::query()
                ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('current_location_id', $visibleLocationIds))
                ->count(),
            'locations' => $this->locationAccess->visibleLocations($request->user())->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('tracked-assets.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        TrackedAsset::create($data + [
            'qr_code' => 'QR-'.$data['asset_code'],
            'last_verified_at' => now(),
        ]);

        return redirect()->route('tracked-assets.index')->with('status', 'Echipamentul a fost adaugat.');
    }

    public function show(Request $request, TrackedAsset $trackedAsset): View
    {
        if ($request->user()->hasAnyRole(['sef-santier', 'gestionar-baza'])) {
            abort_unless(
                $trackedAsset->current_location_id
                    && $this->locationAccess->canView($request->user(), (int) $trackedAsset->current_location_id),
                403
            );
        }
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

    public function edit(TrackedAsset $trackedAsset): View
    {
        return view('tracked-assets.form', $this->formData($trackedAsset));
    }

    public function update(Request $request, TrackedAsset $trackedAsset): RedirectResponse
    {
        $data = $this->validatedData($request, $trackedAsset);
        $trackedAsset->update($data + [
            'qr_code' => 'QR-'.$data['asset_code'],
            'last_verified_at' => now(),
        ]);

        return redirect()->route('tracked-assets.show', $trackedAsset)->with('status', 'Echipamentul a fost actualizat.');
    }

    private function formData(?TrackedAsset $asset = null): array
    {
        return [
            'asset' => $asset,
            'locations' => Location::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('tracking_type', 'serialized')->where('active', true)->orderBy('name')->get(),
            'custodians' => User::where('active', true)->orderBy('name')->get(),
        ];
    }

    private function validatedData(Request $request, ?TrackedAsset $asset = null): array
    {
        return $request->validate([
            'catalog_item_id' => [
                'required',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query->where('tracking_type', 'serialized')),
            ],
            'asset_code' => ['required', 'string', 'max:80', Rule::unique('tracked_assets', 'asset_code')->ignore($asset)],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:available,in_use,in_transfer,maintenance,lost'],
            'condition' => ['required', 'in:good,used,damaged,needs_service'],
            'current_location_id' => ['nullable', 'exists:locations,id'],
            'current_custodian_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
