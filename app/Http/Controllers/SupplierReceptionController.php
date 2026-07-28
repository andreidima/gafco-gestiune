<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\SupplierReception;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\StockLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierReceptionController extends Controller
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($user);
        $receptions = SupplierReception::with([
            'location',
            'supplier',
            'receiver',
            'lines' => fn ($query) => $query->with('catalogItem')->oldest('id')->limit(2),
        ])->withCount('lines')
            ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('location_id', $visibleLocationIds));

        return view('supplier-receptions.index', [
            'receptions' => $receptions
                ->when($request->search, fn ($query, $search) => $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('number', 'like', "%{$search}%")->orWhere('document_number', 'like', "%{$search}%");
                }))
                ->when($request->location_id, fn ($query, $id) => $query->where('location_id', $id))
                ->when($request->supplier_id, fn ($query, $id) => $query->where('supplier_id', $id))
                ->when($request->catalog_item_id, fn ($query, $id) => $query->whereHas('lines', fn ($line) => $line->where('catalog_item_id', $id)))
                ->when($request->document_type, fn ($query, $type) => $query->where('document_type', $type))
                ->when($request->date_from, fn ($query, $date) => $query->whereDate('received_at', '>=', $date))
                ->when($request->date_to, fn ($query, $date) => $query->whereDate('received_at', '<=', $date))
                ->latest()->paginate(20)->withQueryString(),
            'locations' => $this->locationAccess->visibleLocations($user)->orderBy('name')->get(),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('active', true)->where('tracking_type', 'quantity')->orderBy('name')->get(),
            'totalReceptions' => SupplierReception::query()
                ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('location_id', $visibleLocationIds))
                ->count(),
            'canCreate' => $this->canCreate($user),
        ]);
    }

    public function create(Request $request): View
    {
        $locations = $this->writeLocations($request->user())->orderBy('name')->get();
        abort_if($locations->isEmpty(), 403);

        return view('supplier-receptions.create', [
            'locations' => $locations,
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('active', true)->where('tracking_type', 'quantity')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, StockLedgerService $ledger): RedirectResponse
    {
        $writeLocationIds = $this->writeLocations($request->user())->pluck('locations.id');
        $data = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->whereIn('id', $writeLocationIds))],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('active', true)],
            'document_type' => ['required', 'in:aviz,factura'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'catalog_item_id' => ['required', Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->where('tracking_type', 'quantity'))],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($data, $request, $ledger) {
            $location = $this->writeLocations($request->user())
                ->lockForUpdate()
                ->findOrFail($data['location_id']);
            $item = CatalogItem::where('active', true)
                ->where('tracking_type', 'quantity')
                ->findOrFail($data['catalog_item_id']);
            $reception = SupplierReception::create([
                'number' => 'RF-'.now()->format('Ymd-His'),
                'location_id' => $location->id,
                'supplier_id' => $data['supplier_id'] ?? null,
                'received_by' => $request->user()->id,
                'document_type' => $data['document_type'],
                'document_number' => $data['document_number'] ?? null,
                'status' => 'posted',
                'received_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);
            $line = $reception->lines()->create([
                'catalog_item_id' => $item->id,
                'quantity' => $data['quantity'],
                'unit' => $item->unit,
            ]);
            $ledger->postReception($line, $request->user());
        });

        return redirect()->route('supplier-receptions.index')->with('status', 'Receptia a fost salvata.');
    }

    private function writeLocations(User $user): Builder
    {
        return Location::query()
            ->where('active', true)
            ->when(! $user->isOperationsAdmin(), fn (Builder $query) => $query
                ->whereIn('id', $this->managedActiveLocationIds($user)));
    }

    /** @return array<int, int> */
    private function managedActiveLocationIds(User $user): array
    {
        return $user->activeManagedLocations()
            ->where('locations.active', true)
            ->pluck('locations.id')
            ->all();
    }

    private function canCreate(User $user): bool
    {
        if (! ($user->isOperationsAdmin() || $user->hasAnyRole(['sef-santier', 'gestionar-baza']))) {
            return false;
        }

        return $this->writeLocations($user)->exists();
    }
}
