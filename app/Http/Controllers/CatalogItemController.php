<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogItemController extends Controller
{
    public function index(Request $request): View
    {
        return view('catalog-items.index', [
            'items' => CatalogItem::withCount('trackedAssets')
                ->when($request->category, fn ($query, $category) => $query->where('category', $category))
                ->when($request->tracking_type, fn ($query, $trackingType) => $query->where('tracking_type', $trackingType))
                ->when($request->search, fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                }))
                ->orderBy('category')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'stats' => [
                'total' => CatalogItem::count(),
                'materials' => CatalogItem::where('category', 'material')->count(),
                'equipment' => CatalogItem::where('category', 'equipment')->count(),
                'tools' => CatalogItem::where('category', 'tool')->count(),
                'serialized' => CatalogItem::where('tracking_type', 'serialized')->count(),
                'quantity' => CatalogItem::where('tracking_type', 'quantity')->count(),
                'stock_positions' => StockLevel::where('quantity', '>', 0)->count(),
            ],
            'topItems' => CatalogItem::withCount('trackedAssets')
                ->where('tracking_type', 'serialized')
                ->orderByDesc('tracked_assets_count')
                ->limit(5)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CatalogItem::create($request->validate([
            'category' => ['required', 'in:material,equipment,tool'],
            'tracking_type' => ['required', 'in:quantity,serialized'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:catalog_items,sku'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:24'],
        ]) + ['active' => true]);

        return back()->with('status', 'Articolul a fost adaugat.');
    }
}
