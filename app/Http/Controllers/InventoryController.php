<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\InventoryLot;
use App\Models\StockMovement;
use App\Models\UserPreference;
use App\Services\LocationAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canViewInventory(), 403);

        $preference = UserPreference::where('user_id', $user->id)
            ->where('key', 'inventory.index')
            ->first()?->value ?? [];
        $usingRequestFilters = $request->boolean('filters_submitted');
        $filters = [
            'search' => $usingRequestFilters ? trim((string) $request->input('search')) : (string) data_get($preference, 'filters.search', ''),
            'location_id' => $usingRequestFilters ? $request->integer('location_id') : (int) data_get($preference, 'filters.location_id', 0),
            'hide_zero' => $usingRequestFilters ? $request->boolean('hide_zero') : (bool) data_get($preference, 'filters.hide_zero', false),
        ];
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($user);
        $locations = $this->locationAccess->visibleLocations($user)->orderBy('type')->orderBy('name')->get();

        if ($filters['location_id'] && ! $this->locationAccess->canView($user, $filters['location_id'])) {
            abort(403);
        }

        $stockScope = function ($query) use ($visibleLocationIds, $filters): void {
            $query
                ->when($visibleLocationIds !== null, fn (Builder $visible) => $visible->whereIn('location_id', $visibleLocationIds))
                ->when($filters['location_id'], fn (Builder $selected) => $selected->where('location_id', $filters['location_id']));
        };

        $items = CatalogItem::query()
            ->where('tracking_type', 'quantity')
            ->where('active', true)
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(function (Builder $matching) use ($search): void {
                $matching->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            }))
            ->when($filters['hide_zero'], fn (Builder $query) => $query->whereHas('stockLevels', function ($stock) use ($stockScope): void {
                $stockScope($stock);
                $stock->where('quantity', '>', 0);
            }))
            ->withSum(['stockLevels as visible_stock_quantity' => $stockScope], 'quantity')
            ->withCount(['stockLevels as visible_stock_locations_count' => function ($stock) use ($stockScope): void {
                $stockScope($stock);
                $stock->where('quantity', '>', 0);
            }])
            ->with([
                'stockLevels' => function ($stock) use ($stockScope): void {
                    $stockScope($stock);
                    $stock->with('location')->orderByDesc('quantity');
                },
                'inventoryLots' => function ($lots) use ($visibleLocationIds, $filters): void {
                    $lots
                        ->whereHas('balances', function ($balances) use ($visibleLocationIds, $filters): void {
                            $balances->where('quantity', '>', 0)
                                ->when($visibleLocationIds !== null, fn (Builder $visible) => $visible->whereIn('location_id', $visibleLocationIds))
                                ->when($filters['location_id'], fn (Builder $selected) => $selected->where('location_id', $filters['location_id']));
                        })
                        ->with([
                            'supplier',
                            'balances' => function ($balances) use ($visibleLocationIds, $filters): void {
                                $balances->where('quantity', '>', 0)
                                    ->when($visibleLocationIds !== null, fn (Builder $visible) => $visible->whereIn('location_id', $visibleLocationIds))
                                    ->when($filters['location_id'], fn (Builder $selected) => $selected->where('location_id', $filters['location_id']))
                                    ->with('location')
                                    ->orderByDesc('quantity');
                            },
                        ])
                        ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('expires_at')
                        ->orderBy('received_at');
                },
            ])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $columns = collect(data_get($preference, 'columns', $this->defaultColumns($user)))
            ->intersect($this->allowedColumns($user))
            ->values()
            ->all();
        if ($columns === []) {
            $columns = $this->defaultColumns($user);
        }
        $density = data_get($preference, 'density') === 'comfortable' ? 'comfortable' : 'compact';

        return view('inventory.index', [
            'items' => $items,
            'locations' => $locations,
            'filters' => $filters,
            'columns' => $columns,
            'columnOptions' => $this->columnOptions($user),
            'density' => $density,
            'canViewCommercial' => $user->canViewCommercialInventory(),
            'totalMaterials' => CatalogItem::where('tracking_type', 'quantity')->where('active', true)->count(),
        ]);
    }

    public function show(Request $request, CatalogItem $catalogItem): View
    {
        $user = $request->user();
        abort_unless(
            $user->canViewInventory() && $catalogItem->tracking_type === 'quantity',
            403
        );

        $visibleLocationIds = $this->locationAccess->visibleLocationIds($user);
        $locationId = $request->integer('location_id');
        if ($locationId && ! $this->locationAccess->canView($user, $locationId)) {
            abort(403);
        }

        $balanceScope = function ($query) use ($visibleLocationIds, $locationId): void {
            $query
                ->when($visibleLocationIds !== null, fn (Builder $visible) => $visible->whereIn('location_id', $visibleLocationIds))
                ->when($locationId, fn (Builder $selected) => $selected->where('location_id', $locationId));
        };

        $lots = InventoryLot::query()
            ->where('catalog_item_id', $catalogItem->id)
            ->where(function (Builder $query) use ($balanceScope): void {
                $query->whereHas('balances', $balanceScope)
                    ->orWhereHas('movements', $balanceScope);
            })
            ->with([
                'supplier',
                'balances' => function ($balances) use ($balanceScope): void {
                    $balanceScope($balances);
                    $balances->with('location')->orderByDesc('quantity');
                },
            ])
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('received_at')
            ->get();

        $movements = StockMovement::query()
            ->where('catalog_item_id', $catalogItem->id)
            ->when($visibleLocationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $visibleLocationIds))
            ->when($locationId, fn (Builder $query) => $query->where('location_id', $locationId))
            ->with(['location', 'lot.supplier', 'poster'])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('inventory.show', [
            'item' => $catalogItem,
            'lots' => $lots,
            'movements' => $movements,
            'locations' => $this->locationAccess->visibleLocations($user)->orderBy('type')->orderBy('name')->get(),
            'selectedLocationId' => $locationId,
            'canViewCommercial' => $user->canViewCommercialInventory(),
        ]);
    }

    /** @return array<int, string> */
    private function allowedColumns($user): array
    {
        return array_keys($this->columnOptions($user));
    }

    /** @return array<string, string> */
    private function columnOptions($user): array
    {
        $columns = [
            'locations' => 'Locații',
            'lots' => 'Loturi',
            'supplier' => 'Furnizor',
            'document' => 'Document',
            'received_at' => 'Data intrării',
            'expiration' => 'Expirare',
        ];
        if ($user->canViewCommercialInventory()) {
            $columns['price'] = 'Preț';
        }

        return $columns;
    }

    /** @return array<int, string> */
    private function defaultColumns($user): array
    {
        return array_values(array_intersect(
            ['locations', 'lots', 'supplier', 'document', 'expiration'],
            $this->allowedColumns($user),
        ));
    }
}
