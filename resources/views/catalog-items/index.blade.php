@extends('layouts.app')

@section('title', 'Nomenclator')

@section('content')
@php
    $categoryLabels = [
        'material' => ['label' => 'Material', 'class' => 'success'],
        'equipment' => ['label' => 'Utilaj', 'class' => 'primary'],
        'tool' => ['label' => 'Scula', 'class' => 'warning'],
    ];
    $trackingLabels = [
        'quantity' => ['label' => 'Cantitativ', 'class' => 'info'],
        'serialized' => ['label' => 'QR unic', 'class' => 'dark'],
    ];
@endphp

<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
            <div>
                <span class="dashboard-pill"><i class="fa-solid fa-list me-2"></i> Nomenclator</span>
                <h3 class="mb-2">Materiale, scule si utilaje</h3>
                <p class="mb-0 text-muted">Catalogul de articole care intra in stoc, transferuri si rapoarte de consum.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('tracked-assets.index') }}" class="btn btn-outline-primary rounded-3">
                    <i class="fa-solid fa-qrcode me-1"></i> Asset-uri QR
                </a>
                <a href="{{ route('supplier-receptions.index') }}" class="btn btn-outline-secondary rounded-3">
                    <i class="fa-solid fa-receipt me-1"></i> Receptii
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-slate"><span>Total</span><strong>{{ $stats['total'] }}</strong></div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-forest"><span>Materiale</span><strong>{{ $stats['materials'] }}</strong></div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-teal"><span>Utilaje</span><strong>{{ $stats['equipment'] }}</strong></div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-amber"><span>Scule</span><strong>{{ $stats['tools'] }}</strong></div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-rose"><span>QR unic</span><strong>{{ $stats['serialized'] }}</strong></div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-danger"><span>Pozitii stoc</span><strong>{{ $stats['stock_positions'] }}</strong></div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong><i class="fa-solid fa-plus me-1"></i> Adauga articol</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ route('catalog-items.store') }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-2">
                            <label class="form-label">Categorie</label>
                            <select name="category" class="form-select rounded-3">
                                <option value="material">Material</option>
                                <option value="equipment">Utilaj/Echipament</option>
                                <option value="tool">Scula</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Urmarire</label>
                            <select name="tracking_type" class="form-select rounded-3">
                                <option value="quantity">Cantitativ</option>
                                <option value="serialized">Unic / QR</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">SKU</label>
                            <input name="sku" class="form-control rounded-3">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Denumire</label>
                            <input name="name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">UM</label>
                            <input name="unit" value="buc" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-success rounded-3 w-100"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="insight-panel h-100">
                <div class="fw-bold mb-3"><i class="fa-solid fa-ranking-star me-1"></i> Top articole QR</div>
                @forelse($topItems as $topItem)
                    <div class="field-line">
                        <span>{{ $topItem->name }}</span>
                        <strong>{{ $topItem->tracked_assets_count }}</strong>
                    </div>
                @empty
                    <div class="text-muted">Nu exista articole serializate.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card dashboard-chart-card">
        <div class="card-header bg-white">
            <form class="row g-2 align-items-center">
                <div class="col-lg-5">
                    <input name="search" value="{{ request('search') }}" class="form-control rounded-3" placeholder="Cauta dupa denumire, SKU sau cod de bare">
                </div>
                <div class="col-lg-2">
                    <select name="category" class="form-select rounded-3">
                        <option value="">Toate categoriile</option>
                        <option value="material" @selected(request('category')==='material')>Materiale</option>
                        <option value="equipment" @selected(request('category')==='equipment')>Utilaje</option>
                        <option value="tool" @selected(request('category')==='tool')>Scule</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <select name="tracking_type" class="form-select rounded-3">
                        <option value="">Toate urmaririle</option>
                        <option value="quantity" @selected(request('tracking_type')==='quantity')>Cantitativ</option>
                        <option value="serialized" @selected(request('tracking_type')==='serialized')>QR unic</option>
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button class="btn btn-outline-primary rounded-3 flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Filtreaza
                    </button>
                    <a href="{{ route('catalog-items.index') }}" class="btn btn-outline-secondary rounded-3">
                        <i class="fa-solid fa-rotate-right"></i>
                    </a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Articol</th>
                        <th class="culoare2 text-white">Categorie</th>
                        <th class="culoare2 text-white">Urmarire</th>
                        <th class="culoare2 text-white">UM</th>
                        <th class="culoare2 text-white">Asset-uri</th>
                        <th class="culoare2 text-white text-end">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($items as $item)
                    @php
                        $category = $categoryLabels[$item->category] ?? ['label' => $item->category, 'class' => 'secondary'];
                        $tracking = $trackingLabels[$item->tracking_type] ?? ['label' => $item->tracking_type, 'class' => 'secondary'];
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $item->name }}</strong>
                            <div class="small text-secondary">{{ $item->sku ?? 'Fara SKU' }}</div>
                        </td>
                        <td><span class="badge rounded-pill text-bg-{{ $category['class'] }}">{{ $category['label'] }}</span></td>
                        <td><span class="badge rounded-pill text-bg-{{ $tracking['class'] }}">{{ $tracking['label'] }}</span></td>
                        <td><span class="badge rounded-pill text-bg-light border">{{ $item->unit }}</span></td>
                        <td>
                            @if($item->tracking_type === 'serialized')
                                <span class="badge rounded-pill text-bg-primary">{{ $item->tracked_assets_count }} QR</span>
                            @else
                                <span class="badge rounded-pill text-bg-info">Stoc cantitativ</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-outline-primary" href="{{ route('tracked-assets.index') }}">
                                    <i class="fa-solid fa-qrcode"></i>
                                </a>
                                <a class="btn btn-outline-secondary" href="{{ route('supplier-receptions.index') }}">
                                    <i class="fa-solid fa-receipt"></i>
                                </a>
                                <a class="btn btn-outline-success" href="{{ route('consumption-reports.index') }}">
                                    <i class="fa-solid fa-clipboard-check"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Nu exista articole.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $items->links() }}</div>
    </div>
</div>
@endsection
