@extends('layouts.app')

@section('title', 'Locatii')

@section('content')
@php
    $hasFilters = request()->filled('search')
        || request()->filled('type')
        || (request()->has('active') && request('active') !== '');
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Locatii"
        description="Baze, santiere si puncte de lucru, cu responsabilii lor activi."
        :count="$totalLocations"
        :filtered-count="$locations->total()"
        icon="fa-building"
        :create-route="auth()->user()->canManageLocations() ? route('locations.create') : null"
        create-label="Locatie noua"
    />

    <form class="resource-filter-panel" data-auto-submit-filters>
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-6">
                <label class="resource-filter-label">Cautare</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cod, denumire sau adresa">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="resource-filter-label">Tip</label>
                <select name="type" class="form-select"><option value="">Toate</option><option value="base" @selected(request('type') === 'base')>Baze</option><option value="site" @selected(request('type') === 'site')>Santiere</option></select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="resource-filter-label">Stare</label>
                <select name="active" class="form-select"><option value="">Oricare</option><option value="1" @selected(request('active') === '1')>Active</option><option value="0" @selected(request('active') === '0')>Inactive</option></select>
            </div>
            <div class="col-xl-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Cauta</button>
                <a href="{{ route('locations.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <div class="resource-table-card">
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table">
                <thead><tr><th>Locatie</th><th>Tip</th><th>Responsabili</th><th>Inventar / alerte</th><th>Status</th><th class="text-end">Actiuni</th></tr></thead>
                <tbody>
                @forelse($locations as $location)
                    <tr>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $location->name }}</span><span class="resource-code">{{ $location->code }}</span>@if($location->address)<span class="resource-secondary">{{ $location->address }}</span>@endif</div></td>
                        <td><span class="badge text-bg-{{ $location->type === 'base' ? 'primary' : 'info' }}">{{ $location->type === 'base' ? 'Baza' : 'Santier' }}</span></td>
                        <td>
                            <div class="resource-cell-stack">
                                @forelse($location->activeManagers->take(2) as $manager)<span><i class="fa-solid fa-user-tie me-1 text-muted"></i>{{ $manager->name }}</span>@empty<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Fara responsabil</span>@endforelse
                                @if($location->activeManagers->count() > 2)<span class="resource-secondary">+{{ $location->activeManagers->count() - 2 }} alti responsabili</span>@endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                <span><strong>{{ $location->tracked_assets_count }}</strong> echipamente <span class="resource-secondary">/ {{ $location->stock_levels_count }} pozitii de stoc</span></span>
                                @if($location->attention_assets_count > 0)<span class="text-danger small fw-semibold"><i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ $location->attention_assets_count }} echipamente necesita atentie</span>@endif
                                @if($location->empty_stock_levels_count > 0)<span class="text-warning small"><i class="fa-solid fa-box-open me-1"></i>{{ $location->empty_stock_levels_count }} pozitii fara stoc</span>@endif
                                @if($location->pending_transfer_approvals_count > 0)<span class="text-warning small"><i class="fa-solid fa-user-check me-1"></i>{{ $location->pending_transfer_approvals_count }} {{ $location->pending_transfer_approvals_count === 1 ? 'aprobare in asteptare' : 'aprobari in asteptare' }}</span>@endif
                            </div>
                        </td>
                        <td><span class="badge text-bg-{{ $location->active ? 'success' : 'secondary' }}">{{ $location->active ? 'Activa' : 'Inactiva' }}</span></td>
                        <td>
                            <div class="resource-row-actions">
                                @if(auth()->user()->canManageLocations())<x-resource-icon-button :href="route('locations.edit', $location)" icon="fa-pen" label="Modifica locatia" />@endif
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary resource-overflow-button" data-bs-toggle="dropdown" aria-expanded="false" title="Mai multe actiuni"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="{{ route('tracked-assets.index', ['location_id' => $location->id]) }}"><i class="fa-solid fa-screwdriver-wrench me-2"></i>Echipamente</a></li>
                                        <li><a class="dropdown-item" href="{{ route('reports.index', ['location_id' => $location->id]) }}"><i class="fa-solid fa-chart-column me-2"></i>Rapoarte</a></li>
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
                                    <span class="text-muted">Nicio locatie nu corespunde filtrelor selectate.</span>
                                    <a href="{{ route('locations.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Nu exista inca nicio locatie.</span>
                                    @if(auth()->user()->canManageLocations())<a href="{{ route('locations.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Adauga prima locatie</a>@endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($locations as $location)
                <article class="card resource-mobile-card {{ $location->attention_assets_count > 0 ? 'resource-row-alert resource-row-alert-danger' : (($location->empty_stock_levels_count > 0 || $location->pending_transfer_approvals_count > 0 || $location->activeManagers->isEmpty()) ? 'resource-row-alert resource-row-alert-warning' : '') }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $location->name }}</h2>
                                <div class="resource-code">{{ $location->code }}</div>
                                @if($location->address)<div class="resource-mobile-card-subtitle"><i class="fa-solid fa-location-dot me-1"></i>{{ $location->address }}</div>@endif
                            </div>
                            <span class="badge text-bg-{{ $location->active ? 'success' : 'secondary' }}">{{ $location->active ? 'Activa' : 'Inactiva' }}</span>
                        </div>

                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Tip</span>
                                <strong>{{ $location->type === 'base' ? 'Baza' : 'Santier' }}</strong>
                            </div>
                            <div>
                                <span class="resource-filter-label">Responsabili</span>
                                @forelse($location->activeManagers->take(2) as $manager)<strong class="d-block"><i class="fa-solid fa-user-tie me-1 text-muted"></i>{{ $manager->name }}</strong>@empty<span class="text-warning fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Fara responsabil</span>@endforelse
                                @if($location->activeManagers->count() > 2)<span class="resource-secondary">+{{ $location->activeManagers->count() - 2 }} alti responsabili</span>@endif
                            </div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Inventar si alerte</span>
                                <strong>{{ $location->tracked_assets_count }} echipamente</strong>
                                <span class="resource-secondary">{{ $location->stock_levels_count }} pozitii de stoc</span>
                                @if($location->attention_assets_count > 0)<span class="d-block text-danger small fw-semibold"><i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ $location->attention_assets_count }} echipamente necesita atentie</span>@endif
                                @if($location->empty_stock_levels_count > 0)<span class="d-block text-warning small"><i class="fa-solid fa-box-open me-1"></i>{{ $location->empty_stock_levels_count }} pozitii fara stoc</span>@endif
                                @if($location->pending_transfer_approvals_count > 0)<span class="d-block text-warning small"><i class="fa-solid fa-user-check me-1"></i>{{ $location->pending_transfer_approvals_count }} {{ $location->pending_transfer_approvals_count === 1 ? 'aprobare in asteptare' : 'aprobari in asteptare' }}</span>@endif
                            </div>
                        </div>

                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('tracked-assets.index', ['location_id' => $location->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Echipamente</a>
                            <a href="{{ route('reports.index', ['location_id' => $location->id]) }}" class="btn btn-outline-secondary btn-sm" aria-label="Vezi rapoartele"><i class="fa-solid fa-chart-column"></i></a>
                            @if(auth()->user()->canManageLocations())<a href="{{ route('locations.edit', $location) }}" class="btn btn-primary btn-sm" aria-label="Modifica locatia"><i class="fa-solid fa-pen"></i></a>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    @if($hasFilters)
                        <p class="mb-2">Nicio locatie nu corespunde filtrelor selectate.</p>
                        <a href="{{ route('locations.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                    @else
                        <p class="mb-2">Nu exista inca nicio locatie.</p>
                        @if(auth()->user()->canManageLocations())<a href="{{ route('locations.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Adauga prima locatie</a>@endif
                    @endif
                </div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $locations->links() }}</div>
    </div>
</div>
@endsection
