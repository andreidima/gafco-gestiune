<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\NegotiatedOrder;
use App\Models\ReceptionDocument;
use App\Models\ReceptionIntake;
use App\Models\Supplier;
use App\Models\SupplierReception;
use App\Models\User;
use App\Services\LocationAccessService;
use App\Services\ReceptionAccessService;
use App\Services\ReceptionDocumentService;
use App\Services\StockLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierReceptionController extends Controller
{
    private const CURRENCIES = ['RON', 'EUR', 'USD', 'GBP', 'CNY'];

    public function __construct(
        private readonly LocationAccessService $locationAccess,
        private readonly ReceptionAccessService $receptionAccess,
        private readonly ReceptionDocumentService $documents,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewIntakes = $user->hasAnyRole([
            'super-admin', 'admin', 'dispecer', 'manager',
            'sef-santier', 'gestionar-baza', 'muncitor',
        ]);
        $visibleLocationIds = $this->locationAccess->visibleLocationIds($user);
        $receptions = $this->receptionAccess->visibleReceptions($user)
            ->with([
                'location',
                'supplier',
                'receiver',
                'lines' => fn ($query) => $query->with('catalogItem')->oldest('id')->limit(2),
            ])
            ->withCount(['lines', 'documents']);

        return view('supplier-receptions.index', [
            'receptions' => $receptions
                ->when($request->search, fn ($query, $search) => $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('number', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%");
                }))
                ->when($request->location_id, fn ($query, $id) => $query->where('location_id', $id))
                ->when($request->supplier_id, fn ($query, $id) => $query->where('supplier_id', $id))
                ->when($request->catalog_item_id, fn ($query, $id) => $query
                    ->whereHas('lines', fn ($line) => $line->where('catalog_item_id', $id)))
                ->when($request->document_type, fn ($query, $type) => $query->where('document_type', $type))
                ->when($request->date_from, fn ($query, $date) => $query->whereDate('received_at', '>=', $date))
                ->when($request->date_to, fn ($query, $date) => $query->whereDate('received_at', '<=', $date))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'locations' => $this->locationAccess->visibleLocations($user)->orderBy('name')->get(),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('active', true)->where('tracking_type', 'quantity')->orderBy('name')->get(),
            'totalReceptions' => SupplierReception::query()
                ->when($visibleLocationIds !== null, fn ($query) => $query->whereIn('location_id', $visibleLocationIds))
                ->count(),
            'openIntakeCount' => $canViewIntakes
                ? $this->receptionAccess->visibleIntakes($user)->where('status', 'created')->count()
                : 0,
            'canViewIntakes' => $canViewIntakes,
            'canCreate' => $this->canCreate($user),
            'canUploadDocuments' => $user->hasPermissionTo('reception-documents.upload'),
        ]);
    }

    public function show(Request $request, SupplierReception $supplierReception): View
    {
        abort_unless($this->receptionAccess->canViewReception($request->user(), $supplierReception), 403);
        $relations = [
            'location',
            'supplier',
            'receiver',
            'lines.catalogItem',
            'lines.inventoryLot',
            'documents.uploader',
            'intakes',
        ];
        if (Schema::hasColumn('supplier_receptions', 'negotiated_order_id')) {
            $relations[] = 'negotiatedOrder';
        }
        $supplierReception->load($relations);

        return view('supplier-receptions.show', [
            'reception' => $supplierReception,
            'canViewCommercial' => $request->user()->canViewCommercialInventory(),
            'canEdit' => $this->receptionAccess->canEditAllReceptionDetails($request->user(), $supplierReception)
                || $this->receptionAccess->canEditReceptionExpiration($request->user(), $supplierReception),
        ]);
    }

    public function create(Request $request): View
    {
        $locations = $this->writeLocations($request->user())->orderBy('name')->get();
        abort_if($locations->isEmpty(), 403);
        $intake = null;
        $negotiatedOrder = null;

        if ($request->filled('intake_id')) {
            $intake = ReceptionIntake::with(['location', 'submitter', 'documents'])
                ->findOrFail($request->integer('intake_id'));
            abort_unless($this->receptionAccess->canProcessIntake($request->user(), $intake), 403);
        }

        if ($request->filled('negotiated_order_id')) {
            abort_if($intake, 422);
            abort_unless($request->user()->hasAnyRole(['super-admin', 'admin']), 403);
            $negotiatedOrder = NegotiatedOrder::with(['location', 'supplier', 'lines.catalogItem'])
                ->findOrFail($request->integer('negotiated_order_id'));
            abort_unless($negotiatedOrder->isCreated(), 409, 'Comanda este deja închisă.');
        }

        return view('supplier-receptions.create', [
            'locations' => $locations,
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('active', true)->where('tracking_type', 'quantity')->orderBy('name')->get(),
            'currencies' => self::CURRENCIES,
            'documentTypes' => ReceptionDocument::TYPE_LABELS,
            'intake' => $intake,
            'negotiatedOrder' => $negotiatedOrder,
        ]);
    }

    public function store(Request $request, StockLedgerService $ledger): RedirectResponse
    {
        $user = $request->user();
        $writeLocationIds = $this->writeLocations($user)->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $data = $this->validateCreatePayload($request, $writeLocationIds);
        $intake = null;
        if (! empty($data['intake_id'])) {
            $intake = ReceptionIntake::findOrFail($data['intake_id']);
            abort_unless($this->receptionAccess->canProcessIntake($user, $intake), 403);
            abort_unless((int) $intake->location_id === (int) $data['location_id'], 422);
        }
        $negotiatedOrder = null;
        if (! empty($data['negotiated_order_id'])) {
            abort_unless($user->hasAnyRole(['super-admin', 'admin']), 403);
            $negotiatedOrder = NegotiatedOrder::findOrFail($data['negotiated_order_id']);
            abort_unless($negotiatedOrder->isCreated(), 409, 'Comanda este deja închisă.');
        }

        $storedDocuments = collect();
        try {
            $reception = DB::transaction(function () use (
                $data,
                $user,
                $ledger,
                $intake,
                $negotiatedOrder,
                &$storedDocuments,
            ): SupplierReception {
                $location = $this->writeLocations($user)
                    ->lockForUpdate()
                    ->findOrFail($data['location_id']);
                $lockedIntake = null;
                if ($intake) {
                    $lockedIntake = ReceptionIntake::query()->lockForUpdate()->findOrFail($intake->id);
                    abort_unless(
                        $this->receptionAccess->canProcessIntake($user, $lockedIntake)
                            && (int) $lockedIntake->location_id === (int) $location->id,
                        409,
                    );
                }
                $lockedOrder = null;
                if ($negotiatedOrder) {
                    $lockedOrder = NegotiatedOrder::query()
                        ->lockForUpdate()
                        ->findOrFail($negotiatedOrder->id);
                    abort_unless($lockedOrder->isCreated(), 409, 'Comanda este deja închisă.');
                }
                $receptionAttributes = [
                    'number' => 'RF-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(4)),
                    'location_id' => $location->id,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'received_by' => $user->id,
                    'document_type' => $data['document_type'],
                    'document_number' => $data['document_number'] ?? null,
                    'status' => 'posted',
                    'received_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ];
                if ($lockedOrder) {
                    $receptionAttributes['negotiated_order_id'] = $lockedOrder->id;
                }
                $reception = SupplierReception::create($receptionAttributes);

                foreach ($data['lines'] as $lineData) {
                    $item = CatalogItem::where('active', true)
                        ->where('tracking_type', 'quantity')
                        ->findOrFail($lineData['catalog_item_id']);
                    $line = $reception->lines()->create([
                        'catalog_item_id' => $item->id,
                        'quantity' => $lineData['quantity'],
                        'unit' => $item->unit,
                        'lot_code' => $lineData['lot_code'] ?? null,
                        'expires_at' => $lineData['expires_at'] ?? null,
                        'unit_price' => $lineData['unit_price'] ?? null,
                        'currency' => $lineData['currency'] ?? 'RON',
                        'notes' => $lineData['notes'] ?? null,
                    ]);
                    $ledger->postReception($line, $user);
                }

                if (! empty($data['attachments'])) {
                    $storedDocuments = $this->documents->store(
                        array_values($data['attachments']),
                        $user,
                        receptionId: $reception->id,
                    );
                }

                if ($lockedIntake) {
                    $lockedIntake->documents()->update(['supplier_reception_id' => $reception->id]);
                    $lockedIntake->update([
                        'supplier_reception_id' => $reception->id,
                        'processed_by' => $user->id,
                        'status' => 'closed',
                        'closure_type' => 'converted',
                        'closed_at' => now(),
                    ]);
                }
                if ($lockedOrder) {
                    $lockedOrder->update([
                        'status' => NegotiatedOrder::STATUS_CLOSED,
                        'closure_type' => NegotiatedOrder::CLOSURE_RECEPTION,
                        'closure_reason' => null,
                        'closed_by' => $user->id,
                        'closed_at' => now(),
                    ]);
                }

                activity()
                    ->performedOn($reception)
                    ->causedBy($user)
                    ->withProperties([
                        'line_count' => count($data['lines']),
                        'direct_document_count' => $storedDocuments->count(),
                        'intake_id' => $lockedIntake?->id,
                        'negotiated_order_id' => $lockedOrder?->id,
                    ])
                    ->log('Recepție înregistrată');

                return $reception;
            });
        } catch (\Throwable $exception) {
            $this->documents->remove($storedDocuments);
            throw $exception;
        }

        return redirect()
            ->route('supplier-receptions.show', $reception)
            ->with('status', 'Recepția a fost salvată, iar stocul a fost actualizat.');
    }

    public function edit(Request $request, SupplierReception $supplierReception): View
    {
        $canEditAll = $this->receptionAccess->canEditAllReceptionDetails($request->user(), $supplierReception);
        $canEditExpiration = $this->receptionAccess->canEditReceptionExpiration($request->user(), $supplierReception);
        abort_unless($canEditAll || $canEditExpiration, 403);
        $supplierReception->load([
            'location',
            'supplier',
            'lines.catalogItem',
            'lines.inventoryLot',
            'documents',
        ]);

        return view('supplier-receptions.edit', [
            'reception' => $supplierReception,
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'currencies' => self::CURRENCIES,
            'documentTypes' => ReceptionDocument::TYPE_LABELS,
            'canEditAll' => $canEditAll,
            'canEditExpiration' => $canEditExpiration,
            'canViewCommercial' => $request->user()->canViewCommercialInventory(),
        ]);
    }

    public function update(Request $request, SupplierReception $supplierReception): RedirectResponse
    {
        $user = $request->user();
        $canEditAll = $this->receptionAccess->canEditAllReceptionDetails($user, $supplierReception);
        $canEditExpiration = $this->receptionAccess->canEditReceptionExpiration($user, $supplierReception);
        abort_unless($canEditAll || $canEditExpiration, 403);

        $data = $this->validateUpdatePayload($request, $supplierReception, $canEditAll);
        $storedDocuments = collect();
        try {
            DB::transaction(function () use (
                $data,
                $user,
                $supplierReception,
                $canEditAll,
                &$storedDocuments,
            ): void {
                $supplierReception->load('lines.inventoryLot');
                $before = $this->metadataSnapshot($supplierReception);

                if ($canEditAll) {
                    $supplierReception->update([
                        'supplier_id' => $data['supplier_id'] ?? null,
                        'document_type' => $data['document_type'],
                        'document_number' => $data['document_number'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);
                }

                foreach ($data['lines'] as $lineData) {
                    $line = $supplierReception->lines->firstWhere('id', (int) $lineData['id']);
                    abort_unless($line, 422);
                    $changes = ['expires_at' => $lineData['expires_at'] ?? null];
                    if ($canEditAll) {
                        $changes += [
                            'lot_code' => $lineData['lot_code'] ?? null,
                            'unit_price' => $lineData['unit_price'] ?? null,
                            'currency' => $lineData['currency'] ?? 'RON',
                            'notes' => $lineData['notes'] ?? null,
                        ];
                    }
                    $line->update($changes);
                    $line->inventoryLot?->update([
                        'supplier_id' => $canEditAll ? ($data['supplier_id'] ?? null) : $line->inventoryLot->supplier_id,
                        'document_number' => $canEditAll
                            ? ($data['document_number'] ?? null)
                            : $line->inventoryLot->document_number,
                        'lot_code' => $canEditAll ? ($lineData['lot_code'] ?? null) : $line->inventoryLot->lot_code,
                        'expires_at' => $lineData['expires_at'] ?? null,
                        'unit_price' => $canEditAll ? ($lineData['unit_price'] ?? null) : $line->inventoryLot->unit_price,
                        'currency' => $canEditAll ? ($lineData['currency'] ?? 'RON') : $line->inventoryLot->currency,
                        'notes' => $canEditAll ? ($lineData['notes'] ?? $data['notes'] ?? null) : $line->inventoryLot->notes,
                    ]);
                }

                if ($canEditAll && ! empty($data['attachments'])) {
                    $storedDocuments = $this->documents->store(
                        array_values($data['attachments']),
                        $user,
                        receptionId: $supplierReception->id,
                    );
                }

                $supplierReception->refresh()->load('lines.inventoryLot');
                activity()
                    ->performedOn($supplierReception)
                    ->causedBy($user)
                    ->withProperties([
                        'before' => $before,
                        'after' => $this->metadataSnapshot($supplierReception),
                        'scope' => $canEditAll ? 'all_metadata' : 'expiration_only',
                        'new_document_count' => $storedDocuments->count(),
                    ])
                    ->log('Detalii recepție actualizate');
            });
        } catch (\Throwable $exception) {
            $this->documents->remove($storedDocuments);
            throw $exception;
        }

        return redirect()
            ->route('supplier-receptions.show', $supplierReception)
            ->with('status', 'Detaliile recepției au fost actualizate și păstrate în istoricul intern.');
    }

    private function validateCreatePayload(Request $request, array $writeLocationIds): array
    {
        $legacySingleLinePayload = ! $request->has('lines')
            && $request->filled('catalog_item_id')
            && $request->filled('quantity');

        if ($legacySingleLinePayload) {
            $request->merge([
                'lines' => [[
                    'catalog_item_id' => $request->input('catalog_item_id'),
                    'quantity' => $request->input('quantity'),
                    'lot_code' => $request->input('lot_code'),
                    'expires_at' => $request->input('expires_at'),
                    'unit_price' => $request->input('unit_price'),
                    'currency' => $request->input('currency', 'RON'),
                    'notes' => $request->input('line_notes'),
                ]],
            ]);
        }

        $validator = Validator::make($request->all(), [
            'intake_id' => ['nullable', 'integer', 'exists:reception_intakes,id'],
            'negotiated_order_id' => ['nullable', 'integer', 'exists:negotiated_orders,id'],
            'location_id' => ['required', 'integer', Rule::in($writeLocationIds)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('active', true)],
            'document_type' => ['required', 'in:aviz,factura'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.catalog_item_id' => [
                'required',
                'integer',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('tracking_type', 'quantity')),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'lines.*.lot_code' => ['nullable', 'string', 'max:120'],
            'lines.*.expires_at' => ['nullable', 'date'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.9999'],
            'lines.*.currency' => ['required', Rule::in(self::CURRENCIES)],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*.file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:12288'],
            'attachments.*.type' => ['required', Rule::in(array_keys(ReceptionDocument::TYPE_LABELS))],
            'attachments.*.custom_label' => ['nullable', 'string', 'max:160'],
        ]);
        if ($legacySingleLinePayload) {
            $validator->after(function ($validator): void {
                if ($validator->errors()->has('lines.0.catalog_item_id')) {
                    $validator->errors()->add(
                        'catalog_item_id',
                        $validator->errors()->first('lines.0.catalog_item_id'),
                    );
                }
                if ($validator->errors()->has('lines.0.quantity')) {
                    $validator->errors()->add(
                        'quantity',
                        $validator->errors()->first('lines.0.quantity'),
                    );
                }
            });
        }
        $this->validateCustomLabels($validator, $request);

        return $validator->validate();
    }

    private function validateUpdatePayload(
        Request $request,
        SupplierReception $reception,
        bool $canEditAll,
    ): array {
        $rules = [
            'lines' => ['required', 'array', 'size:'.$reception->lines()->count()],
            'lines.*.id' => ['required', 'integer', 'distinct', Rule::exists('supplier_reception_lines', 'id')
                ->where('supplier_reception_id', $reception->id)],
            'lines.*.expires_at' => ['nullable', 'date'],
        ];

        if ($canEditAll) {
            $rules += [
                'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('active', true)],
                'document_type' => ['required', 'in:aviz,factura'],
                'document_number' => ['nullable', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:4000'],
                'lines.*.lot_code' => ['nullable', 'string', 'max:120'],
                'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.9999'],
                'lines.*.currency' => ['required', Rule::in(self::CURRENCIES)],
                'lines.*.notes' => ['nullable', 'string', 'max:2000'],
                'attachments' => ['nullable', 'array', 'max:10'],
                'attachments.*.file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf', 'max:12288'],
                'attachments.*.type' => ['required', Rule::in(array_keys(ReceptionDocument::TYPE_LABELS))],
                'attachments.*.custom_label' => ['nullable', 'string', 'max:160'],
            ];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($canEditAll) {
            $this->validateCustomLabels($validator, $request);
        }

        return $validator->validate();
    }

    private function validateCustomLabels($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request): void {
            foreach ((array) $request->input('attachments', []) as $index => $attachment) {
                if (($attachment['type'] ?? null) === 'custom' && blank($attachment['custom_label'] ?? null)) {
                    $validator->errors()->add("attachments.{$index}.custom_label", 'Completează denumirea documentului personalizat.');
                }
            }
        });
    }

    private function metadataSnapshot(SupplierReception $reception): array
    {
        return [
            'supplier_id' => $reception->supplier_id,
            'document_type' => $reception->document_type,
            'document_number' => $reception->document_number,
            'notes' => $reception->notes,
            'lines' => $reception->lines->map(fn ($line) => [
                'id' => $line->id,
                'lot_code' => $line->lot_code,
                'expires_at' => $line->expires_at?->format('Y-m-d'),
                'unit_price' => $line->unit_price,
                'currency' => $line->currency,
                'notes' => $line->notes,
            ])->values()->all(),
        ];
    }

    private function writeLocations(User $user): Builder
    {
        return Location::query()
            ->where('active', true)
            ->when(! $user->isOperationsAdmin(), fn (Builder $query) => $query
                ->whereIn('id', $user->activeManagedLocations()
                    ->where('locations.active', true)
                    ->pluck('locations.id')));
    }

    private function canCreate(User $user): bool
    {
        return ($user->isOperationsAdmin() || $user->hasAnyRole(['sef-santier', 'gestionar-baza']))
            && $this->writeLocations($user)->exists();
    }
}
