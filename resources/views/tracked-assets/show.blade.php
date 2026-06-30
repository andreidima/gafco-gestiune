@extends('layouts.app')

@section('title', $asset->asset_code)

@section('content')
<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <span class="dashboard-pill"><i class="fa-solid fa-qrcode me-2"></i>{{ $asset->qr_code }}</span>
                <h3 class="mb-2">{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }}</h3>
                <p class="mb-0 text-muted">Locatie curenta, responsabil, stare si istoric transferuri.</p>
            </div>
            <div class="qr-card text-center">
                <div class="qr-box"><i class="fa-solid fa-qrcode"></i></div>
                <div class="fw-semibold mt-2">{{ $asset->qr_code }}</div>
                <div class="small text-muted">Demo QR</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="stat-card accent-slate h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Locatie curenta</h6>
                        <p class="stat-sub">{{ $asset->currentLocation?->name ?? 'Fara locatie' }}</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-location-dot"></i></div>
                </div>
                <div class="small text-muted">Responsabil</div>
                <div class="fs-5 fw-semibold">{{ $asset->currentCustodian?->name ?? 'Fara responsabil' }}</div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="stat-card accent-forest h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Stare</h6>
                        <p class="stat-sub">{{ str_replace('_', ' ', $asset->condition) }}</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                </div>
                <div class="small text-muted">Status operational</div>
                <div class="fs-5 fw-semibold">{{ str_replace('_', ' ', $asset->status) }}</div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="stat-card accent-amber h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Ultima verificare</h6>
                        <p class="stat-sub">{{ optional($asset->last_verified_at)->format('d.m.Y H:i') ?? 'Neverificat' }}</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                </div>
                <div class="small text-muted">Serie</div>
                <div class="fs-5 fw-semibold">{{ $asset->serial_number ?: '-' }}</div>
            </div>
        </div>
    </div>

    <div class="card dashboard-chart-card mt-4">
        <div class="card-header bg-white"><strong><i class="fa-solid fa-clock-rotate-left me-1"></i> Istoric transferuri</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Transfer</th>
                        <th class="culoare2 text-white">Flux</th>
                        <th class="culoare2 text-white">Sofer</th>
                        <th class="culoare2 text-white">Aprobat</th>
                        <th class="culoare2 text-white">Confirmat</th>
                        <th class="culoare2 text-white">Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($history as $transfer)
                    <tr>
                        <td>{{ $transfer->number }}</td>
                        <td>{{ $transfer->sourceLocation?->name }} <i class="fa-solid fa-arrow-right mx-1"></i> {{ $transfer->destinationLocation?->name }}</td>
                        <td>{{ $transfer->driver?->name ?? '-' }}</td>
                        <td>{{ $transfer->approver?->name ?? '-' }}</td>
                        <td>{{ $transfer->confirmer?->name ?? '-' }}</td>
                        <td><x-status :status="$transfer->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Nu exista istoric pentru acest echipament.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
