@extends('layouts.app')

@section('title', 'Transferuri')

@section('content')
<div class="mx-3 px-3 card crud-card">
    <div class="row card-header align-items-center">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-right-left"></i> Transferuri
            </span>
            <div class="small text-muted mt-2">Cerere -> aprobare -> sofer -> confirmare primire</div>
        </div>

        <div class="col-lg-9">
            <form method="post" action="{{ route('transfers.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Tip</label>
                    <select name="type" class="form-select form-select-sm rounded-3">
                        <option value="base_to_site">Baza -> santier</option>
                        <option value="site_to_site">Santier -> santier</option>
                        <option value="site_to_base">Santier -> baza</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Din</label>
                    <select name="source_location_id" class="form-select form-select-sm rounded-3" required>
                        <option value="">Alege</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Catre</label>
                    <select name="destination_location_id" class="form-select form-select-sm rounded-3" required>
                        <option value="">Alege</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Echipament QR</label>
                    <select name="tracked_asset_id" class="form-select form-select-sm rounded-3">
                        <option value="">Fara asset unic</option>
                        @foreach($assets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }} / {{ $asset->currentLocation?->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Material cantitativ</label>
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
                <div class="col-lg-1">
                    <button class="btn btn-sm btn-success text-white border border-dark rounded-3 w-100">
                        <i class="fa-solid fa-plus me-1"></i>Creeaza
                    </button>
                </div>
                <div class="col-lg-3">
                    <select name="driver_id" class="form-select form-select-sm rounded-3">
                        <option value="">Sofer nealocat</option>
                        @foreach($drivers as $driver)
                            <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <input name="document_number" class="form-control form-control-sm rounded-3" placeholder="Aviz / document transfer">
                </div>
                <div class="col-lg-6">
                    <input name="notes" class="form-control form-control-sm rounded-3" placeholder="Observatii">
                </div>
            </form>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        <div class="workflow-strip mb-3">
            <span><i class="fa-solid fa-file-circle-plus"></i> Cerere</span>
            <i class="fa-solid fa-arrow-right"></i>
            <span><i class="fa-solid fa-user-check"></i> Aprobare sef santier</span>
            <i class="fa-solid fa-arrow-right"></i>
            <span><i class="fa-solid fa-truck"></i> Sofer</span>
            <i class="fa-solid fa-arrow-right"></i>
            <span><i class="fa-solid fa-qrcode"></i> Confirmare QR</span>
        </div>

        <div class="table-responsive rounded">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Transfer</th>
                        <th class="culoare2 text-white">Flux</th>
                        <th class="culoare2 text-white">Continut</th>
                        <th class="culoare2 text-white">Aviz</th>
                        <th class="culoare2 text-white">Aprobare</th>
                        <th class="culoare2 text-white">Sofer</th>
                        <th class="culoare2 text-white">Status</th>
                        <th class="culoare2 text-white text-end">Actiune</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($transfers as $transfer)
                    <tr>
                        <td>
                            <strong>{{ $transfer->number }}</strong>
                            <div class="small text-muted">{{ optional($transfer->requested_at)->format('d.m.Y H:i') }}</div>
                        </td>
                        <td>{{ $transfer->sourceLocation?->name }} <i class="fa-solid fa-arrow-right mx-1"></i> {{ $transfer->destinationLocation?->name }}</td>
                        <td>
                            @foreach($transfer->lines as $line)
                                <div>
                                    {{ $line->trackedAsset?->asset_code ?? $line->catalogItem?->name }}
                                    <span class="text-muted">({{ number_format((float) $line->quantity, 2) }} {{ $line->unit }})</span>
                                </div>
                            @endforeach
                        </td>
                        <td>{{ $transfer->document_number ?: '-' }}</td>
                        <td>
                            {{ $transfer->approver?->name ?? 'Neaprobat' }}
                            @if($transfer->approved_at)
                                <div class="small text-muted">{{ $transfer->approved_at->format('d.m.Y H:i') }}</div>
                            @endif
                        </td>
                        <td>{{ $transfer->driver?->name ?? 'Nealocat' }}</td>
                        <td><x-status :status="$transfer->status" /></td>
                        <td>
                            <form method="post" action="{{ route('transfers.update', $transfer) }}" class="row g-1 justify-content-end">
                                @csrf
                                @method('put')
                                <div class="col-auto">
                                    <select name="driver_id" class="form-select form-select-sm rounded-3">
                                        <option value="">Fara sofer</option>
                                        @foreach($drivers as $driver)
                                            <option value="{{ $driver->id }}" @selected($transfer->driver_id === $driver->id)>{{ $driver->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <select name="status" class="form-select form-select-sm rounded-3">
                                        @foreach(['pending_approval', 'approved', 'assigned', 'in_transit', 'received', 'cancelled'] as $status)
                                            <option value="{{ $status }}" @selected($transfer->status === $status)>{{ str_replace('_', ' ', $status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <input name="document_number" value="{{ $transfer->document_number }}" class="form-control form-control-sm rounded-3" placeholder="Aviz" style="width: 120px">
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-sm btn-outline-primary rounded-3">Salveaza</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-4">Nu exista transferuri.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3">{{ $transfers->links() }}</div>
    </div>
</div>
@endsection
