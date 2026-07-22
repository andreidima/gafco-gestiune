@extends('layouts.app')

@section('title', 'Receptie noua')

@section('content')
<x-resource-form-shell
    title="Receptie noua"
    description="Inregistreaza documentul si materialul primit. Stocul locatiei se actualizeaza la salvare."
    :back-route="route('supplier-receptions.index')"
    icon="fa-truck-ramp-box"
>
    <form method="post" action="{{ route('supplier-receptions.store') }}" class="resource-form-card">
        @csrf
        <div class="resource-form-section">
            <div class="resource-form-section-title">Destinatie si document</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Locatie</label><select name="location_id" class="form-select" data-tom-select required autofocus><option value="">Alege locatia</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Furnizor</label><select name="supplier_id" class="form-select" data-tom-select><option value="">Nespecificat</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Tip document</label><select name="document_type" class="form-select" required><option value="aviz" @selected(old('document_type', 'aviz') === 'aviz')>Aviz</option><option value="factura" @selected(old('document_type') === 'factura')>Factura</option></select></div>
                <div class="col-md-9"><label class="form-label">Numar document</label><input name="document_number" value="{{ old('document_number') }}" class="form-control" placeholder="Optional"></div>
            </div>
        </div>
        <div class="resource-form-section">
            <div class="resource-form-section-title">Material primit</div>
            <div class="row g-3">
                <div class="col-md-9"><label class="form-label">Articol</label><select name="catalog_item_id" class="form-select" data-tom-select required><option value="">Alege articolul</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((string) old('catalog_item_id') === (string) $item->id)>{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Cantitate</label><input name="quantity" type="number" step="0.001" min="0.001" value="{{ old('quantity', 1) }}" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Observatii</label><textarea name="notes" class="form-control" rows="4" placeholder="Stare livrare, diferente sau alte mentiuni">{{ old('notes') }}</textarea></div>
            </div>
        </div>
        <div class="resource-form-actions-bar">
            <a href="{{ route('supplier-receptions.index') }}" class="btn btn-outline-secondary">Renunta</a>
            <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Salveaza receptia</button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
