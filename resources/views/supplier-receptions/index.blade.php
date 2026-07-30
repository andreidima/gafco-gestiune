@extends('layouts.app')

@section('title', 'Receptii furnizori')

@section('content')
@php
    $hasFilters = request()->filled('search')
        || request()->filled('location_id')
        || request()->filled('supplier_id')
        || request()->filled('catalog_item_id')
        || request()->filled('document_type')
        || request()->filled('date_from')
        || request()->filled('date_to');
    $formatQuantity = fn ($value) => \App\Support\LocalizedNumber::quantity($value);
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Recepții furnizori"
        description="Intrări de materiale în baze și șantiere, cu documentele sursă la vedere."
        :count="$totalReceptions"
        :filtered-count="$receptions->total()"
        icon="fa-truck-ramp-box"
        :create-route="$canCreate ? route('supplier-receptions.create') : null"
        create-label="Recepție nouă"
    >
        <x-slot:actions>
            @if($canUploadDocuments)
                <a href="{{ route('reception-intakes.create') }}" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-camera me-1"></i>Trimite documente
                </a>
            @endif
            @if($canViewIntakes)
                <a href="{{ route('reception-intakes.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-inbox me-1"></i>De procesat
                    @if($openIntakeCount)
                        <span class="badge rounded-pill text-bg-warning ms-1">{{ $openIntakeCount }}</span>
                    @endif
                </a>
            @endif
        </x-slot:actions>
    </x-resource-page-header>

    <form class="resource-filter-panel" data-auto-submit-filters data-live-filter-target="#supplier-receptions-results">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-3 col-md-6"><label class="resource-filter-label">Cautare</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Numar receptie sau document"></div>
            <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Locatie</label><select name="location_id" class="form-select" data-tom-select><option value="">Toate</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Furnizor</label><select name="supplier_id" class="form-select" data-tom-select><option value="">Toti</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" data-search="{{ $supplier->cui }} {{ $supplier->registration_number }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}{{ $supplier->active ? '' : ' (inactiv)' }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Articol</label><select name="catalog_item_id" class="form-select" data-tom-select><option value="">Toate</option>@foreach($items as $item)<option value="{{ $item->id }}" data-search="{{ $item->sku }} {{ $item->barcode }}" @selected((string) request('catalog_item_id') === (string) $item->id)>{{ $item->name }}</option>@endforeach</select></div>
            <div class="col-xl-1 col-md-4"><label class="resource-filter-label">Document</label><select name="document_type" class="form-select"><option value="">Toate</option><option value="aviz" @selected(request('document_type') === 'aviz')>Aviz</option><option value="factura" @selected(request('document_type') === 'factura')>Factura</option></select></div>
            <div class="col-xl-1 col-md-4"><label class="resource-filter-label">De la</label><input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control"></div>
            <div class="col-xl-1 col-md-4"><label class="resource-filter-label">Pana la</label><input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control"></div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Aplica filtrele</button>
                <a href="{{ route('supplier-receptions.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </div>
    </form>

    <div id="supplier-receptions-results" class="resource-table-card" data-live-filter-results>
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table">
                <thead><tr><th>Receptie</th><th>Locatie</th><th>Furnizor / document</th><th>Continut</th><th>Observatii</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($receptions as $reception)
                    <tr data-href="{{ route('supplier-receptions.show', $reception) }}">
                        <td><div class="resource-cell-stack"><a href="{{ route('supplier-receptions.show', $reception) }}" class="resource-primary text-decoration-none">{{ $reception->number }}</a><span class="resource-secondary"><i class="fa-regular fa-clock me-1"></i>{{ $reception->received_at?->format('d.m.Y H:i') ?? '-' }}</span>@if($reception->receiver)<span class="resource-secondary"><i class="fa-solid fa-user-check me-1"></i>{{ $reception->receiver->name }}</span>@endif</div></td>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $reception->location?->code ?? '-' }}</span><span class="resource-secondary">{{ $reception->location?->name }}</span></div></td>
                        <td><div class="resource-cell-stack">@if($reception->supplier)<span>{{ $reception->supplier->name }}</span>@endif<span class="resource-secondary">{{ strtoupper($reception->document_type) }}@if($reception->document_number) &middot; {{ $reception->document_number }}@endif</span></div></td>
                        <td>
                            <div class="resource-cell-stack">
                                @foreach($reception->lines as $line)<span>{{ $line->catalogItem?->name ?? 'Articol indisponibil' }} <span class="resource-secondary">{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</span></span>@endforeach
                                @if($reception->lines_count > $reception->lines->count())<span class="resource-secondary fw-semibold">+{{ $reception->lines_count - $reception->lines->count() }} {{ $reception->lines_count - $reception->lines->count() === 1 ? 'alt articol' : 'alte articole' }}</span>@endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                @if($reception->notes)
                                    <span class="resource-secondary">{{ Illuminate\Support\Str::limit($reception->notes, 70) }}</span>
                                @endif
                                @if($reception->documents_count)
                                    <span class="resource-secondary">
                                        <i class="fa-solid fa-paperclip me-1"></i>{{ $reception->documents_count }}
                                        {{ $reception->documents_count === 1 ? 'fișier' : 'fișiere' }}
                                    </span>
                                @endif
                                @if(!$reception->notes && !$reception->documents_count)
                                    <span class="text-muted">-</span>
                                @endif
                            </div>
                        </td>
                        <td><x-status :status="$reception->status" :href="route('supplier-receptions.show', $reception)" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            @if($hasFilters)
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Nicio receptie nu corespunde filtrelor selectate.</span>
                                    <a href="{{ route('supplier-receptions.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Nu exista inca receptii de la furnizori.</span>
                                    @if($canCreate)<a href="{{ route('supplier-receptions.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Inregistreaza prima receptie</a>@endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($receptions as $reception)
                <article class="card resource-mobile-card" data-href="{{ route('supplier-receptions.show', $reception) }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $reception->number }}</h2>
                                <div class="resource-mobile-card-subtitle"><i class="fa-regular fa-clock me-1"></i>{{ $reception->received_at?->format('d.m.Y H:i') ?? '-' }}</div>
                                @if($reception->receiver)<div class="resource-mobile-card-subtitle"><i class="fa-solid fa-user-check me-1"></i>{{ $reception->receiver->name }}</div>@endif
                            </div>
                            <x-status :status="$reception->status" :href="route('supplier-receptions.show', $reception)" />
                        </div>

                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Locatie</span>
                                <strong>{{ $reception->location?->code ?? '-' }}</strong>
                                @if($reception->location?->name)<span class="resource-secondary">{{ $reception->location->name }}</span>@endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Furnizor / document</span>
                                <strong>{{ $reception->supplier?->name ?? 'Furnizor nespecificat' }}</strong>
                                <span class="resource-secondary">{{ strtoupper($reception->document_type) }}@if($reception->document_number) &middot; {{ $reception->document_number }}@endif</span>
                            </div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Continut</span>
                                @foreach($reception->lines as $line)
                                    <span class="d-block"><strong>{{ $line->catalogItem?->name ?? 'Articol indisponibil' }}</strong> <span class="resource-secondary d-inline">{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</span></span>
                                @endforeach
                                @if($reception->lines_count > $reception->lines->count())<span class="resource-secondary fw-semibold">+{{ $reception->lines_count - $reception->lines->count() }} {{ $reception->lines_count - $reception->lines->count() === 1 ? 'alt articol' : 'alte articole' }}</span>@endif
                            </div>
                            @if($reception->notes)
                                <div class="resource-mobile-card-wide">
                                    <span class="resource-filter-label">Observatii</span>
                                    <span class="resource-secondary">{{ Illuminate\Support\Str::limit($reception->notes, 120) }}</span>
                                </div>
                            @endif
                            @if($reception->documents_count)
                                <div>
                                    <span class="resource-filter-label">Fișiere</span>
                                    <strong><i class="fa-solid fa-paperclip me-1"></i>{{ $reception->documents_count }}</strong>
                                </div>
                            @endif
                        </div>
                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('supplier-receptions.show', $reception) }}" class="btn btn-outline-primary btn-sm">Deschide</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    @if($hasFilters)
                        <p class="mb-2">Nicio receptie nu corespunde filtrelor selectate.</p>
                        <a href="{{ route('supplier-receptions.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                    @else
                        <p class="mb-2">Nu exista inca receptii de la furnizori.</p>
                        @if($canCreate)<a href="{{ route('supplier-receptions.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Inregistreaza prima receptie</a>@endif
                    @endif
                </div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $receptions->links() }}</div>
    </div>
</div>
@endsection
