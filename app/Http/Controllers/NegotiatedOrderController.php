<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Location;
use App\Models\NegotiatedOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NegotiatedOrderController extends Controller
{
    private const CURRENCIES = ['RON', 'EUR', 'USD', 'GBP', 'CNY'];

    public function index(Request $request): View
    {
        $orders = NegotiatedOrder::query()
            ->with(['location', 'supplier', 'creator', 'closer', 'reception', 'lines.catalogItem'])
            ->when($request->search, function ($query, $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('lines.catalogItem', fn ($item) => $item->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->location_id, fn ($query, $locationId) => $query->where('location_id', $locationId))
            ->when($request->supplier_id, fn ($query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($request->date_from, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date_to, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('negotiated-orders.index', [
            'orders' => $orders,
            'totalOrders' => NegotiatedOrder::count(),
            'locations' => Location::where('active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('negotiated-orders.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);

        $order = DB::transaction(function () use ($data, $request): NegotiatedOrder {
            $order = NegotiatedOrder::create([
                'number' => 'CMD-TMP-'.Str::upper(Str::random(16)),
                'status' => NegotiatedOrder::STATUS_CREATED,
                'location_id' => $data['location_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'created_by' => $request->user()->id,
                'currency' => $data['currency'],
                'notes' => $data['notes'] ?? null,
            ]);
            $order->update([
                'number' => sprintf('CMD-%s-%05d', now()->format('Y'), $order->id),
            ]);
            $this->replaceLines($order, $data['lines']);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->withProperties(['line_count' => count($data['lines'])])
                ->log('Comandă negociată creată');

            return $order;
        });

        return redirect()
            ->route('negotiated-orders.show', $order)
            ->with('status', 'Comanda a fost creată. O poți modifica sau transforma într-o recepție.');
    }

    public function show(NegotiatedOrder $negotiatedOrder): View
    {
        $negotiatedOrder->load([
            'location',
            'supplier',
            'creator',
            'closer',
            'reception',
            'lines.catalogItem',
        ]);

        return view('negotiated-orders.show', ['order' => $negotiatedOrder]);
    }

    public function edit(NegotiatedOrder $negotiatedOrder): View
    {
        abort_unless($negotiatedOrder->isCreated(), 409, 'O comandă închisă nu mai poate fi modificată.');
        $negotiatedOrder->load('lines.catalogItem');

        return view('negotiated-orders.edit', [
            ...$this->formOptions(),
            'order' => $negotiatedOrder,
        ]);
    }

    public function update(Request $request, NegotiatedOrder $negotiatedOrder): RedirectResponse
    {
        $data = $this->validatePayload($request);

        DB::transaction(function () use ($data, $request, $negotiatedOrder): void {
            $order = NegotiatedOrder::query()->lockForUpdate()->findOrFail($negotiatedOrder->id);
            abort_unless($order->isCreated(), 409, 'O comandă închisă nu mai poate fi modificată.');

            $before = $this->snapshot($order->load('lines'));
            $order->update([
                'location_id' => $data['location_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'currency' => $data['currency'],
                'notes' => $data['notes'] ?? null,
            ]);
            $this->replaceLines($order, $data['lines']);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->withProperties([
                    'before' => $before,
                    'after' => $this->snapshot($order->fresh()->load('lines')),
                ])
                ->log('Comandă negociată actualizată');
        });

        return redirect()
            ->route('negotiated-orders.show', $negotiatedOrder)
            ->with('status', 'Comanda a fost actualizată.');
    }

    public function cancel(Request $request, NegotiatedOrder $negotiatedOrder): RedirectResponse
    {
        $data = $request->validate([
            'closure_reason' => ['required', 'string', 'min:5', 'max:4000'],
        ]);

        DB::transaction(function () use ($data, $request, $negotiatedOrder): void {
            $order = NegotiatedOrder::query()->lockForUpdate()->findOrFail($negotiatedOrder->id);
            abort_unless($order->isCreated(), 409, 'Comanda este deja închisă.');
            $order->update([
                'status' => NegotiatedOrder::STATUS_CLOSED,
                'closure_type' => NegotiatedOrder::CLOSURE_CANCELLED,
                'closure_reason' => $data['closure_reason'],
                'closed_by' => $request->user()->id,
                'closed_at' => now(),
            ]);

            activity()
                ->performedOn($order)
                ->causedBy($request->user())
                ->withProperties(['reason' => $data['closure_reason']])
                ->log('Comandă negociată anulată');
        });

        return redirect()
            ->route('negotiated-orders.show', $negotiatedOrder)
            ->with('status', 'Comanda a fost închisă ca anulată. Datele rămân în istoric.');
    }

    private function formOptions(): array
    {
        return [
            'locations' => Location::where('active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
            'items' => CatalogItem::where('active', true)
                ->where('tracking_type', 'quantity')
                ->orderBy('name')
                ->get(),
            'currencies' => self::CURRENCIES,
        ];
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')->where('active', true)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('active', true)],
            'currency' => ['required', Rule::in(self::CURRENCIES)],
            'notes' => ['nullable', 'string', 'max:4000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.catalog_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('catalog_items', 'id')->where(fn ($query) => $query
                    ->where('active', true)
                    ->where('tracking_type', 'quantity')),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999.999'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999.9999'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function replaceLines(NegotiatedOrder $order, array $lines): void
    {
        $order->lines()->delete();

        foreach ($lines as $line) {
            $item = CatalogItem::where('active', true)
                ->where('tracking_type', 'quantity')
                ->findOrFail($line['catalog_item_id']);
            $order->lines()->create([
                'catalog_item_id' => $item->id,
                'quantity' => $line['quantity'],
                'unit' => $item->unit,
                'unit_price' => $line['unit_price'],
                'notes' => $line['notes'] ?? null,
            ]);
        }
    }

    private function snapshot(NegotiatedOrder $order): array
    {
        return [
            'location_id' => $order->location_id,
            'supplier_id' => $order->supplier_id,
            'currency' => $order->currency,
            'notes' => $order->notes,
            'lines' => $order->lines->map(fn ($line) => [
                'catalog_item_id' => $line->catalog_item_id,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'notes' => $line->notes,
            ])->values()->all(),
        ];
    }
}
