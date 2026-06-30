@extends('layouts.app')

@section('title', 'Acasa')

@section('content')
@php
    $activeTransfers = $stats['In tranzit'] ?? 0;
    $driverRequestCount = $stats['Cereri sofer'] ?? 0;
    $sites = $stats['Santiere active'] ?? 0;
    $items = $stats['Articole'] ?? 0;
    $maxStat = max(1, $activeTransfers, $driverRequestCount, $sites, $items);
    $barTransfers = round(($activeTransfers / $maxStat) * 100);
    $barDrivers = round(($driverRequestCount / $maxStat) * 100);
    $barSites = round(($sites / $maxStat) * 100);
    $barItems = round(($items / $maxStat) * 100);
@endphp

<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="dashboard-pill">
                    <i class="fa-solid fa-chart-line me-2"></i> Panou acasa
                </span>
                <h3 class="mb-2">Situatie curenta gestiune</h3>
                <p class="mb-0 text-muted">Statistici rapide pentru santiere, transferuri, receptii si cereri sofer.</p>
            </div>
            <div class="dashboard-highlight text-end">
                <div class="dashboard-highlight-label">Santiere active</div>
                <div class="dashboard-highlight-value">{{ $sites }}</div>
                <div class="dashboard-highlight-sub">Monitorizate in aplicatie.</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="stat-card accent-rose h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Baze</h6>
                        <p class="stat-sub">Puncte principale de stoc.</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-warehouse"></i></div>
                </div>
                <div class="stat-value">{{ $stats['Baze'] ?? 0 }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('locations.index', ['type' => 'base']) }}">Vezi baze</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card accent-slate h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Articole</h6>
                        <p class="stat-sub">Materiale, scule si utilaje.</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                </div>
                <div class="stat-value">{{ $items }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('catalog-items.index') }}">Vezi nomenclator</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card accent-amber h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">In tranzit</h6>
                        <p class="stat-sub">Transferuri nefinalizate.</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-right-left"></i></div>
                </div>
                <div class="stat-value">{{ $activeTransfers }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('transfers.index') }}">Vezi transferuri</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="stat-card accent-forest h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Cereri sofer</h6>
                        <p class="stat-sub">Cereri deschise sau alocate.</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-truck"></i></div>
                </div>
                <div class="stat-value">{{ $driverRequestCount }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('driver-requests.index') }}">Dispecerat</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-12">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Grafic comparativ</h5>
                            <p class="mb-0 text-muted">Indicatori principali pentru fluxul operational.</p>
                        </div>
                        <span class="dashboard-badge">KPI live</span>
                    </div>
                    <div class="chart-rows">
                        <div class="chart-row accent-rose">
                            <div class="chart-label">Santiere active</div>
                            <div class="chart-bar"><span class="chart-bar-fill" style="width: {{ $barSites }}%;"></span></div>
                            <div class="chart-value">{{ $sites }}</div>
                        </div>
                        <div class="chart-row accent-slate">
                            <div class="chart-label">Articole active</div>
                            <div class="chart-bar"><span class="chart-bar-fill" style="width: {{ $barItems }}%;"></span></div>
                            <div class="chart-value">{{ $items }}</div>
                        </div>
                        <div class="chart-row accent-amber">
                            <div class="chart-label">Transferuri in tranzit</div>
                            <div class="chart-bar"><span class="chart-bar-fill" style="width: {{ $barTransfers }}%;"></span></div>
                            <div class="chart-value">{{ $activeTransfers }}</div>
                        </div>
                        <div class="chart-row accent-forest">
                            <div class="chart-label">Cereri sofer</div>
                            <div class="chart-bar"><span class="chart-bar-fill" style="width: {{ $barDrivers }}%;"></span></div>
                            <div class="chart-value">{{ $driverRequestCount }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4">
        <div class="col-xl-8">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="fa-solid fa-right-left me-1"></i> Transferuri recente</strong>
                    <a href="{{ route('transfers.index') }}" class="btn btn-sm stat-btn accent-slate">Vezi toate</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="culoare2 text-white">Numar</th>
                                <th class="culoare2 text-white">Flux</th>
                                <th class="culoare2 text-white">Sofer</th>
                                <th class="culoare2 text-white">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($transfers as $transfer)
                            <tr>
                                <td class="fw-semibold">{{ $transfer->number }}</td>
                                <td>{{ $transfer->sourceLocation?->name }} <i class="fa-solid fa-arrow-right mx-1"></i> {{ $transfer->destinationLocation?->name }}</td>
                                <td>{{ $transfer->driver?->name ?? 'Nealocat' }}</td>
                                <td><x-status :status="$transfer->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Nu exista transferuri.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="fa-solid fa-truck me-1"></i> Cereri sofer</strong>
                    <a href="{{ route('driver-requests.index') }}" class="btn btn-sm stat-btn accent-forest">Dispecerat</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($driverRequests as $request)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $request->number }}</div>
                                    <div class="small text-secondary">{{ $request->site?->name }} - {{ $request->assignedDriver?->name ?? 'fara sofer' }}</div>
                                </div>
                                <x-status :status="$request->status" />
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-secondary">Nu exista cereri active.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card dashboard-chart-card shadow-sm mt-4">
        <div class="card-header bg-white"><strong><i class="fa-solid fa-boxes-stacked me-1"></i> Snapshot stocuri</strong></div>
        <div class="row g-0">
            @forelse($stockSnapshot as $stock)
                <div class="col-md-6 col-xl-3 border-end border-bottom">
                    <div class="p-3">
                        <div class="fw-semibold">{{ $stock->catalogItem?->name }}</div>
                        <div class="small text-secondary">{{ $stock->location?->name }}</div>
                        <div class="h4 mt-2 mb-0">{{ number_format((float) $stock->quantity, 2) }} {{ $stock->catalogItem?->unit }}</div>
                    </div>
                </div>
            @empty
                <div class="col-12 p-4 text-secondary">Nu exista stocuri.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
