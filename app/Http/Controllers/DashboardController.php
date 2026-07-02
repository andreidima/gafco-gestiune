<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\CustodyTransfer;
use App\Models\DriverRequest;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\SupplierReception;
use App\Models\TrackedAsset;
use App\Models\Transfer;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $assetStatusCounts = TrackedAsset::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $transferStatusCounts = Transfer::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $consumptionReports = ConsumptionReport::with('lines')
            ->where('reported_at', '>=', now()->subDays(30))
            ->get();
        $consumptionTrend = collect(range(29, 0))
            ->map(function (int $daysAgo) use ($consumptionReports) {
                $date = now()->subDays($daysAgo);
                $total = $consumptionReports
                    ->filter(fn (ConsumptionReport $report) => $report->reported_at?->isSameDay($date))
                    ->sum(fn (ConsumptionReport $report) => $report->lines->sum('quantity'));

                return [
                    'label' => $date->format('d.m'),
                    'value' => round((float) $total, 2),
                ];
            });
        $topLocations = Location::where('active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function (Location $location) {
                $location->assets_count = TrackedAsset::where('current_location_id', $location->id)->count();

                return $location;
            })
            ->sortByDesc('assets_count')
            ->take(6)
            ->values();
        $activityFeed = $this->activityFeed();

        return view('dashboard', [
            'stats' => [
                'Baze' => Location::where('type', 'base')->count(),
                'Santiere active' => Location::where('type', 'site')->where('active', true)->count(),
                'Articole' => CatalogItem::where('active', true)->count(),
                'Asset-uri QR' => TrackedAsset::count(),
                'In tranzit' => Transfer::where('status', 'in_transit')->count(),
                'Cereri sofer' => DriverRequest::whereIn('status', ['open', 'assigned'])->count(),
                'Alerte' => Transfer::where('status', 'in_transit')->where('dispatched_at', '<', now()->subHours(12))->count()
                    + TrackedAsset::where('status', 'lost')->count(),
                'Receptii luna' => SupplierReception::where('received_at', '>=', now()->subDays(30))->count(),
            ],
            'assetStatusCounts' => $assetStatusCounts,
            'transferStatusCounts' => $transferStatusCounts,
            'consumptionTrend' => $consumptionTrend,
            'topLocations' => $topLocations,
            'activityFeed' => $activityFeed,
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

    private function activityFeed(): Collection
    {
        $transfers = Transfer::with(['sourceLocation', 'destinationLocation'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Transfer $transfer) => [
                'icon' => 'fa-right-left',
                'type' => 'Transfer',
                'title' => $transfer->number,
                'description' => ($transfer->sourceLocation?->code ?? '-') . ' -> ' . ($transfer->destinationLocation?->code ?? '-'),
                'date' => $transfer->created_at,
                'status' => $transfer->status,
            ]);

        $receptions = SupplierReception::with('location')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (SupplierReception $reception) => [
                'icon' => 'fa-receipt',
                'type' => 'Receptie',
                'title' => $reception->number,
                'description' => $reception->location?->code ?? '-',
                'date' => $reception->received_at ?? $reception->created_at,
                'status' => $reception->status,
            ]);

        $custody = CustodyTransfer::with(['trackedAsset', 'toUser'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (CustodyTransfer $transfer) => [
                'icon' => 'fa-qrcode',
                'type' => 'Custodie',
                'title' => $transfer->trackedAsset?->asset_code ?? $transfer->qr_token,
                'description' => $transfer->toUser?->name ?? '-',
                'date' => $transfer->accepted_at ?? $transfer->created_at,
                'status' => $transfer->status,
            ]);

        return $transfers
            ->merge($receptions)
            ->merge($custody)
            ->sortByDesc('date')
            ->take(10)
            ->values();
    }
}
