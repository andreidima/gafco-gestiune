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
    $maxConsumption = max(1, (float) ($consumptionTrend->max('value') ?? 0));
    $maxTopLocation = max(1, (int) ($topLocations->max('assets_count') ?? 0));
    $assetTotal = (int) collect($assetStatusCounts)->sum();
    $assetDivisor = max(1, $assetTotal);
    $assetAvailable = round(((int) ($assetStatusCounts['available'] ?? 0) / $assetDivisor) * 100);
    $assetInUse = round(((int) ($assetStatusCounts['in_use'] ?? 0) / $assetDivisor) * 100);
    $assetInTransfer = round(((int) ($assetStatusCounts['in_transfer'] ?? 0) / $assetDivisor) * 100);
    $transferTotal = (int) collect($transferStatusCounts)->sum();
    $transferDivisor = max(1, $transferTotal);
    $transferReceived = round(((int) ($transferStatusCounts['received'] ?? 0) / $transferDivisor) * 100);
    $transferActive = round((((int) ($transferStatusCounts['assigned'] ?? 0) + (int) ($transferStatusCounts['in_transit'] ?? 0)) / $transferDivisor) * 100);
    $transferPending = round(((int) ($transferStatusCounts['pending_approval'] ?? 0) / $transferDivisor) * 100);
    $dashboardCopy = match ($dashboardMode) {
        'driver' => ['fa-truck-fast', 'Spatiul meu de lucru', 'Sarcinile care au nevoie de atentia ta', 'Vezi doar sarcinile tale, termenele si actiunile pe care le poti face.'],
        'worker' => ['fa-helmet-safety', 'Activitate teren', 'Confirmari si echipamente in custodie', 'Acces rapid la predari, echipamente si scanarea QR.'],
        'manager' => ['fa-list-check', 'Panou operational', 'Ce necesita atentia ta', 'Aprobarile si sarcinile locatiilor tale apar primele.'],
        'operations' => ['fa-chart-line', 'Panou operational', 'Ce necesita atentie acum', 'Actiunile urgente apar inaintea statisticilor generale.'],
        default => ['fa-house', 'Acasa', 'Contul tau este activ', 'Administratorul trebuie sa iti atribuie un rol operational pentru acces la module.'],
    };
@endphp

<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="dashboard-pill">
                    <i class="fa-solid {{ $dashboardCopy[0] }} me-2"></i>
                    {{ $dashboardCopy[1] }}
                </span>
                <h3 class="mb-2">{{ $dashboardCopy[2] }}</h3>
                <p class="mb-0 text-muted">{{ $dashboardCopy[3] }}</p>
            </div>
            <div class="dashboard-hero-tools">
                <x-live-view view-key="dashboard" />
                @if($showOperationsOverview)
                    <div class="dashboard-highlight text-end">
                        <div class="dashboard-highlight-label">Santiere active</div>
                        <div class="dashboard-highlight-value">{{ $sites }}</div>
                        <div class="dashboard-highlight-sub">Monitorizate in aplicatie.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($quickActions)
        <nav class="dashboard-quick-actions mb-4" aria-label="Scurtături pentru rolul meu">
            <span class="dashboard-quick-actions-label">Acces rapid</span>
            @foreach($quickActions as $action)
                <a href="{{ $action['href'] }}" class="dashboard-quick-action">
                    <i class="fa-solid {{ $action['icon'] }}" aria-hidden="true"></i>
                    {{ $action['label'] }}
                </a>
            @endforeach
        </nav>
    @endif

    @if($actionQueues)
    <div class="action-queue-grid mb-4" aria-label="Actiuni prioritare">
        @foreach($actionQueues as $queue)
            <a href="{{ $queue['href'] }}" class="action-queue-card {{ $queue['tone'] }} text-decoration-none">
                <span class="action-queue-icon"><i class="fa-solid {{ $queue['icon'] }}"></i></span>
                <span class="action-queue-content">
                    @if($queue['count'] !== null)<strong>{{ $queue['count'] }}</strong>@endif
                    <span class="action-queue-title">{{ $queue['title'] }}</span>
                    <small>{{ $queue['description'] }}</small>
                </span>
                <i class="fa-solid fa-arrow-right action-queue-arrow" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
    @endif

    @if($showOperationsOverview)
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
                    <a class="btn btn-sm stat-btn" href="{{ route('tasks.dispatch') }}">Dispecerat</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-2">
            <div class="stat-card accent-danger h-100">
                <div class="stat-card-top">
                    <div>
                        <h6 class="mb-1">Alerte</h6>
                        <p class="stat-sub">Stoc și recepții care cer verificare.</p>
                    </div>
                    <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
                <div class="stat-value">{{ $stats['Alerte'] ?? 0 }}</div>
                <div class="stat-footer">
                    <a class="btn btn-sm stat-btn" href="{{ route('alerts.index') }}">Vezi alertele</a>
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
                    <a href="{{ route('tasks.dispatch') }}" class="btn btn-sm stat-btn accent-forest">Dispecerat</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse($driverRequests as $request)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between gap-3">
                                <div>
                                    <div class="fw-semibold">{{ $request->number }}</div>
                                    <div class="small text-secondary">{{ $request->destinationLocation?->name ?? 'Fara locatie' }} - {{ $request->currentAssignment?->driver?->name ?? 'fara sofer' }}</div>
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
                <div class="card-header bg-white"><strong><i class="fa-solid fa-binoculars me-1"></i> Monitorizare operațională</strong></div>
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
    @elseif($dashboardMode === 'driver')
        <div class="card dashboard-chart-card shadow-sm">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong><i class="fa-solid fa-list-check me-1"></i> Urmatoarele mele sarcini</strong>
                    <div class="small text-muted">Intarzierile si sarcinile care asteapta raspuns apar primele.</div>
                </div>
                <a href="{{ route('tasks.index') }}" class="btn btn-sm btn-outline-primary">Vezi toate</a>
            </div>
            <div class="list-group list-group-flush driver-dashboard-task-list">
                @forelse($ownTasks as $task)
                    <a href="{{ route('tasks.show', $task) }}" class="list-group-item list-group-item-action py-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <div class="resource-primary">{{ $task->title }}</div>
                                <div class="resource-code">{{ $task->number }}</div>
                            </div>
                            <x-status :status="$task->status" />
                        </div>
                        <div class="row g-2 mt-1 small">
                            <div class="col-md-6"><i class="fa-solid fa-route me-1 text-muted"></i>{{ $task->sourceLocation?->code ?? 'Nespecificat' }} <span aria-hidden="true">&rarr;</span> {{ $task->destinationLocation?->code ?? 'Nespecificat' }}</div>
                            <div class="col-md-6 {{ $task->isOverdue() ? 'deadline-overdue fw-bold' : 'text-muted' }}">
                                <i class="fa-solid fa-flag-checkered me-1"></i>
                                @if($task->manager_deadline){{ $task->manager_deadline->format('d.m.Y H:i') }} ({{ $task->manager_deadline->diffForHumans() }})@else Termen nespecificat @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="list-group-item text-center text-muted py-4">Nu ai sarcini active. Notificarile noi vor aparea aici.</div>
                @endforelse
            </div>
        </div>
    @elseif($dashboardMode === 'worker')
        <div class="card dashboard-chart-card shadow-sm">
            <div class="card-body text-center py-5">
                <span class="resource-page-icon mx-auto mb-3"><i class="fa-solid fa-mobile-screen-button"></i></span>
                <h4>Continua din modul de teren</h4>
                <p class="text-muted">Acolo găsești confirmările, echipamentele și materialele aflate în responsabilitatea ta.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('field.worker') }}" class="btn btn-primary"><i class="fa-solid fa-hand-holding-hand me-1"></i>Custodia mea</a>
                    <a href="{{ route('qr-scan.index') }}" class="btn btn-outline-primary"><i class="fa-solid fa-qrcode me-1"></i>Scaneaza QR</a>
                </div>
            </div>
        </div>
    @elseif($dashboardMode === 'manager')
        <div class="card dashboard-chart-card shadow-sm">
            <div class="card-body text-center py-5">
                <span class="resource-page-icon mx-auto mb-3"><i class="fa-solid fa-clipboard-check"></i></span>
                <h4>Continua cu operatiunile locatiilor tale</h4>
                <p class="text-muted">Aprobarile, transferurile si sarcinile raman limitate la locatiile pe care le administrezi.</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="{{ route('field.site-manager') }}" class="btn btn-primary">Panou locatie</a>
                    <a href="{{ route('tasks.index') }}" class="btn btn-outline-primary">Sarcini</a>
                    <a href="{{ route('transfers.index') }}" class="btn btn-outline-primary">Transferuri</a>
                </div>
            </div>
        </div>
    @else
        <div class="card dashboard-chart-card shadow-sm">
            <div class="card-body text-center py-5">
                <span class="resource-page-icon mx-auto mb-3"><i class="fa-solid fa-user-lock"></i></span>
                <h4>Rol operational neatribuit</h4>
                <p class="text-muted mb-0">Contacteaza administratorul pentru a primi acces la modulele necesare.</p>
            </div>
        </div>
    @endif
</div>
@endsection
