@extends('layouts.app')

@section('title', 'Retururi')

@section('content')
<div class="mx-3 px-3 card crud-card">
    <div class="row card-header align-items-center">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-rotate-left"></i> Retururi catre baza
            </span>
            <div class="small text-muted mt-2">Echipamente, scule sau materiale intoarse din santier</div>
        </div>
        <div class="col-lg-9">
            <form method="post" action="{{ route('returns.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Santier</label>
                    <select name="source_location_id" class="form-select form-select-sm rounded-3" required>
                        <option value="">Alege</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->code }} - {{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Baza</label>
                    <select name="destination_location_id" class="form-select form-select-sm rounded-3" required>
                        <option value="">Alege</option>
                        @foreach($bases as $base)
                            <option value="{{ $base->id }}">{{ $base->code }} - {{ $base->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Echipament QR</label>
                    <select name="tracked_asset_id" class="form-select form-select-sm rounded-3">
                        <option value="">Fara asset unic</option>
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Material</label>
                    <select name="catalog_item_id" class="form-select form-select-sm rounded-3">
                        <option value="">Fara material</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1">
                    <label class="form-label small mb-1">Cant.</label>
                    <input name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control form-control-sm rounded-3">
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Sofer</label>
                    <select name="driver_id" class="form-select form-select-sm rounded-3">
                        <option value="">Nealocat</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1">
                    <button class="btn btn-sm btn-success text-white border border-dark rounded-3 w-100">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
                <div class="col-lg-3">
                    <input name="document_number" class="form-control form-control-sm rounded-3" placeholder="Aviz retur">
                </div>
                <div class="col-lg-9">
                    <input name="notes" class="form-control form-control-sm rounded-3" placeholder="Observatii retur / stare la plecare">
                </div>
            </form>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        <div class="table-responsive rounded">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Retur</th>
                        <th class="culoare2 text-white">Flux</th>
                        <th class="culoare2 text-white">Continut</th>
                        <th class="culoare2 text-white">Aviz</th>
                        <th class="culoare2 text-white">Sofer</th>
                        <th class="culoare2 text-white">Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($returns as $return)
                    <tr>
                        <td>
                            <strong>{{ $return->number }}</strong>
                            <div class="small text-muted">{{ optional($return->requested_at)->format('d.m.Y H:i') }}</div>
                        </td>
                        <td>{{ $return->sourceLocation?->name }} <i class="fa-solid fa-arrow-right mx-1"></i> {{ $return->destinationLocation?->name }}</td>
                        <td>
                            @foreach($return->lines as $line)
                                <div>{{ $line->trackedAsset?->asset_code ?? $line->catalogItem?->name }} <span class="text-muted">({{ number_format((float) $line->quantity, 2) }} {{ $line->unit }})</span></div>
                            @endforeach
                        </td>
                        <td>{{ $return->document_number ?: '-' }}</td>
                        <td>{{ $return->driver?->name ?? 'Nealocat' }}</td>
                        <td><x-status :status="$return->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Nu exista retururi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3">{{ $returns->links() }}</div>
    </div>
</div>
@endsection
