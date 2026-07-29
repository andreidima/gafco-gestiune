@extends('layouts.app')

@section('title', 'Comenzi negociate')

@php
    $formatQuantity = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, ',', '.'), '0'), ',');
    $formatMoney = fn ($value) => number_format((float) $value, 2, ',', '.');
    $hasFilters = collect(['search', 'status', 'location_id', 'supplier_id', 'date_from', 'date_to'])
        ->contains(fn ($key) => request()->filled($key));
@endphp

@section('content')
<div class="resource-shell">
    <x-resource-page-header
        title="Comenzi negociate"
        description="Lista simplă a comenzilor convenite cu furnizorii. Stocul se actualizează doar prin recepție."
        :count="$totalOrders"
        :filtered-count="$orders->total()"
        icon="fa-file-invoice-dollar"
        :create-route="route('negotiated-orders.create')"
        create-label="Comandă nouă"
    />

    <form method="get" class="resource-filter-panel" data-auto-submit-filters>
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-6">
                <label class="resource-filter-label">Căutare</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Număr, furnizor sau material">
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="resource-filter-label">Stare</label>
                <select name="status" class="form-select">
                    <option value="">Toate</option>
                    <option value="created" @selected(request('status') === 'created')>Creat</option>
                    <option value="closed" @selected(request('status') === 'closed')>Închis</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="resource-filter-label">Locație</label>
                <select name="location_id" class="form-select">
                    <option value="">Toate</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-6">
                <label class="resource-filter-label">Furnizor</label>
                <select name="supplier_id" class="form-select">
                    <option value="">Toți</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-1 col-md-4">
                <label class="resource-filter-label">De la</label>
                <input name="date_from" type="date" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="col-xl-1 col-md-4">
                <label class="resource-filter-label">Până la</label>
                <input name="date_to" type="date" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="col-xl-1 col-md-4 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1" title="Aplică filtrele"><i class="fa-solid fa-filter"></i></button>
                @if($hasFilters)
                    <a href="{{ route('negotiated-orders.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Resetează filtrele"><i class="fa-solid fa-rotate-left"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="resource-table-card d-none d-lg-block">
        <div class="table-responsive">
            <table class="table resource-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Comandă</th>
                        <th>Destinație / furnizor</th>
                        <th>Materiale</th>
                        <th class="text-end">Valoare fără TVA</th>
                        <th>Stare</th>
                        <th class="text-end">Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        @php($total = $order->lines->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price))
                        <tr data-href="{{ route('negotiated-orders.show', $order) }}">
                            <td>
                                <div class="resource-cell-stack">
                                    <a href="{{ route('negotiated-orders.show', $order) }}" class="resource-primary text-decoration-none">{{ $order->number }}</a>
                                    <span class="resource-secondary"><i class="fa-regular fa-clock me-1"></i>{{ $order->created_at->format('d.m.Y H:i') }}</span>
                                    <span class="resource-secondary"><i class="fa-solid fa-user me-1"></i>{{ $order->creator?->name ?? '—' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="resource-cell-stack">
                                    <span class="resource-primary">{{ $order->location?->code }} — {{ $order->location?->name }}</span>
                                    <span class="resource-secondary">{{ $order->supplier?->name ?? 'Furnizor nespecificat' }}</span>
                                </div>
                            </td>
                            <td>
                                <div class="resource-cell-stack">
                                    @foreach($order->lines->take(2) as $line)
                                        <span>{{ $line->catalogItem?->name ?? 'Material indisponibil' }} <span class="resource-secondary">{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</span></span>
                                    @endforeach
                                    @if($order->lines->count() > 2)
                                        <span class="resource-secondary fw-semibold">+{{ $order->lines->count() - 2 }} {{ $order->lines->count() === 3 ? 'alt material' : 'alte materiale' }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">
                                <span class="resource-primary">{{ $formatMoney($total) }} {{ $order->currency }}</span>
                            </td>
                            <td>
                                <div class="resource-cell-stack">
                                    <x-status :status="$order->status" />
                                    @if($order->closure_type === 'cancelled')
                                        <span class="resource-secondary">Anulată</span>
                                    @elseif($order->closure_type === 'reception')
                                        <span class="resource-secondary">Transformată în recepție</span>
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('negotiated-orders.show', $order) }}" class="btn btn-outline-primary btn-sm" title="Deschide"><i class="fa-solid fa-eye"></i></a>
                                    @if($order->isCreated())
                                        <a href="{{ route('negotiated-orders.edit', $order) }}" class="btn btn-outline-secondary btn-sm" title="Modifică"><i class="fa-solid fa-pen"></i></a>
                                        <a href="{{ route('supplier-receptions.create', ['negotiated_order_id' => $order->id]) }}" class="btn btn-outline-success btn-sm" title="Transformă în recepție"><i class="fa-solid fa-truck-ramp-box"></i></a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                @if($hasFilters)
                                    <p class="text-muted mb-2">Nicio comandă nu corespunde filtrelor selectate.</p>
                                    <a href="{{ route('negotiated-orders.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm">Resetează filtrele</a>
                                @else
                                    <p class="text-muted mb-2">Nu există încă nicio comandă negociată.</p>
                                    <a href="{{ route('negotiated-orders.create') }}" class="btn btn-primary btn-sm">Creează prima comandă</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-lg-none d-grid gap-3">
        @forelse($orders as $order)
            @php($total = $order->lines->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price))
            <article class="card resource-mobile-card" data-href="{{ route('negotiated-orders.show', $order) }}">
                <div class="card-body">
                    <div class="resource-mobile-card-header">
                        <div>
                            <h2 class="resource-mobile-card-title">{{ $order->number }}</h2>
                            <div class="resource-mobile-card-subtitle">{{ $order->created_at->format('d.m.Y H:i') }} · {{ $order->creator?->name ?? '—' }}</div>
                        </div>
                        <x-status :status="$order->status" />
                    </div>
                    <div class="resource-mobile-card-grid">
                        <div>
                            <span class="resource-mobile-card-label">Destinație</span>
                            <strong>{{ $order->location?->code }} — {{ $order->location?->name }}</strong>
                        </div>
                        <div>
                            <span class="resource-mobile-card-label">Furnizor</span>
                            <strong>{{ $order->supplier?->name ?? 'Nespecificat' }}</strong>
                        </div>
                        <div class="resource-mobile-card-wide">
                            <span class="resource-mobile-card-label">Materiale</span>
                            @foreach($order->lines->take(3) as $line)
                                <span>{{ $line->catalogItem?->name }} · {{ $formatQuantity($line->quantity) }} {{ $line->unit }}</span>
                            @endforeach
                        </div>
                        <div>
                            <span class="resource-mobile-card-label">Valoare fără TVA</span>
                            <strong>{{ $formatMoney($total) }} {{ $order->currency }}</strong>
                        </div>
                    </div>
                    <div class="resource-mobile-card-actions">
                        <a href="{{ route('negotiated-orders.show', $order) }}" class="btn btn-outline-primary btn-sm">Deschide</a>
                        @if($order->isCreated())
                            <a href="{{ route('supplier-receptions.create', ['negotiated_order_id' => $order->id]) }}" class="btn btn-success btn-sm">Transformă în recepție</a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="card"><div class="card-body text-center text-muted">Nu există comenzi pentru selecția curentă.</div></div>
        @endforelse
    </div>

    @if($orders->hasPages())
        <div class="resource-table-footer">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
