<?php

namespace App\Http\Controllers;

use App\Models\ConsumptionReport;
use App\Models\Location;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferLine;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canReadGlobally = $user->hasGlobalInventoryReadAccess();
        $managedLocationIds = $canReadGlobally
            ? null
            : $user->activeManagedLocations()
                ->where('locations.active', true)
                ->pluck('locations.id')
                ->all();
        $locations = Location::where('active', true)
            ->when($managedLocationIds !== null, fn ($query) => $query->whereIn('id', $managedLocationIds))
            ->orderBy('name')
            ->get();

        return view('reports.index', [
            'assetsByLocation' => Location::withCount('stockLevels')
                ->with(['stockLevels.catalogItem'])
                ->where('active', true)
                ->when($managedLocationIds !== null, fn ($query) => $query->whereIn('id', $managedLocationIds))
                ->when($request->location_id, fn ($query, $id) => $query->whereKey($id))
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(function (Location $location) {
                    $location->tracked_assets_count = TrackedAsset::where('current_location_id', $location->id)->count();

                    return $location;
                }),
            'missingAssets' => TrackedAsset::with(['catalogItem', 'currentLocation'])
                ->whereIn('status', ['lost', 'in_transfer'])
                ->when($managedLocationIds !== null, fn ($query) => $query->whereIn('current_location_id', $managedLocationIds))
                ->when($request->location_id, fn ($query, $id) => $query->where('current_location_id', $id))
                ->latest()
                ->limit(10)
                ->get(),
            'recentTransfers' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver', 'approver', 'confirmer'])
                ->withCount('lines')
                ->when($managedLocationIds !== null, fn ($query) => $query->where(fn ($locations) => $locations
                    ->whereIn('source_location_id', $managedLocationIds)
                    ->orWhereIn('destination_location_id', $managedLocationIds)))
                ->when($request->location_id, fn ($query, $id) => $query->where(fn ($locations) => $locations->where('source_location_id', $id)->orWhere('destination_location_id', $id)))
                ->when($request->status, fn ($query, $status) => $query->where('status', $status))
                ->when($request->date_from, fn ($query, $date) => $query->whereDate('requested_at', '>=', $date))
                ->when($request->date_to, fn ($query, $date) => $query->whereDate('requested_at', '<=', $date))
                ->latest()
                ->limit(12)
                ->get(),
            'inTransitAlerts' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver'])
                ->where('status', 'in_transit')
                ->when($managedLocationIds !== null, fn ($query) => $query->where(fn ($locations) => $locations
                    ->whereIn('source_location_id', $managedLocationIds)
                    ->orWhereIn('destination_location_id', $managedLocationIds)))
                ->when($request->location_id, fn ($query, $id) => $query->where(fn ($locations) => $locations->where('source_location_id', $id)->orWhere('destination_location_id', $id)))
                ->where('dispatched_at', '<', now()->subHours(12))
                ->oldest('dispatched_at')
                ->limit(12)
                ->get(),
            'discrepancyLines' => TransferLine::with(['transfer.sourceLocation', 'transfer.destinationLocation', 'catalogItem', 'trackedAsset'])
                ->where(function ($query) {
                    $query->where('received_status', '!=', 'received')
                        ->orWhereHas('transfer', fn ($transferQuery) => $transferQuery->where('received_with_discrepancy', true));
                })
                ->when($managedLocationIds !== null, fn ($query) => $query->whereHas('transfer', fn ($transfer) => $transfer
                    ->where(fn ($locations) => $locations
                        ->whereIn('source_location_id', $managedLocationIds)
                        ->orWhereIn('destination_location_id', $managedLocationIds))))
                ->when($request->location_id, fn ($query, $id) => $query->whereHas('transfer', fn ($transfer) => $transfer->where(fn ($locations) => $locations->where('source_location_id', $id)->orWhere('destination_location_id', $id))))
                ->latest()
                ->limit(12)
                ->get(),
            'recentConsumption' => ConsumptionReport::with(['location', 'reporter', 'lines.catalogItem'])
                ->when($managedLocationIds !== null, fn ($query) => $query->whereIn('location_id', $managedLocationIds))
                ->when($request->location_id, fn ($query, $id) => $query->where('location_id', $id))
                ->when($request->date_from, fn ($query, $date) => $query->whereDate('reported_at', '>=', $date))
                ->when($request->date_to, fn ($query, $date) => $query->whereDate('reported_at', '<=', $date))
                ->latest('reported_at')
                ->limit(12)
                ->get(),
            'locations' => $locations,
        ]);
    }
}
