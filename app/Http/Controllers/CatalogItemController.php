<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Services\LocationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogItemController extends Controller
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function index(Request $request): View
    {
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($request->user());
        $stockScope = fn ($query) => $query
            ->when($visibleLocationIds !== null, fn ($visible) => $visible->whereIn('location_id', $visibleLocationIds));

        return view('catalog-items.index', [
            'items' => CatalogItem::query()
                ->withCount([
                    'trackedAssets',
                    'trackedAssets as available_tracked_assets_count' => fn ($query) => $query->where('status', 'available'),
                    'trackedAssets as in_use_tracked_assets_count' => fn ($query) => $query->where('status', 'in_use'),
                    'trackedAssets as attention_tracked_assets_count' => fn ($query) => $query->where(function ($attentionQuery) {
                        $attentionQuery->whereIn('status', ['maintenance', 'lost'])
                            ->orWhereIn('condition', ['damaged', 'needs_service']);
                    }),
                    'stockLevels' => $stockScope,
                ])
                ->withSum(['stockLevels' => $stockScope], 'quantity')
                ->when($request->category, fn ($query, $category) => $query->where('category', $category))
                ->when($request->tracking_type, fn ($query, $trackingType) => $query->where('tracking_type', $trackingType))
                ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
                ->when($request->search, fn ($query, $search) => $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereRaw('UPPER(sku) LIKE ?', ['%'.Str::upper($search).'%'])
                        ->orWhere('barcode', 'like', "%{$search}%");
                }))
                ->orderBy('category')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'totalItems' => CatalogItem::count(),
        ]);
    }

    public function create(): View
    {
        return view('catalog-items.form', ['item' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        CatalogItem::create($this->validatedData($request) + ['active' => $request->boolean('active', true)]);

        return redirect()->route('catalog-items.index')->with('status', 'Articolul a fost adaugat.');
    }

    public function edit(CatalogItem $catalogItem): View
    {
        return view('catalog-items.form', ['item' => $catalogItem]);
    }

    public function update(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        $catalogItem->update($this->validatedData($request, $catalogItem) + ['active' => $request->boolean('active')]);

        return redirect()->route('catalog-items.index')->with('status', 'Articolul a fost actualizat.');
    }

    private function validatedData(Request $request, ?CatalogItem $item = null): array
    {
        $request->merge([
            'sku' => $request->filled('sku') ? Str::upper(trim((string) $request->input('sku'))) : null,
        ]);

        return $request->validate([
            'category' => ['required', 'in:material,equipment,tool'],
            'tracking_type' => ['required', 'in:quantity,serialized'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('catalog_items', 'sku')->ignore($item)],
            'barcode' => ['nullable', 'string', 'max:120', Rule::unique('catalog_items', 'barcode')->ignore($item)],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:24'],
            'description' => ['nullable', 'string'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
