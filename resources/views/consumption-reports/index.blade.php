@extends('layouts.app')

@section('title', 'Consum materiale')

@section('content')
@php
    $hasFilters = request()->filled('search')
        || request()->filled('location_id')
        || request()->filled('catalog_item_id')
        || request()->filled('date_from')
        || request()->filled('date_to');
    $formatQuantity = fn ($value) => \App\Support\LocalizedNumber::quantity($value);
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Consum materiale"
        description="Consumuri raportate pe locatie, cu scadere automata din stoc."
        :count="$totalReports"
        :filtered-count="$reports->total()"
        icon="fa-clipboard-check"
        :create-route="$canCreate ? route('consumption-reports.create') : null"
        create-label="Raporteaza consum"
    />

    <form class="resource-filter-panel" data-auto-submit-filters>
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-6"><label class="resource-filter-label">Cautare</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Numar raport"></div>
            <div class="col-xl-3 col-md-6"><label class="resource-filter-label">Locatie</label><select name="location_id" class="form-select" data-tom-select><option value="">Toate</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
            <div class="col-xl-3 col-md-6"><label class="resource-filter-label">Material</label><select name="catalog_item_id" class="form-select" data-tom-select><option value="">Toate</option>@foreach($items as $item)<option value="{{ $item->id }}" data-search="{{ $item->sku }} {{ $item->barcode }}" @selected((string) request('catalog_item_id') === (string) $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div class="col-xl-1 col-md-3"><label class="resource-filter-label">De la</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
            <div class="col-xl-1 col-md-3"><label class="resource-filter-label">Pana la</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
            <div class="col-xl-1 col-md-12 d-flex gap-2"><button class="btn btn-primary flex-fill" title="Aplica filtrele"><i class="fa-solid fa-filter"></i></button><a href="{{ route('consumption-reports.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a></div>
        </div>
    </form>

    <div class="resource-table-card">
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table">
                <thead><tr><th>Raport</th><th>Locatie</th><th>Materiale consumate</th><th>Responsabil</th><th>Observatii</th><th>Status</th><th class="text-end">Actiuni</th></tr></thead>
                <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $report->number }}</span><span class="resource-secondary"><i class="fa-regular fa-clock me-1"></i>{{ $report->reported_at?->format('d.m.Y H:i') ?? '-' }}</span></div></td>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $report->location?->code ?? '-' }}</span><span class="resource-secondary">{{ $report->location?->name }}</span></div></td>
                        <td>
                            <div class="resource-cell-stack">
                                @foreach($report->lines as $line)<span>{{ $line->catalogItem?->name ?? 'Articol indisponibil' }} <span class="resource-secondary">{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</span></span>@endforeach
                                @if($report->lines_count > $report->lines->count())<span class="resource-secondary fw-semibold">+{{ $report->lines_count - $report->lines->count() }} {{ $report->lines_count - $report->lines->count() === 1 ? 'alt material' : 'alte materiale' }}</span>@endif
                            </div>
                        </td>
                        <td>@if($report->reporter)<span>{{ $report->reporter->name }}</span>@else<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Responsabil indisponibil</span>@endif</td>
                        <td>@if($report->notes)<span class="resource-secondary">{{ \Illuminate\Support\Str::limit($report->notes, 70) }}</span>@else<span class="text-muted">-</span>@endif</td>
                        <td>
                            <div class="resource-cell-stack">
                                <x-status :status="$report->status" />
                                @if($report->modified_at)
                                    <span class="resource-secondary">{{ $report->modified_at->format('d.m.Y H:i') }} · {{ $report->modifier?->name ?? 'Utilizator indisponibil' }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-row-actions justify-content-end">
                                @if($canCorrect)
                                    <x-resource-icon-button :href="route('consumption-reports.edit', $report)" icon="fa-pen" label="Corecteaza consumul" variant="outline-secondary" />
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            @if($hasFilters)
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Niciun consum nu corespunde filtrelor selectate.</span>
                                    <a href="{{ route('consumption-reports.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Nu exista inca rapoarte de consum.</span>
                                    @if($canCreate)<a href="{{ route('consumption-reports.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Raporteaza primul consum</a>@endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($reports as $report)
                <article class="card resource-mobile-card">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $report->number }}</h2>
                                <div class="resource-mobile-card-subtitle"><i class="fa-regular fa-clock me-1"></i>{{ $report->reported_at?->format('d.m.Y H:i') ?? '-' }}</div>
                            </div>
                            <x-status :status="$report->status" />
                        </div>

                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Locatie</span>
                                <strong>{{ $report->location?->code ?? '-' }}</strong>
                                @if($report->location?->name)<span class="resource-secondary">{{ $report->location->name }}</span>@endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Responsabil</span>
                                @if($report->reporter)<strong><i class="fa-solid fa-user-check me-1 text-muted"></i>{{ $report->reporter->name }}</strong>@else<span class="text-warning fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Responsabil indisponibil</span>@endif
                            </div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Materiale consumate</span>
                                @foreach($report->lines as $line)
                                    <span class="d-block"><strong>{{ $line->catalogItem?->name ?? 'Articol indisponibil' }}</strong> <span class="resource-secondary d-inline">{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</span></span>
                                @endforeach
                                @if($report->lines_count > $report->lines->count())<span class="resource-secondary fw-semibold">+{{ $report->lines_count - $report->lines->count() }} {{ $report->lines_count - $report->lines->count() === 1 ? 'alt material' : 'alte materiale' }}</span>@endif
                            </div>
                            @if($report->notes)
                                <div class="resource-mobile-card-wide">
                                    <span class="resource-filter-label">Observatii</span>
                                    <span class="resource-secondary">{{ \Illuminate\Support\Str::limit($report->notes, 120) }}</span>
                                </div>
                            @endif
                            @if($report->modified_at)
                                <div class="resource-mobile-card-wide">
                                    <span class="resource-filter-label">Ultima corectie</span>
                                    <span class="resource-secondary">{{ $report->modified_at->format('d.m.Y H:i') }} · {{ $report->modifier?->name ?? 'Utilizator indisponibil' }}</span>
                                </div>
                            @endif
                        </div>
                        @if($canCorrect)
                            <div class="mt-3 text-end">
                                <a href="{{ route('consumption-reports.edit', $report) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen me-1"></i>Corecteaza
                                </a>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    @if($hasFilters)
                        <p class="mb-2">Niciun consum nu corespunde filtrelor selectate.</p>
                        <a href="{{ route('consumption-reports.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                    @else
                        <p class="mb-2">Nu exista inca rapoarte de consum.</p>
                        @if($canCreate)<a href="{{ route('consumption-reports.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Raporteaza primul consum</a>@endif
                    @endif
                </div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $reports->links() }}</div>
    </div>
</div>
@endsection
