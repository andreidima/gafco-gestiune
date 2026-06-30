@extends('layouts.app')

@section('title', 'Nomenclator')
@section('page_title', 'Nomenclator')
@section('page_subtitle', 'Materiale, utilaje, echipamente si scule')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ route('catalog-items.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2"><label class="form-label">Categorie</label><select name="category" class="form-select"><option value="material">Material</option><option value="equipment">Utilaj/Echipament</option><option value="tool">Scula</option></select></div>
                <div class="col-md-2"><label class="form-label">Urmarire</label><select name="tracking_type" class="form-select"><option value="quantity">Cantitativ</option><option value="serialized">Unic / QR</option></select></div>
                <div class="col-md-2"><label class="form-label">SKU</label><input name="sku" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Denumire</label><input name="name" class="form-control" required></div>
                <div class="col-md-1"><label class="form-label">UM</label><input name="unit" value="buc" class="form-control" required></div>
                <div class="col-md-1"><button class="btn btn-success w-100">Adauga</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Articol</th><th>Categorie</th><th>Urmarire</th><th>UM</th><th>Asset-uri</th></tr></thead>
                <tbody>
                @forelse($items as $item)
                    <tr><td><strong>{{ $item->name }}</strong><div class="small text-secondary">{{ $item->sku ?? 'Fara SKU' }}</div></td><td>{{ $item->category }}</td><td>{{ $item->tracking_type }}</td><td>{{ $item->unit }}</td><td>{{ $item->tracked_assets_count }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">Nu exista articole.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $items->links() }}</div>
    </div>
@endsection
