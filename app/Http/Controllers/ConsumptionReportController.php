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
use Illuminate\Support\Collection;
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
            'modifier',
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
            'canCorrect' => $this->canCorrect($user),
        ]);
    }

    public function create(Request $request): View
    {
        $locations = $this->writeLocations($request->user())->orderBy('type')->orderBy('name')->get();
        abort_if($locations->isEmpty(), 403);

        return view('consumption-reports.create', $this->formData($locations));
    }

    public function edit(Request $request, ConsumptionReport $consumptionReport): View
    {
        $this->authorizeCorrection($request->user());
        abort_unless($this->locationAccess->canView($request->user(), (int) $consumptionReport->location_id), 403);
        $consumptionReport->load([
            'location',
            'lines.catalogItem',
            'revisions' => fn ($query) => $query->with('changedBy')->latest('revision'),
        ]);

        return view('consumption-reports.create', $this->formData(
            $this->writeLocations($request->user())->orderBy('type')->orderBy('name')->get(),
            $consumptionReport,
        ));
    }

    public function stockOptions(Request $request): JsonResponse
    {
        $writeLocationIds = $this->writeLocations($request->user())->pluck('locations.id');
        $data = $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->whereIn('id', $writeLocationIds))],
            'report_id' => ['nullable', 'integer', 'exists:consumption_reports,id'],
        ]);

        $report = ! empty($data['report_id'])
            ? ConsumptionReport::findOrFail($data['report_id'])
            : null;
        if ($report) {
            $this->authorizeCorrection($request->user());
        }

        $virtualQuantities = collect();
        if ($report && (int) $report->location_id === (int) $data['location_id']) {
            $virtualQuantities = $report->lines()
                ->selectRaw('catalog_item_id, SUM(quantity) as quantity')
                ->groupBy('catalog_item_id')
                ->pluck('quantity', 'catalog_item_id');
        }

        $items = StockLevel::query()
            ->where('location_id', $data['location_id'])
            ->whereHas('catalogItem', fn ($query) => $query
                ->where('active', true)
                ->where('tracking_type', 'quantity'))
            ->with('catalogItem')
            ->get()
            ->map(function (StockLevel $stock) use ($virtualQuantities): array {
                $available = round(
                    (float) $stock->quantity + (float) ($virtualQuantities[$stock->catalog_item_id] ?? 0),
                    3,
                );

                return [
                    'id' => $stock->catalog_item_id,
                    'name' => $stock->catalogItem->name,
                    'sku' => $stock->catalogItem->sku,
                    'unit' => $stock->catalogItem->unit,
                    'available' => number_format($available, 3, '.', ''),
                ];
            })
            ->filter(fn (array $item) => (float) $item['available'] > 0.0005)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json(['items' => $items]);
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
            'report_id' => ['nullable', 'integer', 'exists:consumption_reports,id'],
        ]);

        $virtualLotQuantities = [];
        if (! empty($data['report_id'])) {
            $report = ConsumptionReport::findOrFail($data['report_id']);
            $this->authorizeCorrection($request->user());
            $virtualLotQuantities = $ledger->virtualCorrectionLotQuantities(
                $report,
                (int) $data['location_id'],
                (int) $data['catalog_item_id'],
            );
        }

        $proposal = $ledger->suggestConsumptionAllocations(
            (int) $data['location_id'],
            (int) $data['catalog_item_id'],
            (float) $data['quantity'],
            $virtualLotQuantities,
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
        $legacyInput = ! $request->has('lines') && $request->filled('catalog_item_id');
        $this->normalizeLegacyInput($request);
        try {
            $data = $this->validatedData($request);
        } catch (ValidationException $exception) {
            if (! $legacyInput) {
                throw $exception;
            }

            $messages = [];
            foreach ($exception->errors() as $key => $errors) {
                $legacyKey = match ($key) {
                    'lines.0.catalog_item_id' => 'catalog_item_id',
                    'lines.0.quantity' => 'quantity',
                    default => str_starts_with($key, 'lines.0.allocations') ? substr($key, 8) : $key,
                };
                $messages[$legacyKey] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }

        DB::transaction(function () use ($data, $request, $ledger, $legacyInput): void {
            $location = $this->writeLocations($request->user())
                ->lockForUpdate()
                ->findOrFail($data['location_id']);
            $report = ConsumptionReport::create([
                'number' => 'CS-'.now()->format('Ymd-His').'-'.str()->upper(str()->random(4)),
                'location_id' => $location->id,
                'reported_by' => $request->user()->id,
                'status' => 'posted',
                'revision' => 1,
                'reported_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->postLines($report, $data['lines'], $location->id, $request->user(), $ledger, $legacyInput);
        });

        return redirect()->route('consumption-reports.index')
            ->with('status', 'Consumul a fost înregistrat și stocul a fost actualizat.');
    }

    public function update(
        Request $request,
        ConsumptionReport $consumptionReport,
        StockLedgerService $ledger,
    ): RedirectResponse {
        $this->authorizeCorrection($request->user());
        $data = $this->validatedData($request, true);

        DB::transaction(function () use ($data, $request, $consumptionReport, $ledger): void {
            $report = ConsumptionReport::query()->lockForUpdate()->findOrFail($consumptionReport->id);
            $report->load(['lines.catalogItem']);
            $before = $this->snapshot($report);
            $reason = trim($data['correction_reason']);

            $ledger->reverseConsumption($report, $request->user(), $reason);
            $report->lines()->update(['superseded_at' => now()]);

            $revision = (int) $report->revision + 1;
            $location = $this->writeLocations($request->user())
                ->lockForUpdate()
                ->findOrFail($data['location_id']);
            $report->update([
                'location_id' => $location->id,
                'status' => 'modified',
                'revision' => $revision,
                'modified_by' => $request->user()->id,
                'modified_at' => now(),
                'notes' => $data['notes'] ?? null,
                'correction_reason' => $reason,
            ]);
            $report->unsetRelation('lines');
            $this->postLines($report, $data['lines'], $location->id, $request->user(), $ledger);
            $report->unsetRelation('lines');
            $report->load('lines.catalogItem');

            $report->revisions()->create([
                'revision' => $revision,
                'before_data' => $before,
                'after_data' => $this->snapshot($report),
                'reason' => $reason,
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
        });

        return redirect()->route('consumption-reports.index')
            ->with('status', 'Consumul a fost corectat. Versiunea anterioară și mișcările de stoc au rămas în istoric.');
    }

    /** @param Collection<int, Location> $locations */
    private function formData($locations, ?ConsumptionReport $report = null): array
    {
        return [
            'report' => $report,
            'locations' => $locations,
            'allocationProposalUrl' => route('consumption-reports.allocation-proposal'),
            'stockOptionsUrl' => route('consumption-reports.stock-options'),
        ];
    }

    private function validatedData(Request $request, bool $correction = false): array
    {
        $writeLocationIds = $this->writeLocations($request->user())->pluck('locations.id');

        return $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where(fn ($query) => $query
                ->where('active', true)
                ->whereIn('id', $writeLocationIds))],
            'notes' => ['nullable', 'string'],
            'correction_reason' => [$correction ? 'required' : 'nullable', 'nullable', 'string', 'min:5'],
            'lines' => ['required', 'array', 'min:1', 'max:30'],
            'lines.*.catalog_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('tracking_type', 'quantity')),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'lines.*.notes' => ['nullable', 'string'],
            'lines.*.allocations' => ['nullable', 'array', 'max:50'],
            'lines.*.allocations.*.inventory_lot_id' => ['required', 'integer', 'distinct', 'exists:inventory_lots,id'],
            'lines.*.allocations.*.quantity' => ['required', 'numeric', 'min:0'],
        ], [
            'lines.*.catalog_item_id.distinct' => 'Același material poate apărea o singură dată în raport.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function postLines(
        ConsumptionReport $report,
        array $lines,
        int $locationId,
        User $actor,
        StockLedgerService $ledger,
        bool $legacyInput = false,
    ): void {
        foreach (array_values($lines) as $index => $lineData) {
            $item = CatalogItem::where('active', true)
                ->where('tracking_type', 'quantity')
                ->findOrFail($lineData['catalog_item_id']);
            $stock = StockLevel::where('location_id', $locationId)
                ->where('catalog_item_id', $item->id)
                ->lockForUpdate()
                ->first();
            if ($stock === null || (float) $stock->quantity + 0.0005 < (float) $lineData['quantity']) {
                throw ValidationException::withMessages([
                    $legacyInput ? 'quantity' : "lines.{$index}.quantity" => 'Cantitatea depășește stocul disponibil pentru această locație.',
                ]);
            }

            $line = $report->lines()->create([
                'revision' => $report->revision,
                'catalog_item_id' => $item->id,
                'quantity' => $lineData['quantity'],
                'unit' => $item->unit,
                'notes' => $lineData['notes'] ?? null,
            ]);
            $requestedAllocations = array_key_exists('allocations', $lineData)
                ? array_values($lineData['allocations'])
                : null;
            $ledger->postConsumption(
                $line->load('consumptionReport'),
                $locationId,
                $actor,
                $requestedAllocations,
            );
        }
    }

    private function normalizeLegacyInput(Request $request): void
    {
        if ($request->has('lines') || ! $request->filled('catalog_item_id')) {
            return;
        }

        $line = [
            'catalog_item_id' => $request->input('catalog_item_id'),
            'quantity' => $request->input('quantity'),
            'notes' => $request->input('notes'),
        ];
        if ($request->has('allocations')) {
            $line['allocations'] = $request->input('allocations');
        }
        $request->merge(['lines' => [$line]]);
    }

    /** @return array<string, mixed> */
    private function snapshot(ConsumptionReport $report): array
    {
        $report->loadMissing('lines.catalogItem');

        return [
            'revision' => (int) $report->revision,
            'status' => $report->status,
            'location_id' => (int) $report->location_id,
            'notes' => $report->notes,
            'lines' => $report->lines->map(fn ($line) => [
                'id' => $line->id,
                'catalog_item_id' => (int) $line->catalog_item_id,
                'catalog_item_name' => $line->catalogItem?->name,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'notes' => $line->notes,
            ])->values()->all(),
        ];
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

    private function canCorrect(User $user): bool
    {
        return $user->active && $user->hasAnyRole(['super-admin', 'admin']);
    }

    private function authorizeCorrection(User $user): void
    {
        abort_unless($this->canCorrect($user), 403);
    }
}
