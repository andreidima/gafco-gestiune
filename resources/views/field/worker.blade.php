@extends('layouts.app')

@section('title', 'Mod muncitor')

@section('content')
<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <span class="dashboard-pill"><i class="fa-solid fa-helmet-safety me-2"></i> Mod muncitor</span>
        <h3 class="mb-2">Scule in custodie si predare intre muncitori</h3>
        <p class="mb-0 text-muted">Demo pentru predarea unei scule scanate QR catre alt muncitor.</p>
    </div>

    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong>Predare scula</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ route('custody-transfers.store') }}" class="vstack gap-2">
                        @csrf
                        <select name="tracked_asset_id" class="form-select rounded-3" required>
                            <option value="">Alege scula / echipament</option>
                            @foreach($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }}</option>
                            @endforeach
                        </select>
                        <select name="to_user_id" class="form-select rounded-3" required>
                            <option value="">Catre muncitor</option>
                            @foreach($workers as $worker)
                                <option value="{{ $worker->id }}">{{ $worker->name }}</option>
                            @endforeach
                        </select>
                        <input name="notes" class="form-control rounded-3" placeholder="Observatii stare / locatie">
                        <button class="btn btn-primary rounded-3"><i class="fa-solid fa-qrcode me-1"></i> Genereaza predare</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="row g-3">
                @foreach($assets->take(8) as $asset)
                    <div class="col-md-6">
                        <div class="field-card h-100">
                            <div class="d-flex justify-content-between mb-2">
                                <strong>{{ $asset->asset_code }}</strong>
                                <span class="qr-mini"><i class="fa-solid fa-qrcode"></i> {{ $asset->qr_code }}</span>
                            </div>
                            <div>{{ $asset->catalogItem?->name }}</div>
                            <div class="small text-muted">{{ $asset->currentLocation?->name ?? '-' }}</div>
                            <div class="mt-2">Custodie: <strong>{{ $asset->currentCustodian?->name ?? 'Nealocat' }}</strong></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card dashboard-chart-card mt-4">
        <div class="card-header bg-white"><strong>Predari intre muncitori</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Token QR</th>
                        <th class="culoare2 text-white">Echipament</th>
                        <th class="culoare2 text-white">De la</th>
                        <th class="culoare2 text-white">Catre</th>
                        <th class="culoare2 text-white">Status</th>
                        <th class="culoare2 text-white text-end">Actiune</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($custodyTransfers as $transfer)
                    <tr>
                        <td><span class="qr-mini"><i class="fa-solid fa-qrcode"></i> {{ $transfer->qr_token }}</span></td>
                        <td>{{ $transfer->trackedAsset?->asset_code }} / {{ $transfer->trackedAsset?->catalogItem?->name }}</td>
                        <td>{{ $transfer->fromUser?->name ?? '-' }}</td>
                        <td>{{ $transfer->toUser?->name }}</td>
                        <td><x-status :status="$transfer->status" /></td>
                        <td class="text-end">
                            @if($transfer->status === 'pending')
                                <form method="post" action="{{ route('custody-transfers.update', $transfer) }}">
                                    @csrf
                                    @method('put')
                                    <input type="hidden" name="status" value="accepted">
                                    <button class="btn btn-sm btn-outline-success rounded-3">Accepta</button>
                                </form>
                            @else
                                <span class="text-muted">{{ optional($transfer->accepted_at)->format('d.m.Y H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
