@extends('layouts.app')

@section('title', 'Furnizori')

@php
    $hasFilters = request()->filled('search') || request()->filled('active');
    $formatDate = fn ($value) => $value
        ? \Illuminate\Support\Carbon::parse($value)->format('d.m.Y')
        : null;
@endphp

@section('content')
<div class="resource-shell">
    <x-resource-page-header
        title="Furnizori"
        description="Datele de identificare și contact folosite în comenzi, recepții și istoricul stocului."
        :count="$totalSuppliers"
        :filtered-count="$suppliers->total()"
        icon="fa-building"
        :create-route="$canManage ? route('suppliers.create') : null"
        create-label="Furnizor nou"
    >
        <x-slot:actions>
            <span class="badge text-bg-success">{{ $activeSuppliers }} activi</span>
        </x-slot:actions>
    </x-resource-page-header>

    <form method="get" class="resource-filter-panel" data-auto-submit-filters>
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-lg-7 col-md-6">
                <label class="resource-filter-label">Căutare</label>
                <input
                    name="search"
                    value="{{ request('search') }}"
                    class="form-control"
                    placeholder="Denumire, CUI, persoană de contact, email sau telefon"
                >
            </div>
            <div class="col-lg-3 col-md-3">
                <label class="resource-filter-label">Stare</label>
                <select name="active" class="form-select">
                    <option value="">Toți</option>
                    <option value="1" @selected(request('active') === '1')>Activi</option>
                    <option value="0" @selected(request('active') === '0')>Inactivi</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Caută
                </button>
                @if($hasFilters)
                    <a
                        href="{{ route('suppliers.index', ['filters_reset' => 1]) }}"
                        class="btn btn-outline-secondary"
                        title="Resetează filtrele"
                        aria-label="Resetează filtrele"
                    ><i class="fa-solid fa-rotate-left"></i></a>
                @endif
            </div>
        </div>
    </form>

    <div class="resource-table-card">
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table">
                <thead>
                    <tr>
                        <th>Furnizor</th>
                        <th>Contact</th>
                        <th>Activitate</th>
                        <th>Stare</th>
                        <th class="text-end">Acțiuni</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr>
                            <td>
                                <div class="resource-cell-stack">
                                    <span class="resource-primary">{{ $supplier->name }}</span>
                                    @if($supplier->cui)
                                        <span class="resource-secondary">CUI {{ $supplier->cui }}</span>
                                    @endif
                                    @if($supplier->registration_number)
                                        <span class="resource-secondary">{{ $supplier->registration_number }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="resource-cell-stack">
                                    @if($supplier->contact_person)
                                        <span><i class="fa-solid fa-user me-1"></i>{{ $supplier->contact_person }}</span>
                                    @endif
                                    @if($supplier->email)
                                        <a href="mailto:{{ $supplier->email }}" class="resource-secondary">{{ $supplier->email }}</a>
                                    @endif
                                    @if($supplier->phone)
                                        <a href="tel:{{ $supplier->phone }}" class="resource-secondary">{{ $supplier->phone }}</a>
                                    @endif
                                    @if(!$supplier->contact_person && !$supplier->email && !$supplier->phone)
                                        <span class="resource-secondary">Fără date de contact</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="resource-cell-stack">
                                    <span>
                                        <strong>{{ $supplier->receptions_count }}</strong>
                                        {{ $supplier->receptions_count === 1 ? 'recepție' : 'recepții' }}
                                    </span>
                                    <span class="resource-secondary">
                                        {{ $supplier->negotiated_orders_count }}
                                        {{ $supplier->negotiated_orders_count === 1 ? 'comandă negociată' : 'comenzi negociate' }}
                                    </span>
                                    @if($supplier->open_negotiated_orders_count)
                                        <span class="text-warning small fw-semibold">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                            {{ $supplier->open_negotiated_orders_count }}
                                            {{ $supplier->open_negotiated_orders_count === 1 ? 'comandă deschisă' : 'comenzi deschise' }}
                                        </span>
                                    @elseif($formatDate($supplier->last_reception_at))
                                        <span class="resource-secondary">Ultima recepție: {{ $formatDate($supplier->last_reception_at) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $supplier->active ? 'success' : 'secondary' }}">
                                    {{ $supplier->active ? 'Activ' : 'Inactiv' }}
                                </span>
                            </td>
                            <td>
                                <div class="resource-row-actions">
                                    <a
                                        href="{{ route('supplier-receptions.index', ['supplier_id' => $supplier->id]) }}"
                                        class="btn btn-outline-secondary resource-icon-button"
                                        title="Vezi recepțiile"
                                        aria-label="Vezi recepțiile"
                                    ><i class="fa-solid fa-receipt"></i></a>
                                    @if($canManage)
                                        <x-resource-icon-button
                                            :href="route('suppliers.edit', $supplier)"
                                            icon="fa-pen"
                                            label="Modifică furnizorul"
                                        />
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                @if($hasFilters)
                                    <p class="text-muted mb-2">Niciun furnizor nu corespunde filtrelor selectate.</p>
                                    <a href="{{ route('suppliers.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm">
                                        Resetează filtrele
                                    </a>
                                @else
                                    <p class="text-muted mb-2">Nu există încă furnizori.</p>
                                    @if($canManage)
                                        <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm">Adaugă primul furnizor</a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($suppliers as $supplier)
                <article class="card resource-mobile-card">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $supplier->name }}</h2>
                                @if($supplier->cui)
                                    <div class="resource-mobile-card-subtitle">CUI {{ $supplier->cui }}</div>
                                @endif
                            </div>
                            <span class="badge text-bg-{{ $supplier->active ? 'success' : 'secondary' }}">
                                {{ $supplier->active ? 'Activ' : 'Inactiv' }}
                            </span>
                        </div>

                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Contact</span>
                                <strong>{{ $supplier->contact_person ?: 'Nespecificat' }}</strong>
                                @if($supplier->phone)<span class="resource-secondary">{{ $supplier->phone }}</span>@endif
                                @if($supplier->email)<span class="resource-secondary">{{ $supplier->email }}</span>@endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Activitate</span>
                                <strong>{{ $supplier->receptions_count }} recepții</strong>
                                <span class="resource-secondary">{{ $supplier->negotiated_orders_count }} comenzi negociate</span>
                            </div>
                            @if($supplier->open_negotiated_orders_count)
                                <div class="resource-mobile-card-wide">
                                    <span class="text-warning small fw-semibold">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                        {{ $supplier->open_negotiated_orders_count }}
                                        {{ $supplier->open_negotiated_orders_count === 1 ? 'comandă deschisă' : 'comenzi deschise' }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="resource-mobile-card-actions">
                            <a
                                href="{{ route('supplier-receptions.index', ['supplier_id' => $supplier->id]) }}"
                                class="btn btn-outline-secondary btn-sm"
                            ><i class="fa-solid fa-receipt me-1"></i>Recepții</a>
                            @if($canManage)
                                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-pen me-1"></i>Modifică
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    <p class="mb-2">{{ $hasFilters ? 'Niciun furnizor nu corespunde filtrelor selectate.' : 'Nu există încă furnizori.' }}</p>
                    @if($hasFilters)
                        <a href="{{ route('suppliers.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm">Resetează filtrele</a>
                    @elseif($canManage)
                        <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm">Adaugă primul furnizor</a>
                    @endif
                </div>
            @endforelse
        </div>

        @if($suppliers->hasPages())
            <div class="resource-table-footer">{{ $suppliers->links() }}</div>
        @endif
    </div>
</div>
@endsection
