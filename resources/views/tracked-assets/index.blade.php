@extends('layouts.app')

@section('title', 'Echipamente')

@section('content')
@php
    $statusLabels = ['available'=>'Disponibil','in_use'=>'In folosinta','in_transfer'=>'In transfer','maintenance'=>'Service','lost'=>'Lipsa'];
    $conditionLabels = ['good'=>'Bun','used'=>'Uzura','damaged'=>'Deteriorat','needs_service'=>'Necesita service'];
    $statusVariants = ['available'=>'success','in_use'=>'primary','in_transfer'=>'info','maintenance'=>'warning','lost'=>'danger'];
    $conditionVariants = ['good'=>'light','used'=>'secondary','damaged'=>'danger','needs_service'=>'warning'];
    $verificationDueBefore = now()->subDays(30);
    $hasFilters = request()->filled('search')
        || request()->filled('catalog_item_id')
        || request()->filled('location_id')
        || request()->filled('status')
        || request()->filled('condition');
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Echipamente QR"
        description="Echipamente si scule urmarite individual prin cod QR."
        :count="$totalAssets"
        :filtered-count="$assets->total()"
        icon="fa-screwdriver-wrench"
        :create-route="auth()->user()->canManageTrackedAssets() ? route('tracked-assets.create') : null"
        create-label="Echipament nou"
    />

    <form class="resource-filter-panel" data-auto-submit-filters>
        <input type="hidden" name="filters_submitted" value="1">
        @if(request()->filled('catalog_item_id'))<input type="hidden" name="catalog_item_id" value="{{ request('catalog_item_id') }}">@endif
        <div class="row g-2 align-items-end">
            <div class="col-xl-3"><label class="resource-filter-label">Cautare</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cod intern, QR, serie sau denumire"></div>
            <div class="col-xl-3 col-md-5"><label class="resource-filter-label">Locatie</label><select name="location_id" class="form-select"><option value="">Toate locatiile</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)request('location_id') === (string)$location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="resource-filter-label">Status</label><select name="status" class="form-select"><option value="">Toate</option>@foreach($statusLabels as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="resource-filter-label">Conditie</label><select name="condition" class="form-select"><option value="">Toate</option>@foreach($conditionLabels as $value=>$label)<option value="{{ $value }}" @selected(request('condition') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-xl-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Cauta</button><a href="{{ route('tracked-assets.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a></div>
        </div>
    </form>

    <div class="resource-table-card">
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table">
                <thead><tr><th>Echipament</th><th>Localizare</th><th>Stare</th><th>Identificare</th><th>Verificat</th><th class="text-end">Actiuni</th></tr></thead>
                <tbody>
                @forelse($assets as $asset)
                    @php
                        $verificationIsDue = ! $asset->last_verified_at || $asset->last_verified_at->lte($verificationDueBefore);
                    @endphp
                    <tr>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $asset->catalogItem?->name ?? 'Articol indisponibil' }}</span><span class="resource-code">{{ $asset->asset_code }}</span>@if($asset->serial_number)<span class="resource-secondary">Serie {{ $asset->serial_number }}</span>@endif</div></td>
                        <td><div class="resource-cell-stack"><span class="{{ $asset->currentLocation ? '' : 'text-warning' }}"><i class="fa-solid fa-location-dot me-1 text-muted"></i>{{ $asset->currentLocation?->name ?? 'Fara locatie' }}</span>@if($asset->currentCustodian)<span class="resource-secondary"><i class="fa-solid fa-user me-1"></i>{{ $asset->currentCustodian->name }}</span>@endif</div></td>
                        <td>
                            <div class="resource-cell-stack">
                                <span><span class="resource-secondary me-1">Disponibilitate</span><a href="{{ route('tracked-assets.show', $asset) }}" class="badge status-badge-link text-bg-{{ $statusVariants[$asset->status] ?? 'secondary' }}">{{ $statusLabels[$asset->status] ?? $asset->status }}<span class="visually-hidden"> — deschide echipamentul</span></a></span>
                                <span><span class="resource-secondary me-1">Conditie</span><span class="badge text-bg-{{ $conditionVariants[$asset->condition] ?? 'secondary' }} {{ $asset->condition === 'good' ? 'border' : '' }}">{{ $conditionLabels[$asset->condition] ?? $asset->condition }}</span></span>
                            </div>
                        </td>
                        <td><span class="qr-mini"><i class="fa-solid fa-qrcode"></i> {{ $asset->qr_code }}</span></td>
                        <td>
                            <div class="resource-cell-stack">
                                @if(! $asset->last_verified_at)
                                    <span class="text-danger fw-semibold"><i class="fa-solid fa-circle-exclamation me-1"></i>Niciodata verificat</span>
                                @else
                                    <span class="{{ $verificationIsDue ? 'text-warning fw-semibold' : '' }}"><i class="fa-solid {{ $verificationIsDue ? 'fa-triangle-exclamation' : 'fa-circle-check text-success' }} me-1"></i>{{ $verificationIsDue ? 'De reverificat' : ucfirst($asset->last_verified_at->locale('ro')->diffForHumans()) }}</span>
                                    <span class="resource-secondary">{{ $verificationIsDue ? ucfirst($asset->last_verified_at->locale('ro')->diffForHumans()).' · ' : '' }}{{ $asset->last_verified_at->format('d.m.Y H:i') }}</span>
                                @endif
                            </div>
                        </td>
                        <td><div class="resource-row-actions"><x-resource-icon-button :href="route('tracked-assets.show', $asset)" icon="fa-eye" label="Vezi istoricul" />@if(auth()->user()->canManageTrackedAssets())<x-resource-icon-button :href="route('tracked-assets.edit', $asset)" icon="fa-pen" label="Modifica echipamentul" variant="outline-secondary" />@endif</div></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            @if($hasFilters)
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Niciun echipament nu corespunde filtrelor selectate.</span>
                                    <a href="{{ route('tracked-assets.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Nu exista inca echipamente urmarite prin QR.</span>
                                    @if(auth()->user()->canManageTrackedAssets())<a href="{{ route('tracked-assets.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Adauga primul echipament</a>@endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($assets as $asset)
                @php
                    $verificationIsDue = ! $asset->last_verified_at || $asset->last_verified_at->lte($verificationDueBefore);
                    $hasCriticalState = $asset->status === 'lost' || $asset->condition === 'damaged';
                    $hasWarningState = $verificationIsDue || $asset->status === 'maintenance' || $asset->condition === 'needs_service';
                @endphp
                <article class="card resource-mobile-card {{ $hasCriticalState ? 'resource-row-alert resource-row-alert-danger' : ($hasWarningState ? 'resource-row-alert resource-row-alert-warning' : '') }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $asset->catalogItem?->name ?? 'Articol indisponibil' }}</h2>
                                <div class="resource-code">{{ $asset->asset_code }}</div>
                                @if($asset->serial_number)<div class="resource-mobile-card-subtitle">Serie {{ $asset->serial_number }}</div>@endif
                            </div>
                            <a href="{{ route('tracked-assets.show', $asset) }}" class="badge status-badge-link text-bg-{{ $statusVariants[$asset->status] ?? 'secondary' }}">{{ $statusLabels[$asset->status] ?? $asset->status }}<span class="visually-hidden"> — deschide echipamentul</span></a>
                        </div>

                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Localizare</span>
                                <strong class="{{ $asset->currentLocation ? '' : 'text-warning' }}"><i class="fa-solid fa-location-dot me-1 text-muted"></i>{{ $asset->currentLocation?->name ?? 'Fara locatie' }}</strong>
                                @if($asset->currentCustodian)<span class="resource-secondary"><i class="fa-solid fa-user me-1"></i>{{ $asset->currentCustodian->name }}</span>@endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Conditie</span>
                                <span><span class="badge text-bg-{{ $conditionVariants[$asset->condition] ?? 'secondary' }} {{ $asset->condition === 'good' ? 'border' : '' }}">{{ $conditionLabels[$asset->condition] ?? $asset->condition }}</span></span>
                                <span class="resource-secondary"><i class="fa-solid fa-qrcode me-1"></i>{{ $asset->qr_code }}</span>
                            </div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Ultima verificare</span>
                                @if(! $asset->last_verified_at)
                                    <strong class="text-danger"><i class="fa-solid fa-circle-exclamation me-1"></i>Niciodata verificat</strong>
                                @else
                                    <strong class="{{ $verificationIsDue ? 'text-warning' : '' }}"><i class="fa-solid {{ $verificationIsDue ? 'fa-triangle-exclamation' : 'fa-circle-check text-success' }} me-1"></i>{{ $verificationIsDue ? 'De reverificat' : ucfirst($asset->last_verified_at->locale('ro')->diffForHumans()) }}</strong>
                                    <span class="resource-secondary">{{ ucfirst($asset->last_verified_at->locale('ro')->diffForHumans()) }} &middot; {{ $asset->last_verified_at->format('d.m.Y H:i') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('tracked-assets.show', $asset) }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-eye me-1"></i>Istoric</a>
                            @if(auth()->user()->canManageTrackedAssets())<a href="{{ route('tracked-assets.edit', $asset) }}" class="btn btn-outline-secondary btn-sm" aria-label="Modifica echipamentul"><i class="fa-solid fa-pen"></i></a>@endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    @if($hasFilters)
                        <p class="mb-2">Niciun echipament nu corespunde filtrelor selectate.</p>
                        <a href="{{ route('tracked-assets.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                    @else
                        <p class="mb-2">Nu exista inca echipamente urmarite prin QR.</p>
                        @if(auth()->user()->canManageTrackedAssets())<a href="{{ route('tracked-assets.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Adauga primul echipament</a>@endif
                    @endif
                </div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $assets->links() }}</div>
    </div>
</div>
@endsection
