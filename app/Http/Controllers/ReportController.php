<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\TrackedAsset;
use App\Models\Transfer;
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
        ]);
    }
}
