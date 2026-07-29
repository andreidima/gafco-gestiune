@extends('layouts.app')

@section('title', 'Alerte')

@section('content')
@php
    $hasFilters = $filters['search']
        || $filters['alert_type']
        || $filters['severity']
        || $filters['location_id']
        || $filters['status'] !== 'active';
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Alerte"
        description="Situații de stoc, documente și planuri de materiale care necesită verificare. Alertele se închid automat când motivul dispare."
        :count="$alerts->total()"
        icon="fa-triangle-exclamation"
    >
        <x-slot:actions>
            <x-live-view view-key="alerts-index" />
            <span class="badge rounded-pill text-bg-warning">{{ $activeCount }} active</span>
            @if($criticalCount)
                <span class="badge rounded-pill text-bg-danger">{{ $criticalCount }} critice</span>
            @endif
            @if($canConfigure)
                <a href="{{ route('alert-rules.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-sliders me-1"></i>Reguli
                </a>
            @endif
        </x-slot:actions>
    </x-resource-page-header>

    <form class="resource-filter-panel" data-auto-submit-filters>
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-3 col-lg-6">
                <label class="resource-filter-label">Căutare</label>
                <input name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Material, lot, document sau locație">
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="resource-filter-label">Tip</label>
                <select name="alert_type" class="form-select">
                    <option value="">Toate tipurile</option>
                    @foreach($typeLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['alert_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="resource-filter-label">Locație</label>
                <select name="location_id" class="form-select">
                    <option value="">Toate locațiile</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) $filters['location_id'] === $location->id)>
                            {{ $location->code }} — {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label class="resource-filter-label">Prioritate</label>
                <select name="severity" class="form-select">
                    <option value="">Toate</option>
                    @foreach($severityLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['severity'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-1 col-md-4">
                <label class="resource-filter-label">Stare</label>
                <select name="status" class="form-select">
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="resolved" @selected($filters['status'] === 'resolved')>Închise</option>
                    <option value="all" @selected($filters['status'] === 'all')>Toate</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-8 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrează</button>
                <a href="{{ route('alerts.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Resetează filtrele">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="resource-table-card">
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Alertă</th>
                        <th>Locație</th>
                        <th>Termen / vechime</th>
                        <th>Stare</th>
                        <th class="text-end"><span class="visually-hidden">Acțiune</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($alerts as $alert)
                    <tr class="{{ $alert->isActive() ? ($alert->severity === 'danger' ? 'resource-row-alert resource-row-alert-danger' : 'resource-row-alert resource-row-alert-warning') : '' }}">
                        <td>
                            <span class="badge {{ $alert->severity === 'danger' ? 'text-bg-danger' : 'text-bg-warning' }}">
                                {{ $typeLabels[$alert->alert_type] ?? $alert->alert_type }}
                            </span>
                            <strong class="d-block mt-1">{{ $alert->title }}</strong>
                            <span class="resource-secondary">{{ $alert->message }}</span>
                        </td>
                        <td>
                            <strong>{{ $alert->location?->code ?? '—' }}</strong>
                            <span class="resource-secondary">{{ $alert->location?->name }}</span>
                        </td>
                        <td class="text-nowrap">
                            @if($alert->alert_type === 'lot_expiration' && $alert->due_at)
                                <strong>{{ $alert->due_at->format('d.m.Y') }}</strong>
                                <span class="resource-secondary">
                                    {{ $alert->due_at->isPast() ? 'Termen depășit' : $alert->due_at->locale('ro')->diffForHumans() }}
                                </span>
                            @else
                                <strong>{{ $alert->triggered_at->format('d.m.Y H:i') }}</strong>
                                <span class="resource-secondary">{{ $alert->triggered_at->locale('ro')->diffForHumans() }}</span>
                            @endif
                        </td>
                        <td>
                            @if($alert->isActive())
                                <span class="badge {{ $alert->severity === 'danger' ? 'text-bg-danger' : 'text-bg-warning' }}">Activă</span>
                            @else
                                <span class="badge text-bg-success">Închisă automat</span>
                                <span class="resource-secondary">{{ $alert->resolved_at->format('d.m.Y H:i') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ $alert->url }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                Verifică <i class="fa-solid fa-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-secondary">
                            {{ $hasFilters ? 'Nu există alerte pentru filtrele selectate.' : 'Nu există alerte active în aria ta.' }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($alerts as $alert)
                <article class="card resource-mobile-card {{ $alert->isActive() ? ($alert->severity === 'danger' ? 'resource-row-alert resource-row-alert-danger' : 'resource-row-alert resource-row-alert-warning') : '' }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div>
                                <span class="badge {{ $alert->severity === 'danger' ? 'text-bg-danger' : 'text-bg-warning' }}">
                                    {{ $typeLabels[$alert->alert_type] ?? $alert->alert_type }}
                                </span>
                                <h2 class="resource-mobile-card-title mt-2">{{ $alert->title }}</h2>
                            </div>
                            <span class="badge {{ $alert->isActive() ? 'text-bg-warning' : 'text-bg-success' }}">
                                {{ $alert->isActive() ? 'Activă' : 'Închisă' }}
                            </span>
                        </div>
                        <p class="mb-3 text-secondary">{{ $alert->message }}</p>
                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Locație</span>
                                <strong>{{ $alert->location?->code ?? '—' }}</strong>
                                <span class="resource-secondary">{{ $alert->location?->name }}</span>
                            </div>
                            <div>
                                <span class="resource-filter-label">{{ $alert->due_at ? 'Termen' : 'Înregistrat' }}</span>
                                <strong>{{ ($alert->due_at ?? $alert->triggered_at)->format('d.m.Y H:i') }}</strong>
                            </div>
                        </div>
                        <div class="resource-mobile-card-actions">
                            <a href="{{ $alert->url }}" class="btn btn-outline-primary btn-sm">Verifică situația</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    {{ $hasFilters ? 'Nu există alerte pentru filtrele selectate.' : 'Nu există alerte active în aria ta.' }}
                </div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $alerts->links() }}</div>
    </div>
</div>
@endsection
