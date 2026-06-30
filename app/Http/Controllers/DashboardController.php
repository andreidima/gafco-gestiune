<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\DriverRequest;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'stats' => [
                'Baze' => Location::where('type', 'base')->count(),
                'Santiere active' => Location::where('type', 'site')->where('active', true)->count(),
                'Articole' => CatalogItem::where('active', true)->count(),
                'Asset-uri QR' => TrackedAsset::count(),
                'In tranzit' => Transfer::where('status', 'in_transit')->count(),
                'Cereri sofer' => DriverRequest::whereIn('status', ['open', 'assigned'])->count(),
            ],
            'transfers' => Transfer::with(['sourceLocation', 'destinationLocation', 'driver'])
                ->latest()
                ->limit(8)
                ->get(),
            'driverRequests' => DriverRequest::with(['site', 'assignedDriver'])
                ->latest()
                ->limit(6)
                ->get(),
            'stockSnapshot' => StockLevel::with(['location', 'catalogItem'])
                ->where('quantity', '>', 0)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
