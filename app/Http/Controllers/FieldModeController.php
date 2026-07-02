<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\CustodyTransfer;
use App\Models\DriverRequest;
use App\Models\Location;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\View\View;

class FieldModeController extends Controller
{
    public function driver(): View
    {
        return view('field.driver', [
            'transfers' => Transfer::with(['sourceLocation', 'destinationLocation', 'lines.catalogItem', 'lines.trackedAsset'])
                ->whereIn('status', ['assigned', 'in_transit'])
                ->latest('assigned_at')
                ->limit(30)
                ->get(),
            'driverRequests' => DriverRequest::with(['site', 'assignedDriver'])
                ->whereIn('status', ['open', 'assigned', 'in_progress'])
                ->latest('needed_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function siteManager(): View
    {
        return view('field.site-manager', [
            'pendingTransfers' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver', 'lines.catalogItem', 'lines.trackedAsset'])
                ->whereIn('status', ['pending_approval', 'in_transit'])
                ->latest()
                ->limit(25)
                ->get(),
            'recentConsumption' => ConsumptionReport::with(['location', 'lines.catalogItem'])
                ->latest('reported_at')
                ->limit(8)
                ->get(),
            'sites' => Location::where('type', 'site')->where('active', true)->orderBy('name')->get(),
            'bases' => Location::where('type', 'base')->where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('tracking_type', 'quantity')->where('active', true)->orderBy('name')->get(),
            'assets' => TrackedAsset::with(['catalogItem', 'currentLocation'])->whereIn('status', ['available', 'in_use'])->orderBy('asset_code')->limit(120)->get(),
            'drivers' => User::role('sofer')->orderBy('name')->get(),
        ]);
    }

    public function worker(): View
    {
        return view('field.worker', [
            'assets' => TrackedAsset::with(['catalogItem', 'currentLocation', 'currentCustodian'])
                ->whereNotNull('current_custodian_id')
                ->whereIn('status', ['available', 'in_use'])
                ->latest('last_verified_at')
                ->limit(40)
                ->get(),
            'custodyTransfers' => CustodyTransfer::with(['trackedAsset.catalogItem', 'fromUser', 'toUser'])
                ->latest()
                ->limit(30)
                ->get(),
            'workers' => User::role('muncitor')->orderBy('name')->get(),
        ]);
    }
}
