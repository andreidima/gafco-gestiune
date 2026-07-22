@extends('layouts.app')

@section('title', $asset->asset_code)

@section('content')
@php
    $statusLabels = ['available' => 'Disponibil', 'in_use' => 'In folosinta', 'in_transfer' => 'In transfer', 'maintenance' => 'In service', 'lost' => 'Lipsa'];
    $conditionLabels = ['good' => 'Bun', 'used' => 'Uzura normala', 'damaged' => 'Deteriorat', 'needs_service' => 'Necesita service'];
    $limitedViewer = ! auth()->user()->isManagementUser();
    $backRoute = auth()->user()->isManagementUser()
        ? route('tracked-assets.index')
        : (auth()->user()->hasRole('contabil') ? route('reports.index') : route('qr-scan.index'));
    $visiblePerson = static function ($person, string $genericLabel) use ($limitedViewer): string {
        if (! $person) {
            return '-';
        }

        if (! $limitedViewer || (int) $person->id === (int) auth()->id()) {
            return (int) $person->id === (int) auth()->id() && $limitedViewer ? 'Tu' : $person->name;
        }

        return $genericLabel;
    };
@endphp
<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <span class="dashboard-pill"><i class="fa-solid fa-qrcode me-2"></i>{{ $asset->qr_code }}</span>
                <h3 class="mb-2">{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }}</h3>
                <p class="mb-0 text-muted">Locatie curenta, responsabil, stare si istoric transferuri.</p>
            </div>
            <div class="d-flex align-items-start gap-2">
                @if(auth()->user()->canManageTrackedAssets())<a href="{{ route('tracked-assets.edit', $asset) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen me-1"></i>Modifica</a>@endif
                <a href="{{ $backRoute }}" class="btn btn-outline-secondary btn-sm">Inapoi</a>
                <div class="qr-card text-center">
                    <div class="qr-box"><i class="fa-solid fa-qrcode"></i></div>
                    <div class="fw-semibold mt-2">{{ $asset->qr_code }}</div>
                    <div class="small text-muted">Cod de identificare</div>
                </div>
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
                <div class="fs-5 fw-semibold">{{ $asset->currentCustodian ? $visiblePerson($asset->currentCustodian, 'Alocat unui coleg') : 'Fara responsabil' }}</div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="stat-card accent-forest h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Stare</h6>
                        <p class="stat-sub">{{ $conditionLabels[$asset->condition] ?? $asset->condition }}</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                </div>
                <div class="small text-muted">Status operational</div>
                <div class="fs-5 fw-semibold">{{ $statusLabels[$asset->status] ?? $asset->status }}</div>
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
                        <td>{{ $visiblePerson($transfer->driver, 'Sofer alocat') }}</td>
                        <td>{{ $visiblePerson($transfer->approver, 'Aprobat') }}</td>
                        <td>{{ $visiblePerson($transfer->confirmer, 'Confirmat') }}</td>
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
