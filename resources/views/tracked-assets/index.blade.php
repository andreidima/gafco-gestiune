@extends('layouts.app')

@section('title', 'Echipamente')

@section('content')
<div class="mx-3 px-3 card crud-card">
    <div class="row card-header align-items-center">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-screwdriver-wrench"></i> Echipamente
            </span>
        </div>

        <div class="col-lg-6">
            <form method="GET" action="{{ route('tracked-assets.index') }}">
                <div class="row g-2 justify-content-center">
                    <div class="col-lg-4">
                        <input type="text" class="form-control rounded-3" name="search" placeholder="Cod, QR, serie, denumire" value="{{ request('search') }}">
                    </div>
                    <div class="col-lg-4">
                        <select name="location_id" class="form-select rounded-3">
                            <option value="">Toate locatiile</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <select name="status" class="form-select rounded-3">
                            <option value="">Toate statusurile</option>
                            @foreach(['available' => 'Disponibil', 'in_use' => 'In folosinta', 'in_transfer' => 'In transfer', 'maintenance' => 'Service', 'lost' => 'Lipsa'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <button class="btn btn-sm w-100 btn-primary text-white border border-dark rounded-3">
                            <i class="fas fa-search text-white me-1"></i>Cauta
                        </button>
                    </div>
                    <div class="col-lg-4">
                        <a class="btn btn-sm w-100 btn-secondary text-white border border-dark rounded-3" href="{{ route('tracked-assets.index') }}">
                            <i class="far fa-trash-alt text-white me-1"></i>Reseteaza
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-3">
            <form method="post" action="{{ route('tracked-assets.store') }}" class="row g-2">
                @csrf
                <div class="col-12">
                    <select name="catalog_item_id" class="form-select form-select-sm rounded-3" required>
                        <option value="">Tip echipament</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6"><input name="asset_code" class="form-control form-control-sm rounded-3" placeholder="Cod intern" required></div>
                <div class="col-6"><input name="serial_number" class="form-control form-control-sm rounded-3" placeholder="Serie"></div>
                <div class="col-6">
                    <select name="status" class="form-select form-select-sm rounded-3">
                        <option value="available">Disponibil</option>
                        <option value="in_use">In folosinta</option>
                        <option value="maintenance">Service</option>
                    </select>
                </div>
                <div class="col-6">
                    <select name="condition" class="form-select form-select-sm rounded-3">
                        <option value="good">Bun</option>
                        <option value="used">Uzura</option>
                        <option value="damaged">Deteriorat</option>
                        <option value="needs_service">Necesita service</option>
                    </select>
                </div>
                <div class="col-12">
                    <select name="current_location_id" class="form-select form-select-sm rounded-3">
                        <option value="">Locatie</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <button class="btn btn-sm btn-success text-white border border-dark rounded-3 w-100">
                        <i class="fa-solid fa-plus me-1"></i>Adauga
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        <div class="table-responsive rounded">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Cod</th>
                        <th class="culoare2 text-white">Echipament</th>
                        <th class="culoare2 text-white">Locatie</th>
                        <th class="culoare2 text-white">Responsabil</th>
                        <th class="culoare2 text-white">Stare</th>
                        <th class="culoare2 text-white">QR</th>
                        <th class="culoare2 text-white text-end">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($assets as $asset)
                    <tr>
                        <td>
                            <strong>{{ $asset->asset_code }}</strong>
                            <div class="small text-muted">{{ $asset->serial_number ?: 'Fara serie' }}</div>
                        </td>
                        <td>{{ $asset->catalogItem?->name }}</td>
                        <td>{{ $asset->currentLocation?->name ?? 'Fara locatie' }}</td>
                        <td>{{ $asset->currentCustodian?->name ?? 'Fara responsabil' }}</td>
                        <td>
                            <span class="badge text-bg-light border">{{ str_replace('_', ' ', $asset->condition) }}</span>
                            <span class="badge text-bg-{{ $asset->status === 'lost' ? 'danger' : ($asset->status === 'maintenance' ? 'warning' : 'success') }}">{{ str_replace('_', ' ', $asset->status) }}</span>
                        </td>
                        <td>
                            <a class="qr-mini text-decoration-none" href="{{ route('tracked-assets.show', $asset) }}">
                                <i class="fa-solid fa-qrcode"></i>
                                <span>{{ $asset->qr_code }}</span>
                            </a>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-3" href="{{ route('tracked-assets.show', $asset) }}">
                                <i class="fa-solid fa-eye me-1"></i>Detalii
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Nu exista echipamente.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3">{{ $assets->links() }}</div>
    </div>
</div>
@endsection
