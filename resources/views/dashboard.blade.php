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
    $assetLabels = [
        'available' => 'Disponibile',
        'in_use' => 'In lucru',
        'in_transfer' => 'In transfer',
        'maintenance' => 'Mentenanta',
        'lost' => 'Pierdute',
    ];
    $transferLabels = [
        'pending_approval' => 'Asteapta',
        'approved' => 'Aprobate',
        'assigned' => 'Alocate',
        'in_transit' => 'Tranzit',
        'received' => 'Primite',
        'cancelled' => 'Anulate',
    ];
    $maxAssetStatus = max(1, ...collect($assetLabels)->keys()->map(fn ($status) => (int) ($assetStatusCounts[$status] ?? 0))->all());
    $maxTransferStatus = max(1, ...collect($transferLabels)->keys()->map(fn ($status) => (int) ($transferStatusCounts[$status] ?? 0))->all());
    $maxConsumption = max(1, ...$consumptionTrend->pluck('value')->all());
    $maxTopLocation = max(1, ...$topLocations->pluck('assets_count')->all());
    $assetTotal = max(1, collect($assetStatusCounts)->sum());
    $assetAvailable = round(((int) ($assetStatusCounts['available'] ?? 0) / $assetTotal) * 100);
    $assetInUse = round(((int) ($assetStatusCounts['in_use'] ?? 0) / $assetTotal) * 100);
    $assetInTransfer = round(((int) ($assetStatusCounts['in_transfer'] ?? 0) / $assetTotal) * 100);
    $transferTotal = max(1, collect($transferStatusCounts)->sum());
    $transferReceived = round(((int) ($transferStatusCounts['received'] ?? 0) / $transferTotal) * 100);
    $transferActive = round((((int) ($transferStatusCounts['assigned'] ?? 0) + (int) ($transferStatusCounts['in_transit'] ?? 0)) / $transferTotal) * 100);
    $transferPending = round(((int) ($transferStatusCounts['pending_approval'] ?? 0) / $transferTotal) * 100);
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
        <div class="col-md-6 col-lg-2">
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
        <div class="col-md-6 col-lg-2">
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
        <div class="col-md-6 col-lg-2">
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
        <div class="col-md-6 col-lg-2">
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
        <div class="col-md-6 col-lg-2">
            <div class="stat-card accent-danger h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Alerte</h6>
                        <p class="stat-sub">Tranzit intarziat si pierderi.</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
                <div class="stat-value">{{ $stats['Alerte'] ?? 0 }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('reports.index') }}">Vezi rapoarte</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-2">
            <div class="stat-card accent-teal h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Receptii luna</h6>
                        <p class="stat-sub">Intrari de la furnizori.</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-receipt"></i></div>
                </div>
                <div class="stat-value">{{ $stats['Receptii luna'] ?? 0 }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('supplier-receptions.index') }}">Vezi receptii</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="fa-solid fa-circle-notch me-1"></i> Distributie echipamente</strong>
                    <span class="dashboard-badge">{{ $assetTotal }} total</span>
                </div>
                <div class="card-body">
                    <div class="donut-layout">
                        <div class="donut-chart"
                             style="--slice-1: {{ $assetAvailable }}%; --slice-2: {{ $assetAvailable + $assetInUse }}%; --slice-3: {{ $assetAvailable + $assetInUse + $assetInTransfer }}%;">
                            <div>
                                <strong>{{ $assetAvailable }}%</strong>
                                <span>disponibile</span>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <div><span class="legend-dot legend-green"></span> Disponibile <strong>{{ $assetStatusCounts['available'] ?? 0 }}</strong></div>
                            <div><span class="legend-dot legend-blue"></span> In lucru <strong>{{ $assetStatusCounts['in_use'] ?? 0 }}</strong></div>
                            <div><span class="legend-dot legend-amber"></span> In transfer <strong>{{ $assetStatusCounts['in_transfer'] ?? 0 }}</strong></div>
                            <div><span class="legend-dot legend-muted"></span> Alte statusuri <strong>{{ $assetTotal - (($assetStatusCounts['available'] ?? 0) + ($assetStatusCounts['in_use'] ?? 0) + ($assetStatusCounts['in_transfer'] ?? 0)) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="fa-solid fa-circle-notch me-1"></i> Distributie transferuri</strong>
                    <span class="dashboard-badge">{{ $transferTotal }} total</span>
                </div>
                <div class="card-body">
                    <div class="donut-layout">
                        <div class="donut-chart donut-chart-alt"
                             style="--slice-1: {{ $transferReceived }}%; --slice-2: {{ $transferReceived + $transferActive }}%; --slice-3: {{ $transferReceived + $transferActive + $transferPending }}%;">
                            <div>
                                <strong>{{ $transferReceived }}%</strong>
                                <span>primite</span>
                            </div>
                        </div>
                        <div class="donut-legend">
                            <div><span class="legend-dot legend-green"></span> Primite <strong>{{ $transferStatusCounts['received'] ?? 0 }}</strong></div>
                            <div><span class="legend-dot legend-blue"></span> Active <strong>{{ ($transferStatusCounts['assigned'] ?? 0) + ($transferStatusCounts['in_transit'] ?? 0) }}</strong></div>
                            <div><span class="legend-dot legend-amber"></span> Asteapta aprobare <strong>{{ $transferStatusCounts['pending_approval'] ?? 0 }}</strong></div>
                            <div><span class="legend-dot legend-muted"></span> Alte statusuri <strong>{{ $transferTotal - (($transferStatusCounts['received'] ?? 0) + ($transferStatusCounts['assigned'] ?? 0) + ($transferStatusCounts['in_transit'] ?? 0) + ($transferStatusCounts['pending_approval'] ?? 0)) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4">
        <div class="col-xl-5">
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
        <div class="col-xl-7">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Consum materiale - ultimele 30 zile</h5>
                            <p class="mb-0 text-muted">Volum raportat de sefi de santier.</p>
                        </div>
                        <a href="{{ route('consumption-reports.index') }}" class="btn btn-sm stat-btn accent-teal">Consum</a>
                    </div>
                    <div class="mini-chart" aria-label="Consum materiale ultimele 30 zile">
                        @foreach($consumptionTrend as $point)
                            <div class="mini-chart-column" title="{{ $point['label'] }}: {{ $point['value'] }}">
                                <span style="height: {{ max(8, round(($point['value'] / $maxConsumption) * 100)) }}%;"></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-2">
                        <span>{{ $consumptionTrend->first()['label'] ?? '' }}</span>
                        <span>{{ $consumptionTrend->last()['label'] ?? '' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4">
        <div class="col-xl-4">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="fa-solid fa-screwdriver-wrench me-1"></i> Echipamente pe status</strong>
                    <span class="dashboard-badge">{{ $stats['Asset-uri QR'] ?? 0 }} QR</span>
                </div>
                <div class="card-body">
                    <div class="chart-rows">
                        @foreach($assetLabels as $status => $label)
                            @php($value = (int) ($assetStatusCounts[$status] ?? 0))
                            <div class="chart-row compact-row">
                                <div class="chart-label">{{ $label }}</div>
                                <div class="chart-bar"><span class="chart-bar-fill" style="width: {{ round(($value / $maxAssetStatus) * 100) }}%;"></span></div>
                                <div class="chart-value">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white"><strong><i class="fa-solid fa-route me-1"></i> Transferuri pe status</strong></div>
                <div class="card-body">
                    <div class="transfer-funnel">
                        @foreach($transferLabels as $status => $label)
                            @php($value = (int) ($transferStatusCounts[$status] ?? 0))
                            <div class="funnel-step">
                                <span>{{ $label }}</span>
                                <strong>{{ $value }}</strong>
                                <div class="funnel-meter"><span style="width: {{ round(($value / $maxTransferStatus) * 100) }}%;"></span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong><i class="fa-solid fa-location-dot me-1"></i> Top locatii dupa echipamente</strong>
                    <a href="{{ route('locations.index') }}" class="btn btn-sm stat-btn accent-rose">Locatii</a>
                </div>
                <div class="card-body">
                    <div class="chart-rows">
                        @foreach($topLocations as $location)
                            <div class="chart-row compact-row">
                                <div class="chart-label">{{ $location->code }}</div>
                                <div class="chart-bar"><span class="chart-bar-fill" style="width: {{ round(($location->assets_count / $maxTopLocation) * 100) }}%;"></span></div>
                                <div class="chart-value">{{ $location->assets_count }}</div>
                            </div>
                        @endforeach
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

    <div class="row g-3 mt-4">
        <div class="col-xl-5">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white"><strong><i class="fa-solid fa-bell me-1"></i> Alerte demo</strong></div>
                <div class="card-body">
                    <div class="alert-tile alert-tile-warning mb-3">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                        <div>
                            <strong>{{ $stats['In tranzit'] ?? 0 }} transferuri in tranzit</strong>
                            <div class="small">Verifica intarzierile peste 12 ore in rapoarte.</div>
                        </div>
                    </div>
                    <div class="alert-tile alert-tile-danger">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <div>
                            <strong>{{ $assetStatusCounts['lost'] ?? 0 }} echipamente pierdute</strong>
                            <div class="small">Apar in rapoarte si in lista de echipamente.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card dashboard-chart-card shadow-sm h-100">
                <div class="card-header bg-white"><strong><i class="fa-solid fa-clock-rotate-left me-1"></i> Activitate recenta</strong></div>
                <div class="list-group list-group-flush">
                    @foreach($activityFeed as $activity)
                        <div class="list-group-item activity-item">
                            <div class="activity-icon"><i class="fa-solid {{ $activity['icon'] }}"></i></div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between gap-3">
                                    <strong>{{ $activity['type'] }} - {{ $activity['title'] }}</strong>
                                    <span class="small text-muted">{{ optional($activity['date'])->format('d.m H:i') }}</span>
                                </div>
                                <div class="small text-secondary">{{ $activity['description'] }}</div>
                            </div>
                            <x-status :status="$activity['status']" />
                        </div>
                    @endforeach
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
