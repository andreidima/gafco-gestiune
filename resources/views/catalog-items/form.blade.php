@extends('layouts.app')

@php($editing = (bool) $item)
@section('title', $editing ? 'Modifica articol' : 'Articol nou')

@section('content')
<x-resource-form-shell
    :title="$editing ? 'Modifica articolul' : 'Articol nou'"
    description="Datele de baza folosite in stocuri, receptii, transferuri si consumuri."
    :back-route="route('catalog-items.index')"
    icon="fa-box"
>
    <form method="post" action="{{ $editing ? route('catalog-items.update', $item) : route('catalog-items.store') }}" class="resource-form-card">
        @csrf
        @if($editing) @method('put') @endif
        <div class="resource-form-section">
            <div class="resource-form-section-title">Identificare</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Denumire</label><input name="name" value="{{ old('name', $item?->name) }}" class="form-control" required autofocus></div>
                <div class="col-md-3"><label class="form-label">SKU</label><input name="sku" value="{{ old('sku', $item?->sku) }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Cod de bare</label><input name="barcode" value="{{ old('barcode', $item?->barcode) }}" class="form-control"></div>
            </div>
        </div>
        <div class="resource-form-section">
            <div class="resource-form-section-title">Clasificare si urmarire</div>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Categorie</label><select name="category" class="form-select" required>@foreach(['material'=>'Material','equipment'=>'Utilaj / echipament','tool'=>'Scula'] as $value=>$label)<option value="{{ $value }}" @selected(old('category', $item?->category ?? 'material') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Tip urmarire</label><select name="tracking_type" class="form-select" required><option value="quantity" @selected(old('tracking_type', $item?->tracking_type ?? 'quantity') === 'quantity')>Cantitativ</option><option value="serialized" @selected(old('tracking_type', $item?->tracking_type) === 'serialized')>Unic / QR</option></select></div>
                <div class="col-md-2"><label class="form-label">Unitate de masura</label><input name="unit" value="{{ old('unit', $item?->unit ?? 'buc') }}" class="form-control" required></div>
                <div class="col-md-2 d-flex align-items-end"><div class="form-check mb-2"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" class="form-check-input" id="item-active" @checked(old('active', $item?->active ?? true))><label class="form-check-label" for="item-active">Articol activ</label></div></div>
                <div class="col-12"><label class="form-label">Descriere</label><textarea name="description" class="form-control" rows="4">{{ old('description', $item?->description) }}</textarea></div>
            </div>
        </div>
        <div class="resource-form-actions-bar">
            <a href="{{ route('catalog-items.index') }}" class="btn btn-outline-secondary">Renunta</a>
            <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salveaza modificarile' : 'Creeaza articolul' }}</button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
