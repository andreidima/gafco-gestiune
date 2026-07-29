@extends('layouts.app')

@section('title', 'Fișă inventar materiale')

@php
    $formatQuantity = static fn ($value) => number_format((float) $value, 3, ',', '.');
    $columnVisible = static fn (string $column) => in_array($column, $columns, true);
@endphp

@section('content')
<div class="resource-shell inventory-shell">
    <x-resource-page-header
        title="Fișă inventar materiale"
        description="Situația curentă pe locații, cu detalii despre loturi și acces rapid la istoricul fiecărui material."
        :count="$totalMaterials"
        :filtered-count="$items->total()"
        icon="fa-warehouse"
    >
        <x-slot:actions><x-live-view view-key="inventory-index" /></x-slot:actions>
    </x-resource-page-header>

    <form
        method="get"
        action="{{ route('inventory.index') }}"
        class="resource-filter-panel"
        data-inventory-filters
        data-auto-submit-filters
        data-preferences-url="{{ route('preferences.inventory.update') }}"
    >
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-5 col-md-6">
                <label for="inventory-search" class="resource-filter-label">Căutare</label>
                <input
                    id="inventory-search"
                    name="search"
                    value="{{ $filters['search'] }}"
                    class="form-control"
                    placeholder="Material, cod sau cod de bare"
                    autocomplete="off"
                    data-inventory-search
                >
            </div>
            <div class="col-xl-4 col-md-6">
                <label for="inventory-location" class="resource-filter-label">Locație</label>
                <select id="inventory-location" name="location_id" class="form-select" data-inventory-change>
                    <option value="">Toate locațiile permise</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) $filters['location_id'] === (int) $location->id)>
                            {{ $location->code }} — {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-3 d-flex flex-wrap align-items-center gap-2">
                <div class="form-check form-switch me-auto">
                    <input type="hidden" name="hide_zero" value="0">
                    <input
                        id="inventory-hide-zero"
                        name="hide_zero"
                        value="1"
                        class="form-check-input"
                        type="checkbox"
                        @checked($filters['hide_zero'])
                        data-inventory-change
                    >
                    <label class="form-check-label" for="inventory-hide-zero">Ascunde stocul zero</label>
                </div>
                <div class="dropdown d-none d-md-block">
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                        <i class="fa-solid fa-table-columns me-1"></i>Coloane
                    </button>
                    <div class="dropdown-menu dropdown-menu-end inventory-column-menu p-3">
                        <div class="small fw-semibold mb-2">Informații vizibile</div>
                        @foreach($columnOptions as $column => $label)
                            <div class="form-check mb-1">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    value="{{ $column }}"
                                    id="inventory-column-{{ $column }}"
                                    @checked(in_array($column, $columns, true))
                                    data-inventory-column
                                >
                                <label class="form-check-label" for="inventory-column-{{ $column }}">{{ $label }}</label>
                            </div>
                        @endforeach
                        <hr>
                        <label for="inventory-density" class="resource-filter-label">Densitate</label>
                        <select id="inventory-density" class="form-select form-select-sm" data-inventory-density>
                            <option value="compact" @selected($density === 'compact')>Compactă</option>
                            <option value="comfortable" @selected($density === 'comfortable')>Confortabilă</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit" title="Reîncarcă rezultatele">
                    <i class="fa-solid fa-rotate"></i>
                </button>
                <a href="{{ route('inventory.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Resetează filtrele">
                    <i class="fa-solid fa-filter-circle-xmark"></i>
                </a>
            </div>
        </div>
        <div class="inventory-filter-status mt-2" data-inventory-save-status aria-live="polite">
            Selecțiile din filtre și coloanele sunt salvate în contul tău. Căutarea scrisă nu este memorată.
        </div>
    </form>

    <div class="resource-table-card inventory-table-card inventory-density-{{ $density }}">
        <div class="resource-results-meta">
            <span><strong>{{ $items->total() }}</strong> materiale în vizualizarea curentă</span>
            <span>Apasă pe un material pentru loturi și istoric complet.</span>
        </div>
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table inventory-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th class="text-nowrap">UM</th>
                        <th class="text-end">Stoc total</th>
                        @if($columnVisible('locations'))<th>Locații</th>@endif
                        @if($columnVisible('lots'))<th>Loturi active</th>@endif
                        @if($columnVisible('supplier'))<th>Furnizor</th>@endif
                        @if($columnVisible('document'))<th>Document</th>@endif
                        @if($columnVisible('received_at'))<th>Data intrării</th>@endif
                        @if($columnVisible('expiration'))<th>Expirare</th>@endif
                        @if($columnVisible('price') && $canViewCommercial)<th class="text-end">Preț unitar</th>@endif
                        <th class="text-end"><span class="visually-hidden">Detalii</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    @php
                        $total = (float) ($item->visible_stock_quantity ?? 0);
                        $activeLots = $item->inventoryLots;
                        $firstLot = $activeLots->first();
                    @endphp
                    <tr class="inventory-row {{ $total <= 0 ? 'inventory-row-zero' : '' }}" data-href="{{ route('inventory.show', ['catalogItem' => $item, 'location_id' => $filters['location_id'] ?: null]) }}">
                        <td>
                            <a href="{{ route('inventory.show', ['catalogItem' => $item, 'location_id' => $filters['location_id'] ?: null]) }}" class="resource-primary text-decoration-none">
                                {{ $item->name }}
                            </a>
                            <span class="resource-secondary">{{ $item->sku ?: 'Fără cod intern' }}</span>
                        </td>
                        <td class="text-nowrap">{{ $item->unit }}</td>
                        <td class="text-end">
                            <strong class="{{ $total <= 0 ? 'text-secondary' : '' }}">{{ $formatQuantity($total) }}</strong>
                            @if($total <= 0)<span class="resource-secondary">Stoc zero</span>@endif
                        </td>
                        @if($columnVisible('locations'))
                            <td>
                                @forelse($item->stockLevels->where('quantity', '>', 0)->take(3) as $stock)
                                    <div class="inventory-cell-line">
                                        <span>{{ $stock->location?->code }}</span>
                                        <strong>{{ $formatQuantity($stock->quantity) }}</strong>
                                    </div>
                                @empty
                                    <span class="text-secondary">—</span>
                                @endforelse
                                @if($item->stockLevels->where('quantity', '>', 0)->count() > 3)
                                    <span class="resource-secondary">+{{ $item->stockLevels->where('quantity', '>', 0)->count() - 3 }} locații</span>
                                @endif
                            </td>
                        @endif
                        @if($columnVisible('lots'))
                            <td>
                                <strong>{{ $activeLots->count() }}</strong>
                                <span class="resource-secondary">{{ $activeLots->where('is_opening_balance', true)->count() ? 'include sold inițial' : 'loturi urmărite' }}</span>
                            </td>
                        @endif
                        @if($columnVisible('supplier'))
                            <td>{{ $firstLot?->supplier?->name ?? ($firstLot?->is_opening_balance ? 'Sold inițial' : '—') }}</td>
                        @endif
                        @if($columnVisible('document'))
                            <td>{{ $firstLot?->document_number ?? '—' }}</td>
                        @endif
                        @if($columnVisible('received_at'))
                            <td class="text-nowrap">{{ $firstLot?->received_at?->format('d.m.Y') ?? '—' }}</td>
                        @endif
                        @if($columnVisible('expiration'))
                            <td class="text-nowrap">
                                @if($firstLot?->expires_at)
                                    <span class="{{ $firstLot->expires_at->isPast() ? 'text-danger fw-semibold' : '' }}">{{ $firstLot->expires_at->format('d.m.Y') }}</span>
                                @else
                                    —
                                @endif
                            </td>
                        @endif
                        @if($columnVisible('price') && $canViewCommercial)
                            <td class="text-end text-nowrap">
                                {{ $firstLot?->unit_price !== null ? number_format((float) $firstLot->unit_price, 4, ',', '.').' '.$firstLot->currency : '—' }}
                            </td>
                        @endif
                        <td class="text-end">
                            <a href="{{ route('inventory.show', ['catalogItem' => $item, 'location_id' => $filters['location_id'] ?: null]) }}" class="btn btn-sm btn-outline-secondary" aria-label="Deschide fișa materialului">
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">
                            <div class="resource-empty-state">
                                <i class="fa-solid fa-box-open"></i>
                                <span>Niciun material nu corespunde filtrelor selectate.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="resource-mobile-list">
            @forelse($items as $item)
                @php
                    $total = (float) ($item->visible_stock_quantity ?? 0);
                    $activeLots = $item->inventoryLots;
                    $firstLot = $activeLots->first();
                    $positiveStocks = $item->stockLevels->where('quantity', '>', 0);
                @endphp
                <article class="card resource-mobile-card {{ $total <= 0 ? 'inventory-row-zero' : '' }}" data-href="{{ route('inventory.show', ['catalogItem' => $item, 'location_id' => $filters['location_id'] ?: null]) }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $item->name }}</h2>
                                <div class="resource-code">{{ $item->sku ?: 'Fără cod intern' }}</div>
                            </div>
                            <span class="badge {{ $total > 0 ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                {{ $formatQuantity($total) }} {{ $item->unit }}
                            </span>
                        </div>
                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Locații cu stoc</span>
                                <strong>{{ $positiveStocks->count() }}</strong>
                                @if($positiveStocks->isNotEmpty())
                                    <span class="resource-secondary">
                                        {{ $positiveStocks->take(2)->map(fn ($stock) => ($stock->location?->code ?? '—').' '.$formatQuantity($stock->quantity))->implode(' · ') }}
                                    </span>
                                @else
                                    <span class="resource-secondary">Stoc zero</span>
                                @endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Loturi active</span>
                                <strong>{{ $activeLots->count() }}</strong>
                                <span class="resource-secondary">
                                    {{ $firstLot?->supplier?->name ?? ($firstLot?->is_opening_balance ? 'Include sold inițial' : 'Fără lot activ') }}
                                </span>
                            </div>
                            @if($firstLot?->expires_at)
                                <div class="resource-mobile-card-wide">
                                    <span class="resource-filter-label">Cea mai apropiată expirare</span>
                                    <strong class="{{ $firstLot->expires_at->isPast() ? 'text-danger' : '' }}">{{ $firstLot->expires_at->format('d.m.Y') }}</strong>
                                </div>
                            @endif
                        </div>
                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('inventory.show', ['catalogItem' => $item, 'location_id' => $filters['location_id'] ?: null]) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa-solid fa-list-ul me-1"></i>Loturi și istoric
                            </a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    <i class="fa-solid fa-box-open"></i>
                    <span>Niciun material nu corespunde filtrelor selectate.</span>
                </div>
            @endforelse
        </div>
        @if($items->hasPages())
            <div class="resource-pagination">{{ $items->links() }}</div>
        @endif
    </div>
</div>
@endsection
