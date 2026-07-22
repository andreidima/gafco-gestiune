@extends('layouts.app')

@php($editing = (bool) $asset)
@section('title', $editing ? 'Modifica echipamentul' : 'Echipament nou')

@section('content')
<x-resource-form-shell
    :title="$editing ? 'Modifica echipamentul' : 'Echipament nou'"
    description="Identificare QR, stare, localizare si responsabil curent."
    :back-route="$editing ? route('tracked-assets.show', $asset) : route('tracked-assets.index')"
    icon="fa-screwdriver-wrench"
>
    <form method="post" action="{{ $editing ? route('tracked-assets.update', $asset) : route('tracked-assets.store') }}" class="resource-form-card">
        @csrf
        @if($editing) @method('put') @endif
        <div class="resource-form-section">
            <div class="resource-form-section-title">Identificare</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Articol serializat</label><select name="catalog_item_id" class="form-select" data-tom-select required><option value="">Alege tipul echipamentului</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((string)old('catalog_item_id', $asset?->catalog_item_id) === (string)$item->id)>{{ $item->name }}{{ $item->sku ? ' - '.$item->sku : '' }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Cod intern</label><input name="asset_code" value="{{ old('asset_code', $asset?->asset_code) }}" class="form-control text-uppercase" required></div>
                <div class="col-md-3"><label class="form-label">Numar de serie</label><input name="serial_number" value="{{ old('serial_number', $asset?->serial_number) }}" class="form-control"></div>
            </div>
        </div>
        <div class="resource-form-section">
            <div class="resource-form-section-title">Stare si localizare</div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(['available'=>'Disponibil','in_use'=>'In folosinta','in_transfer'=>'In transfer','maintenance'=>'Service','lost'=>'Lipsa'] as $value=>$label)<option value="{{ $value }}" @selected(old('status', $asset?->status ?? 'available') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Conditie</label><select name="condition" class="form-select">@foreach(['good'=>'Bun','used'=>'Uzura','damaged'=>'Deteriorat','needs_service'=>'Necesita service'] as $value=>$label)<option value="{{ $value }}" @selected(old('condition', $asset?->condition ?? 'good') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Locatie curenta</label><select name="current_location_id" class="form-select" data-tom-select><option value="">Fara locatie</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)old('current_location_id', $asset?->current_location_id) === (string)$location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Responsabil curent</label><select name="current_custodian_id" class="form-select" data-tom-select><option value="">Fara responsabil</option>@foreach($custodians as $custodian)<option value="{{ $custodian->id }}" @selected((string)old('current_custodian_id', $asset?->current_custodian_id) === (string)$custodian->id)>{{ $custodian->name }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label">Observatii</label><textarea name="notes" class="form-control" rows="4">{{ old('notes', $asset?->notes) }}</textarea></div>
            </div>
        </div>
        <div class="resource-form-actions-bar"><a href="{{ $editing ? route('tracked-assets.show', $asset) : route('tracked-assets.index') }}" class="btn btn-outline-secondary">Renunta</a><button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salveaza modificarile' : 'Creeaza echipamentul' }}</button></div>
    </form>
</x-resource-form-shell>
@endsection
