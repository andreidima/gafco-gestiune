@extends('layouts.app')

@section('title', 'Mod sef santier')

@section('content')
<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <span class="dashboard-pill"><i class="fa-solid fa-user-check me-2"></i> Mod sef santier</span>
        <h3 class="mb-2">Aprobari, confirmari, consum si retururi</h3>
        <p class="mb-0 text-muted">Fluxurile principale pe care le-ar folosi seful de santier din telefon.</p>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong>Inregistreaza consum</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ route('consumption-reports.store') }}" class="vstack gap-2">
                        @csrf
                        <select name="location_id" class="form-select rounded-3" required>
                            <option value="">Santier</option>
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->code }} - {{ $site->name }}</option>
                            @endforeach
                        </select>
                        <select name="catalog_item_id" class="form-select rounded-3" required>
                            <option value="">Material</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                            @endforeach
                        </select>
                        <input name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control rounded-3" required>
                        <input name="notes" class="form-control rounded-3" placeholder="Observatii">
                        <button class="btn btn-success rounded-3"><i class="fa-solid fa-check me-1"></i> Salveaza consum</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong>Creeaza retur</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ route('returns.store') }}" class="vstack gap-2">
                        @csrf
                        <select name="source_location_id" class="form-select rounded-3" required>
                            <option value="">Santier</option>
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->code }} - {{ $site->name }}</option>
                            @endforeach
                        </select>
                        <select name="destination_location_id" class="form-select rounded-3" required>
                            <option value="">Baza</option>
                            @foreach($bases as $base)
                                <option value="{{ $base->id }}">{{ $base->code }} - {{ $base->name }}</option>
                            @endforeach
                        </select>
                        <select name="tracked_asset_id" class="form-select rounded-3">
                            <option value="">Echipament QR optional</option>
                            @foreach($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }}</option>
                            @endforeach
                        </select>
                        <select name="catalog_item_id" class="form-select rounded-3">
                            <option value="">Material optional</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                            @endforeach
                        </select>
                        <input name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control rounded-3">
                        <input name="document_number" class="form-control rounded-3" placeholder="Aviz retur">
                        <button class="btn btn-primary rounded-3"><i class="fa-solid fa-rotate-left me-1"></i> Trimite retur</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong>Ultimele consumuri</strong></div>
                <div class="card-body vstack gap-2">
                    @forelse($recentConsumption as $report)
                        <div class="field-line">
                            <span>{{ $report->location?->code }} / {{ $report->number }}</span>
                            <span>{{ optional($report->reported_at)->format('d.m H:i') }}</span>
                        </div>
                    @empty
                        <div class="text-muted">Nu exista consumuri.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card dashboard-chart-card mt-4">
        <div class="card-header bg-white"><strong>Aprobari si confirmari</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Transfer</th>
                        <th class="culoare2 text-white">Flux</th>
                        <th class="culoare2 text-white">Continut</th>
                        <th class="culoare2 text-white">Status</th>
                        <th class="culoare2 text-white text-end">Actiune</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pendingTransfers as $transfer)
                    <tr>
                        <td>{{ $transfer->number }}</td>
                        <td>{{ $transfer->sourceLocation?->name }} <i class="fa-solid fa-arrow-right mx-1"></i> {{ $transfer->destinationLocation?->name }}</td>
                        <td>
                            @foreach($transfer->lines as $line)
                                <div>{{ $line->trackedAsset?->asset_code ?? $line->catalogItem?->name }}</div>
                            @endforeach
                        </td>
                        <td><x-status :status="$transfer->status" /></td>
                        <td>
                            <form method="post" action="{{ route('transfers.update', $transfer) }}" class="d-flex justify-content-end gap-2">
                                @csrf
                                @method('put')
                                <input type="hidden" name="document_number" value="{{ $transfer->document_number }}">
                                <input type="hidden" name="status" value="{{ $transfer->status === 'pending_approval' ? 'approved' : 'received' }}">
                                @if($transfer->status === 'in_transit')
                                    <input name="discrepancy_notes" class="form-control form-control-sm rounded-3" placeholder="Diferente / observatii" style="max-width: 220px">
                                @endif
                                <button class="btn btn-sm btn-outline-primary rounded-3">
                                    {{ $transfer->status === 'pending_approval' ? 'Aproba' : 'Confirma primire' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
