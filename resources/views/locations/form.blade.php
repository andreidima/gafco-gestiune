@extends('layouts.app')

@php($editing = (bool) $location)
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
                <div class="col-md-3"><label class="form-label">Cod</label><input name="code" value="{{ old('code', $location?->code) }}" class="form-control text-uppercase" required></div>
                <div class="col-md-6"><label class="form-label">Denumire</label><input name="name" value="{{ old('name', $location?->name) }}" class="form-control" required autofocus></div>
                <div class="col-md-9"><label class="form-label">Adresa</label><input name="address" value="{{ old('address', $location?->address) }}" class="form-control"></div>
                <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" class="form-check-input" id="location-active" @checked(old('active', $location?->active ?? true))><label class="form-check-label" for="location-active">Locatie activa</label></div></div>
            </div>
        </div>
        <div class="resource-form-section">
            <div class="resource-form-section-title">Responsabili si observatii</div>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Responsabili activi</label><select name="manager_user_ids[]" class="form-select" multiple data-tom-select>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected(in_array($manager->id, old('manager_user_ids', $location?->activeManagers?->pluck('id')->all() ?? [])))>{{ $manager->name }}</option>@endforeach</select><div class="form-text">Toti sunt notificati; aprobarea unuia dintre ei este suficienta.</div></div>
                <div class="col-12"><label class="form-label">Observatii</label><textarea name="notes" class="form-control" rows="4">{{ old('notes', $location?->notes) }}</textarea></div>
            </div>
        </div>
        <div class="resource-form-actions-bar"><a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">Renunta</a><button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salveaza modificarile' : 'Creeaza locatia' }}</button></div>
    </form>
</x-resource-form-shell>
@endsection
