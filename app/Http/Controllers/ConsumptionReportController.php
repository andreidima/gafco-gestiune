<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\ConsumptionReport;
use App\Models\Location;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\StockLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConsumptionReportController extends Controller
{
    public function __construct(private readonly LocationAccessService $locationAccess) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($user);
        $reports = ConsumptionReport::with([
            'location',
            'reporter',
            'lines' => fn ($query) => $query->with('catalogItem')->oldest('id')->limit(2),
        ])->withCount('lines')
            ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('location_id', $visibleLocationIds));

        return view('consumption-reports.index', [
            'reports' => $reports
                ->when($request->search, fn ($query, $search) => $query->where('number', 'like', "%{$search}%"))
                ->when($request->location_id, fn ($query, $id) => $query->where('location_id', $id))
                ->when($request->catalog_item_id, fn ($query, $id) => $query->whereHas('lines', fn ($line) => $line->where('catalog_item_id', $id)))
                ->when($request->date_from, fn ($query, $date) => $query->whereDate('reported_at', '>=', $date))
                ->when($request->date_to, fn ($query, $date) => $query->whereDate('reported_at', '<=', $date))
                ->latest('reported_at')
                ->paginate(20)
                ->withQueryString(),
            'locations' => $this->locationAccess->visibleLocations($user)->orderBy('type')->orderBy('name')->get(),
            'items' => CatalogItem::where('tracking_type', 'quantity')->where('active', true)->orderBy('name')->get(),
            'totalReports' => ConsumptionReport::query()
                ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('location_id', $visibleLocationIds))
                ->count(),
            'canCreate' => $this->canCreate($user),
        ]);
    }

    public function create(Request $request): View
    {
        $locations = $this->writeLocations($request->user())->orderBy('type')->orderBy('name')->get();
        abort_if($locations->isEmpty(), 403);

        return view('consumption-reports.create', [
            'locations' => $locations,
            'items' => CatalogItem::where('tracking_type', 'quantity')->where('active', true)->orderBy('name')->get(),
            'allocationProposalUrl' => route('consumption-reports.allocation-proposal'),
        ]);
    }

    public function allocationProposal(Request $request, StockLedgerService $ledger): JsonResponse
    {
        $writeLocationIds = $this->writeLocations($request->user())->pluck('locations.id');
        $data = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->whereIn('id', $writeLocationIds))],
            'catalog_item_id' => ['required', Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->where('tracking_type', 'quantity'))],
            'quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        $proposal = $ledger->suggestConsumptionAllocations(
            (int) $data['location_id'],
            (int) $data['catalog_item_id'],
            (float) $data['quantity'],
        );

        return response()->json([
            'allocations' => $proposal->map(fn (array $allocation) => [
                'inventory_lot_id' => $allocation['lot']->id,
                'label' => $allocation['lot']->lot_code
                    ?: ($allocation['lot']->document_number
                        ?: ($allocation['lot']->is_opening_balance ? 'Sold inițial' : 'Fără cod lot')),
                'supplier' => $allocation['lot']->supplier?->name,
                'received_at' => $allocation['lot']->received_at?->format('d.m.Y'),
                'expires_at' => $allocation['lot']->expires_at?->format('d.m.Y'),
                'available' => number_format($allocation['available'], 3, '.', ''),
                'quantity' => number_format($allocation['quantity'], 3, '.', ''),
            ])->values(),
        ]);
    }

    public function store(Request $request, StockLedgerService $ledger): RedirectResponse
    {
        $writeLocationIds = $this->writeLocations($request->user())->pluck('locations.id');
        $data = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->whereIn('id', $writeLocationIds))],
            'catalog_item_id' => ['required', Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->where('tracking_type', 'quantity'))],
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string'],
            'allocations' => ['nullable', 'array', 'max:50'],
            'allocations.*.inventory_lot_id' => ['required', 'integer', 'distinct', 'exists:inventory_lots,id'],
            'allocations.*.quantity' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $request, $ledger) {
            $location = $this->writeLocations($request->user())
                ->lockForUpdate()
                ->findOrFail($data['location_id']);
            $item = CatalogItem::where('active', true)
                ->where('tracking_type', 'quantity')
                ->findOrFail($data['catalog_item_id']);
            $stock = StockLevel::where('location_id', $location->id)
                ->where('catalog_item_id', $item->id)
                ->lockForUpdate()
                ->first();

            if ($stock === null || (float) $stock->quantity < (float) $data['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Cantitatea depaseste stocul disponibil pentru aceasta locatie.',
                ]);
            }

            $report = ConsumptionReport::create([
                'number' => 'CS-'.now()->format('Ymd-His').'-'.str()->upper(str()->random(4)),
                'location_id' => $location->id,
                'reported_by' => $request->user()->id,
                'status' => 'posted',
                'reported_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $line = $report->lines()->create([
                'catalog_item_id' => $item->id,
                'quantity' => $data['quantity'],
                'unit' => $item->unit,
                'notes' => $data['notes'] ?? null,
            ]);
            $requestedAllocations = array_key_exists('allocations', $data)
                ? array_values($data['allocations'])
                : null;
            $ledger->postConsumption(
                $line->load('consumptionReport'),
                $location->id,
                $request->user(),
                $requestedAllocations,
            );
        });

        return redirect()->route('consumption-reports.index')->with('status', 'Consumul a fost inregistrat si stocul a fost actualizat.');
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
