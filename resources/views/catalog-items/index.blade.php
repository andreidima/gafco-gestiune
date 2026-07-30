@extends('layouts.app')

@section('title', 'Nomenclator')

@section('content')
@php
    $categoryLabels = ['material' => 'Material', 'equipment' => 'Utilaj', 'tool' => 'Scula'];
    $trackingLabels = ['quantity' => 'Cantitativ', 'serialized' => 'QR unic'];
    $hasFilters = request()->filled('search')
        || request()->filled('category')
        || request()->filled('tracking_type')
        || (request()->has('active') && request('active') !== '');
    $formatQuantity = fn ($value) => \App\Support\LocalizedNumber::quantity($value);
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Nomenclator"
        description="Materiale, scule si utilaje folosite in stocuri si operatiuni."
        :count="$totalItems"
        :filtered-count="$items->total()"
        icon="fa-list"
        :create-route="auth()->user()->canManageInventoryMasterData() ? route('catalog-items.create') : null"
        create-label="Articol nou"
    >
        <x-slot:actions>
            <a href="{{ route('tracked-assets.index') }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-qrcode me-1"></i>Asset-uri QR</a>
        </x-slot:actions>
    </x-resource-page-header>

    <form class="resource-filter-panel" data-auto-submit-filters data-live-filter-target="#catalog-items-results">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-5">
                <label class="resource-filter-label">Cautare</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Denumire, SKU sau cod de bare">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="resource-filter-label">Categorie</label>
                <select name="category" class="form-select">
                    <option value="">Toate</option>
                    @foreach($categoryLabels as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="resource-filter-label">Urmarire</label>
                <select name="tracking_type" class="form-select">
                    <option value="">Toate</option>
                    @foreach($trackingLabels as $value => $label)<option value="{{ $value }}" @selected(request('tracking_type') === $value)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-xl-1 col-md-4">
                <label class="resource-filter-label">Stare</label>
                <select name="active" class="form-select">
                    <option value="">Oricare</option>
                    <option value="1" @selected(request('active') === '1')>Activ</option>
                    <option value="0" @selected(request('active') === '0')>Inactiv</option>
                </select>
            </div>
            <div class="col-xl-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Cauta</button>
                <a href="{{ route('catalog-items.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <div id="catalog-items-results" class="resource-table-card" data-live-filter-results>
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table">
                <thead><tr><th>Articol</th><th>Clasificare</th><th>Unitate</th><th>Disponibilitate</th><th>Status</th><th class="text-end">Actiuni</th></tr></thead>
                <tbody>
                @forelse($items as $item)
                    <tr>
                        <td>
                            <div class="resource-cell-stack">
                                <span class="resource-primary">{{ $item->name }}</span>
                                @if($item->sku)<span class="resource-code">{{ $item->sku }}</span>@endif
                                @if($item->barcode)<span class="resource-secondary"><i class="fa-solid fa-barcode me-1"></i>{{ $item->barcode }}</span>@endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                <span><span class="badge text-bg-light border">{{ $categoryLabels[$item->category] ?? $item->category }}</span></span>
                                <span class="resource-secondary">{{ $trackingLabels[$item->tracking_type] ?? $item->tracking_type }}</span>
                            </div>
                        </td>
                        <td><span class="badge text-bg-light border">{{ $item->unit }}</span></td>
                        <td>
                            @if($item->tracking_type === 'serialized')
                                <div class="resource-cell-stack">
                                    <span><strong>{{ $item->available_tracked_assets_count }}</strong> din {{ $item->tracked_assets_count }} disponibile</span>
                                    @if($item->in_use_tracked_assets_count > 0)<span class="resource-secondary"><i class="fa-solid fa-user-gear me-1"></i>{{ $item->in_use_tracked_assets_count }} in folosinta</span>@endif
                                    @if($item->attention_tracked_assets_count > 0)<span class="text-warning small fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $item->attention_tracked_assets_count }} necesita atentie</span>@endif
                                </div>
                            @else
                                <div class="resource-cell-stack">
                                    <span><strong>{{ $formatQuantity($item->stock_levels_sum_quantity ?? 0) }}</strong> {{ $item->unit }} in stoc</span>
                                    @if($item->stock_levels_count > 0)
                                        <span class="resource-secondary">In {{ $item->stock_levels_count }} {{ $item->stock_levels_count === 1 ? 'locatie' : 'locatii' }}</span>
                                    @else
                                        <span class="text-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i>Fara stoc inregistrat</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td><span class="badge text-bg-{{ $item->active ? 'success' : 'secondary' }}">{{ $item->active ? 'Activ' : 'Inactiv' }}</span></td>
                        <td>
                            <div class="resource-row-actions">
                                @if(auth()->user()->canManageInventoryMasterData())<x-resource-icon-button :href="route('catalog-items.edit', $item)" icon="fa-pen" label="Modifica articolul" />@endif
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary resource-overflow-button" data-bs-toggle="dropdown" aria-expanded="false" title="Mai multe actiuni"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('tracked-assets.index', ['catalog_item_id' => $item->id]) }}"><i class="fa-solid fa-qrcode me-2"></i>Asset-uri asociate</a></li>
                                        <li><a class="dropdown-item" href="{{ route('supplier-receptions.index', ['catalog_item_id' => $item->id]) }}"><i class="fa-solid fa-receipt me-2"></i>Receptii</a></li>
                                        <li><a class="dropdown-item" href="{{ route('consumption-reports.index', ['catalog_item_id' => $item->id]) }}"><i class="fa-solid fa-clipboard-check me-2"></i>Consumuri</a></li>
                                    </ul>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            @if($hasFilters)
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Niciun articol nu corespunde filtrelor selectate.</span>
                                    <a href="{{ route('catalog-items.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Nomenclatorul este gol.</span>
                                    @if(auth()->user()->canManageInventoryMasterData())<a href="{{ route('catalog-items.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Adauga primul articol</a>@endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($items as $item)
                <article class="card resource-mobile-card">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $item->name }}</h2>
                                @if($item->sku)<div class="resource-code">{{ $item->sku }}</div>@endif
                                @if($item->barcode)<div class="resource-mobile-card-subtitle"><i class="fa-solid fa-barcode me-1"></i>{{ $item->barcode }}</div>@endif
                            </div>
                            <span class="badge text-bg-{{ $item->active ? 'success' : 'secondary' }}">{{ $item->active ? 'Activ' : 'Inactiv' }}</span>
                        </div>

                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Clasificare</span>
                                <strong>{{ $categoryLabels[$item->category] ?? $item->category }}</strong>
                                <span class="resource-secondary">{{ $trackingLabels[$item->tracking_type] ?? $item->tracking_type }}</span>
                            </div>
                            <div>
                                <span class="resource-filter-label">Unitate</span>
                                <strong>{{ $item->unit }}</strong>
                            </div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Disponibilitate</span>
                                @if($item->tracking_type === 'serialized')
                                    <strong>{{ $item->available_tracked_assets_count }} din {{ $item->tracked_assets_count }} disponibile</strong>
                                    @if($item->in_use_tracked_assets_count > 0)<span class="resource-secondary"><i class="fa-solid fa-user-gear me-1"></i>{{ $item->in_use_tracked_assets_count }} in folosinta</span>@endif
                                    @if($item->attention_tracked_assets_count > 0)<span class="d-block text-warning small fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $item->attention_tracked_assets_count }} necesita atentie</span>@endif
                                @else
                                    <strong>{{ $formatQuantity($item->stock_levels_sum_quantity ?? 0) }} {{ $item->unit }} in stoc</strong>
                                    @if($item->stock_levels_count > 0)
                                        <span class="resource-secondary">In {{ $item->stock_levels_count }} {{ $item->stock_levels_count === 1 ? 'locatie' : 'locatii' }}</span>
                                    @else
                                        <span class="d-block text-warning small"><i class="fa-solid fa-triangle-exclamation me-1"></i>Fara stoc inregistrat</span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('tracked-assets.index', ['catalog_item_id' => $item->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-qrcode me-1"></i>QR</a>
                            <a href="{{ route('supplier-receptions.index', ['catalog_item_id' => $item->id]) }}" class="btn btn-outline-secondary btn-sm" aria-label="Vezi receptiile"><i class="fa-solid fa-receipt"></i></a>
                            <a href="{{ route('consumption-reports.index', ['catalog_item_id' => $item->id]) }}" class="btn btn-outline-secondary btn-sm" aria-label="Vezi consumurile"><i class="fa-solid fa-clipboard-check"></i></a>
                            @if(auth()->user()->canManageInventoryMasterData())<a href="{{ route('catalog-items.edit', $item) }}" class="btn btn-primary btn-sm" aria-label="Modifica articolul"><i class="fa-solid fa-pen"></i></a>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    @if($hasFilters)
                        <p class="mb-2">Niciun articol nu corespunde filtrelor selectate.</p>
                        <a href="{{ route('catalog-items.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                    @else
                        <p class="mb-2">Nomenclatorul este gol.</p>
                        @if(auth()->user()->canManageInventoryMasterData())<a href="{{ route('catalog-items.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Adauga primul articol</a>@endif
                    @endif
                </div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $items->links() }}</div>
    </div>
</div>
@endsection
