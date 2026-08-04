@extends('layouts.app')

@php
    $editing = (bool) $location;
    $selectedManagerIds = collect(old(
        'manager_user_ids',
        $location?->activeManagers?->sortByDesc(fn ($manager) => (bool) $manager->pivot->is_primary)->pluck('id')->all() ?? [],
    ))
        ->map(fn ($id) => (int) $id)
        ->all();
    $managerRoleLabels = [
        'super-admin' => 'Super-administrator',
        'admin' => 'Administrator',
        'dispecer' => 'Dispecer',
        'sef-santier' => 'Șef de șantier',
        'gestionar-baza' => 'Gestionar de bază',
    ];
@endphp
@section('title', $editing ? 'Modifica locatia' : 'Locatie noua')

@section('content')
<x-resource-form-shell
    :title="$editing ? 'Modifica locatia' : 'Locatie noua'"
    description="Date operationale, adresa si persoanele care pot aproba pentru aceasta locatie."
    :back-route="route('locations.index')"
    icon="fa-building"
>
    <form method="post" action="{{ $editing ? route('locations.update', $location) : route('locations.store') }}" class="resource-form-card">
        @csrf
        @if($editing) @method('put') @endif
        <div class="resource-form-section">
            <div class="resource-form-section-title">Identificare</div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Tip</label><select name="type" class="form-select" required><option value="site" @selected(old('type', $location?->type ?? 'site') === 'site')>Santier</option><option value="base" @selected(old('type', $location?->type) === 'base')>Baza</option></select></div>
                <div class="col-md-3"><label class="form-label">Cod</label><input name="code" data-internal-code autocapitalize="characters" spellcheck="false" value="{{ old('code', $location?->code) }}" class="form-control text-uppercase" required></div>
                <div class="col-md-6"><label class="form-label">Denumire</label><input name="name" value="{{ old('name', $location?->name) }}" class="form-control" required autofocus></div>
                <div class="col-md-9"><label class="form-label">Adresa</label><input name="address" value="{{ old('address', $location?->address) }}" class="form-control"></div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="mb-2">
                        <div class="form-check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" class="form-check-input @error('active') is-invalid @enderror" id="location-active" @checked(old('active', $location?->active ?? true))><label class="form-check-label" for="location-active">Locație activă</label></div>
                        @error('active')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @if($editing)<div class="form-text">Pentru dezactivare, locația trebuie să nu mai aibă echipamente, stoc pozitiv, aprobări în așteptare sau transferuri active.</div>@endif
                    </div>
                </div>
            </div>
        </div>
        <div class="resource-form-section">
            <div class="resource-form-section-title">Responsabili si observatii</div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Responsabili activi</label>
                    <select name="manager_user_ids[]" class="form-select" multiple data-tom-select data-manager-selector>
                        @foreach($managers as $manager)
                            @php($roleNames = $manager->roles->pluck('name')->map(fn ($role) => $managerRoleLabels[$role] ?? $role)->implode(', '))
                            <option value="{{ $manager->id }}" data-search="{{ $manager->login_code }}" @selected(in_array($manager->id, $selectedManagerIds, true))>{{ $manager->name }}{{ $roleNames ? ' · '.$roleNames : '' }}</option>
                        @endforeach
                    </select>
                    <div class="manager-selection-guidance mt-2">
                        <div class="manager-selection-status" data-manager-selection-status>
                            <i class="fa-solid fa-users me-1"></i><span>{{ count($selectedManagerIds) }} {{ count($selectedManagerIds) === 1 ? 'responsabil selectat' : 'responsabili selectați' }}</span>
                        </div>
                        <ul class="mb-0">
                            <li>Toți responsabilii activi sunt notificați, dar aprobarea unuia singur este suficientă.</li>
                            <li>Eliminarea oprește notificările viitoare și, pentru rolurile locale, accesul la locație; aprobările deja înregistrate rămân în istoric.</li>
                        </ul>
                    </div>
                </div>
                <div class="col-12"><label class="form-label">Observatii</label><textarea name="notes" class="form-control" rows="4">{{ old('notes', $location?->notes) }}</textarea></div>
            </div>
        </div>
        <div class="resource-form-actions-bar"><a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">Renunta</a><button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salveaza modificarile' : 'Creeaza locatia' }}</button></div>
    </form>
</x-resource-form-shell>
@endsection
