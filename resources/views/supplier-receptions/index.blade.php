@extends('layouts.app')

@section('title', 'Receptii')
@section('page_title', 'Receptii furnizori')
@section('page_subtitle', 'Marfa intrata pe baza sau santier')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ route('supplier-receptions.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Locatie</label>
                    <select name="location_id" class="form-select" data-tom-select required>
                        <option value="">Alege</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Furnizor</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">Nespecificat</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Document</label>
                    <select name="document_type" class="form-select">
                        <option value="aviz">Aviz</option>
                        <option value="factura">Factura</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Numar doc.</label>
                    <input name="document_number" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Articol</label>
                    <select name="catalog_item_id" class="form-select" data-tom-select required>
                        <option value="">Alege</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Cant.</label>
                    <input name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control" required>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-success w-100">Salveaza</button>
                </div>
                <div class="col-12">
                    <input name="notes" class="form-control" placeholder="Observatii">
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Numar</th>
                        <th>Locatie</th>
                        <th>Furnizor</th>
                        <th>Document</th>
                        <th>Linii</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($receptions as $reception)
                    <tr>
                        <td>
                            <strong>{{ $reception->number }}</strong>
                            <div class="small text-secondary">{{ optional($reception->received_at)->format('d.m.Y H:i') }}</div>
                        </td>
                        <td>{{ $reception->location?->name }}</td>
                        <td>{{ $reception->supplier?->name ?? 'Nespecificat' }}</td>
                        <td>{{ strtoupper($reception->document_type) }} {{ $reception->document_number }}</td>
                        <td>{{ $reception->lines_count }}</td>
                        <td><x-status :status="$reception->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Nu exista receptii.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $receptions->links() }}</div>
    </div>
@endsection
