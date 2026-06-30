@extends('layouts.app')

@section('title', 'Rapoarte')

@section('content')
<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <span class="dashboard-pill"><i class="fa-solid fa-chart-column me-2"></i> Rapoarte</span>
        <h3 class="mb-2">Ce este pe fiecare santier si ce necesita atentie</h3>
        <p class="mb-0 text-muted">Rapoarte simple pentru demo: inventar pe locatie, lipsuri/neconfirmate si istoric transferuri.</p>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong>Inventar pe locatie</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="culoare2 text-white">Locatie</th>
                                <th class="culoare2 text-white">Tip</th>
                                <th class="culoare2 text-white text-end">Echipamente</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($assetsByLocation as $location)
                            <tr>
                                <td>{{ $location->name }}</td>
                                <td>{{ $location->type }}</td>
                                <td class="text-end fw-semibold">{{ $location->tracked_assets_count }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong>Lipsuri / neconfirmate</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="culoare2 text-white">Cod</th>
                                <th class="culoare2 text-white">Echipament</th>
                                <th class="culoare2 text-white">Locatie</th>
                                <th class="culoare2 text-white">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($missingAssets as $asset)
                            <tr>
                                <td>{{ $asset->asset_code }}</td>
                                <td>{{ $asset->catalogItem?->name }}</td>
                                <td>{{ $asset->currentLocation?->name ?? '-' }}</td>
                                <td><span class="badge text-bg-warning">{{ str_replace('_', ' ', $asset->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Nu exista echipamente lipsa sau neconfirmate.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card dashboard-chart-card mt-4">
        <div class="card-header bg-white"><strong>Istoric transferuri recente</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Numar</th>
                        <th class="culoare2 text-white">Flux</th>
                        <th class="culoare2 text-white">Sofer</th>
                        <th class="culoare2 text-white">Linii</th>
                        <th class="culoare2 text-white">Aviz</th>
                        <th class="culoare2 text-white">Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($recentTransfers as $transfer)
                    <tr>
                        <td>{{ $transfer->number }}</td>
                        <td>{{ $transfer->sourceLocation?->name }} <i class="fa-solid fa-arrow-right mx-1"></i> {{ $transfer->destinationLocation?->name }}</td>
                        <td>{{ $transfer->driver?->name ?? '-' }}</td>
                        <td>{{ $transfer->lines_count }}</td>
                        <td>{{ $transfer->document_number ?: '-' }}</td>
                        <td><x-status :status="$transfer->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
