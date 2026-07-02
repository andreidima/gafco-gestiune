<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\Location;
use App\Models\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConsumptionReportController extends Controller
{
    public function index(): View
    {
        return view('consumption-reports.index', [
            'reports' => ConsumptionReport::with(['location', 'reporter', 'lines.catalogItem'])
                ->latest('reported_at')
                ->paginate(20),
            'locations' => Location::where('active', true)->orderBy('type')->orderBy('name')->get(),
            'items' => CatalogItem::where('tracking_type', 'quantity')->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'exists:locations,id'],
            'catalog_item_id' => ['required', 'exists:catalog_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $item = CatalogItem::findOrFail($data['catalog_item_id']);
            $report = ConsumptionReport::create([
                'number' => 'CS-'.now()->format('Ymd-His'),
                'location_id' => $data['location_id'],
                'reported_by' => $request->user()->id,
                'status' => 'posted',
                'reported_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $report->lines()->create([
                'catalog_item_id' => $item->id,
                'quantity' => $data['quantity'],
                'unit' => $item->unit,
                'notes' => $data['notes'] ?? null,
            ]);

            $stock = StockLevel::firstOrCreate(
                ['location_id' => $data['location_id'], 'catalog_item_id' => $item->id],
                ['quantity' => 0]
            );
            $stock->update(['quantity' => max(0, (float) $stock->quantity - (float) $data['quantity'])]);
        });

        return back()->with('status', 'Consumul a fost inregistrat si stocul a fost actualizat.');
    }
}
