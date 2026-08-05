@extends('layouts.app')

@php($editing = (bool) $supplier)
@section('title', $editing ? 'Modifică furnizorul' : 'Furnizor nou')

@section('content')
<x-resource-form-shell
    :title="$editing ? 'Modifică furnizorul' : 'Furnizor nou'"
    description="Datele furnizorului rămân legate de comenzile, recepțiile și loturile existente."
    :back-route="route('suppliers.index')"
    icon="fa-building"
>
    <form
        method="post"
        action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
        class="resource-form-card"
    >
        @csrf
        @if($editing) @method('put') @endif

        <section class="resource-form-section">
            <div class="resource-form-section-title">Identificare</div>
            <div class="row g-3">
                <div class="col-lg-6">
                    <label class="form-label">Denumire furnizor</label>
                    <input
                        name="name"
                        value="{{ old('name', $supplier?->name) }}"
                        class="form-control"
                        maxlength="255"
                        required
                        autofocus
                    >
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">CUI</label>
                    <input
                        name="cui"
                        value="{{ old('cui', $supplier?->cui) }}"
                        class="form-control"
                        maxlength="32"
                        placeholder="Ex. RO12345678"
                    >
                    <div class="form-text">Aplicația verifică automat dacă acest CUI există deja.</div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label">Nr. Registrul Comerțului</label>
                    <input
                        name="registration_number"
                        value="{{ old('registration_number', $supplier?->registration_number) }}"
                        class="form-control"
                        maxlength="80"
                        placeholder="Ex. J00/000/2026"
                    >
                </div>
                <div class="col-12">
                    <label class="form-label">Adresă</label>
                    <textarea name="address" class="form-control" rows="2" maxlength="2000">{{ old('address', $supplier?->address) }}</textarea>
                </div>
            </div>
        </section>

        <section class="resource-form-section">
            <div class="resource-form-section-title">Contact</div>
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label">Persoană de contact</label>
                    <input
                        name="contact_person"
                        value="{{ old('contact_person', $supplier?->contact_person) }}"
                        class="form-control"
                        maxlength="255"
                    >
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Email</label>
                    <input
                        name="email"
                        type="email"
                        value="{{ old('email', $supplier?->email) }}"
                        class="form-control"
                        maxlength="255"
                    >
                </div>
                <div class="col-lg-4">
                    <label class="form-label">Telefon</label>
                    <input
                        name="phone"
                        value="{{ old('phone', $supplier?->phone) }}"
                        class="form-control"
                        maxlength="64"
                    >
                </div>
            </div>
        </section>

        <section class="resource-form-section">
            <div class="resource-form-section-title">Observații</div>
            <textarea name="notes" class="form-control" rows="4" maxlength="4000">{{ old('notes', $supplier?->notes) }}</textarea>
        </section>

        <div class="resource-form-actions-bar">
            <a href="{{ route('suppliers.index') }}" class="btn btn-outline-secondary">Renunță</a>
            <button class="btn btn-success">
                <i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salvează modificările' : 'Creează furnizorul' }}
            </button>
        </div>
    </form>

    @if($editing)
        <section class="resource-form-card mt-4">
            <div class="resource-form-section">
                <div class="resource-form-section-title">Starea furnizorului</div>

                @if($supplier->active && $openOrderCount > 0)
                    <div class="alert alert-warning mb-3">
                        <div class="fw-semibold">
                            Furnizorul nu poate fi dezactivat deoarece are
                            {{ $openOrderCount === 1 ? 'o comandă negociată deschisă' : $openOrderCount.' comenzi negociate deschise' }}.
                        </div>
                        <div class="mt-1">Închide sau anulează comenzile înainte să dezactivezi furnizorul.</div>
                        @if(auth()->user()->hasAbility('negotiated-orders.view'))
                            <a
                                href="{{ route('negotiated-orders.index', ['supplier_id' => $supplier->id, 'status' => 'created']) }}"
                                class="btn btn-outline-warning btn-sm mt-3"
                            ><i class="fa-solid fa-file-invoice-dollar me-1"></i>Vezi comenzile deschise</a>
                        @else
                            <div class="small mt-2">Un administrator poate închide sau anula aceste comenzi.</div>
                        @endif
                    </div>
                @endif

                @if($supplier->active)
                    <p class="text-secondary">
                        După dezactivare, furnizorul rămâne vizibil în documentele existente, dar nu mai poate fi ales în documente noi.
                    </p>
                    <form method="post" action="{{ route('suppliers.deactivate', $supplier) }}">
                        @csrf
                        <button class="btn btn-outline-danger" @disabled($openOrderCount > 0)>
                            <i class="fa-solid fa-ban me-1"></i>Dezactivează furnizorul
                        </button>
                    </form>
                @else
                    <p class="text-secondary">
                        Furnizorul este inactiv și nu poate fi ales în comenzi sau recepții noi. Istoricul lui a fost păstrat.
                    </p>
                    <form method="post" action="{{ route('suppliers.activate', $supplier) }}">
                        @csrf
                        <button class="btn btn-success">
                            <i class="fa-solid fa-rotate-left me-1"></i>Reactivează furnizorul
                        </button>
                    </form>
                @endif
            </div>
        </section>
    @endif
</x-resource-form-shell>
@endsection
