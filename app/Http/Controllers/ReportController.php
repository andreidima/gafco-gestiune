<?php

namespace App\Http\Controllers;

use App\Models\ConsumptionReport;
use App\Models\Location;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\TransferLine;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'assetsByLocation' => Location::withCount('stockLevels')
                ->with(['stockLevels.catalogItem'])
                ->where('active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(function (Location $location) {
                    $location->tracked_assets_count = TrackedAsset::where('current_location_id', $location->id)->count();

                    return $location;
                }),
            'missingAssets' => TrackedAsset::with(['catalogItem', 'currentLocation'])
                ->whereIn('status', ['lost', 'in_transfer'])
                ->latest()
                ->limit(10)
                ->get(),
            'recentTransfers' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver', 'approver', 'confirmer'])
                ->withCount('lines')
                ->latest()
                ->limit(12)
                ->get(),
            'inTransitAlerts' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver'])
                ->where('status', 'in_transit')
                ->where('dispatched_at', '<', now()->subHours(12))
                ->oldest('dispatched_at')
                ->limit(12)
                ->get(),
            'discrepancyLines' => TransferLine::with(['transfer.sourceLocation', 'transfer.destinationLocation', 'catalogItem', 'trackedAsset'])
                ->where(function ($query) {
                    $query->where('received_status', '!=', 'received')
                        ->orWhereHas('transfer', fn ($transferQuery) => $transferQuery->where('received_with_discrepancy', true));
                })
                ->latest()
                ->limit(12)
                ->get(),
            'recentConsumption' => ConsumptionReport::with(['location', 'reporter', 'lines.catalogItem'])
                ->latest('reported_at')
                ->limit(12)
                ->get(),
        ]);
    }
}
