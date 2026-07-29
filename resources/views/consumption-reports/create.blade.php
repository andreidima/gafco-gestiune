@extends('layouts.app')

@section('title', 'Raportează consum')

@section('content')
<x-resource-form-shell
    title="Raportează consum"
    description="Cantitatea se scade din stocul locației imediat după salvare."
    :back-route="route('consumption-reports.index')"
    icon="fa-clipboard-check"
>
    <form
        method="post"
        action="{{ route('consumption-reports.store') }}"
        class="resource-form-card"
        data-consumption-allocation
        data-allocation-url="{{ $allocationProposalUrl }}"
        data-old-allocations='@json(old("allocations", []))'
    >
        @csrf
        <div class="resource-form-section">
            <div class="resource-form-section-title">Consum raportat</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Locație</label><select name="location_id" class="form-select" data-tom-select required autofocus><option value="">Alege locația</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Material</label><select name="catalog_item_id" class="form-select" data-tom-select required><option value="">Alege materialul</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((string) old('catalog_item_id') === (string) $item->id)>{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Cantitate</label><input name="quantity" type="number" step="0.001" min="0.001" value="{{ old('quantity', 1) }}" class="form-control" required></div>
                <div class="col-md-9"><label class="form-label">Observații</label><textarea name="notes" class="form-control" rows="4" placeholder="Lucrare, echipă, zonă sau explicații">{{ old('notes') }}</textarea></div>
            </div>
        </div>
        <section class="resource-form-section">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <div class="resource-form-section-title mb-1">Loturile din care se scade</div>
                    <div class="resource-secondary">Aplicația propune FEFO (expiră primul) și apoi FIFO (intrat primul). Poți modifica repartiția.</div>
                </div>
                <span class="badge text-bg-light border" data-allocation-total>Alege locația, materialul și cantitatea</span>
            </div>
            <div class="consumption-allocation-state mt-3" data-allocation-state>
                Propunerea apare automat după completarea câmpurilor de mai sus.
            </div>
            <div class="table-responsive d-none mt-3" data-allocation-table-wrap>
                <table class="table table-sm align-middle consumption-allocation-table mb-0">
                    <thead>
                        <tr>
                            <th>Lot / sursă</th>
                            <th>Furnizor</th>
                            <th>Intrare</th>
                            <th>Expirare</th>
                            <th class="text-end">Disponibil</th>
                            <th class="text-end">De scăzut</th>
                        </tr>
                    </thead>
                    <tbody data-allocation-rows></tbody>
                </table>
            </div>
        </section>
        <div class="resource-form-actions-bar">
            <a href="{{ route('consumption-reports.index') }}" class="btn btn-outline-secondary">Renunță</a>
            <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Înregistrează consumul</button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
