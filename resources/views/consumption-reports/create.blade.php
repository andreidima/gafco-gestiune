@extends('layouts.app')

@section('title', 'Raporteaza consum')

@section('content')
<x-resource-form-shell
    title="Raporteaza consum"
    description="Cantitatea se scade din stocul locatiei imediat dupa salvare."
    :back-route="route('consumption-reports.index')"
    icon="fa-clipboard-check"
>
    <form method="post" action="{{ route('consumption-reports.store') }}" class="resource-form-card">
        @csrf
        <div class="resource-form-section">
            <div class="resource-form-section-title">Consum raportat</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Locatie</label><select name="location_id" class="form-select" data-tom-select required autofocus><option value="">Alege locatia</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Material</label><select name="catalog_item_id" class="form-select" data-tom-select required><option value="">Alege materialul</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((string) old('catalog_item_id') === (string) $item->id)>{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Cantitate</label><input name="quantity" type="number" step="0.001" min="0.001" value="{{ old('quantity', 1) }}" class="form-control" required></div>
                <div class="col-md-9"><label class="form-label">Observatii</label><textarea name="notes" class="form-control" rows="4" placeholder="Lucrare, echipa, zona sau explicatii">{{ old('notes') }}</textarea></div>
            </div>
        </div>
        <div class="resource-form-actions-bar">
            <a href="{{ route('consumption-reports.index') }}" class="btn btn-outline-secondary">Renunta</a>
            <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Inregistreaza consumul</button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
