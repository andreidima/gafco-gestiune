@extends('layouts.app')

@section('title', 'Transferuri')

@section('content')
@php
    $transferStatusLabels = ['pending_approval' => 'Asteapta aprobare', 'approved' => 'Aprobat', 'in_transit' => 'In tranzit', 'received' => 'Receptionat', 'cancelled' => 'Anulat'];
    $locationTypeLabels = ['base' => 'Baza', 'site' => 'Santier'];
    $assignmentLabels = ['pending' => 'Asteapta raspuns', 'accepted' => 'Acceptata', 'reassignment_requested' => 'Realocare solicitata'];
    $approvalStatusLabels = ['pending' => 'In asteptare', 'approved' => 'Aprobate', 'rejected' => 'Refuzate'];
    $approvalScopeLabels = ['source_manager' => 'Sursa', 'destination_manager' => 'Destinatie', 'driver' => 'Sofer'];
    $approvalScopeOrder = ['source_manager' => 0, 'destination_manager' => 1, 'driver' => 2];
    $isDriver = auth()->user()->usesDriverWorkspace();

    $formatMinuteDelta = static function (int $minutes): string {
        if ($minutes === 0) {
            return 'la termen';
        }

        $prefix = $minutes > 0 ? '+' : '-';
        $absolute = abs($minutes);
        $days = intdiv($absolute, 1440);
        $absolute %= 1440;
        $hours = intdiv($absolute, 60);
        $remainingMinutes = $absolute % 60;
        $parts = [];
        if ($days > 0) {
            $parts[] = $days.'z';
        }
        if ($hours > 0) {
            $parts[] = $hours.'h';
        }
        if ($days === 0 && ($remainingMinutes > 0 || $hours === 0)) {
            $parts[] = $remainingMinutes.'m';
        }

        return $prefix.implode(' ', $parts);
    };
    $formatQuantity = static fn (float $quantity): string => \App\Support\LocalizedNumber::quantity($quantity);

    $activeTransferFilters = [];
    if (request()->filled('search')) {
        $activeTransferFilters['search'] = 'Cautare: „'.request('search').'”';
    }
    if (request()->filled('purpose')) {
        $activeTransferFilters['purpose'] = 'Flux: '.(request('purpose') === 'return' ? 'Retur' : 'Transfer');
    }
    if (request()->filled('status')) {
        $activeTransferFilters['status'] = 'Status: '.($transferStatusLabels[request('status')] ?? request('status'));
    }
    if (request()->filled('source_location_id')) {
        $sourceFilter = $locations->firstWhere('id', (int) request('source_location_id'));
        $activeTransferFilters['source_location_id'] = 'Sursa: '.($sourceFilter?->code ?? '#'.request('source_location_id'));
    }
    if (request()->filled('destination_location_id')) {
        $destinationFilter = $locations->firstWhere('id', (int) request('destination_location_id'));
        $activeTransferFilters['destination_location_id'] = 'Destinatie: '.($destinationFilter?->code ?? '#'.request('destination_location_id'));
    }
    if (! $isDriver && request()->filled('project_id')) {
        $activeTransferFilters['project_id'] = 'Proiect: '.($projects->firstWhere('id', (int) request('project_id'))?->code ?? '#'.request('project_id'));
    }
    if (! $isDriver && request()->filled('driver_id')) {
        $activeTransferFilters['driver_id'] = 'Sofer: '.($drivers->firstWhere('id', (int) request('driver_id'))?->name ?? '#'.request('driver_id'));
    }
    if (request()->filled('approval_status')) {
        $activeTransferFilters['approval_status'] = 'Aprobari: '.($approvalStatusLabels[request('approval_status')] ?? request('approval_status'));
    }
    if (request()->boolean('overdue')) {
        $activeTransferFilters['overdue'] = 'Doar intarziate';
    }
    if (request()->boolean('archived')) {
        $activeTransferFilters['archived'] = 'Include arhivate';
    }
    $advancedTransferFilterCount = count($activeTransferFilters) - (isset($activeTransferFilters['search']) ? 1 : 0);
@endphp

<div class="resource-shell">
    <x-resource-page-header
        title="Transferuri si retururi"
        description="Materiale si echipamente mutate intre santiere si baze, cu aprobari pe fiecare revizie."
        :count="$totalTransfers"
        :filtered-count="$transfers->total()"
        icon="fa-right-left"
        :create-route="Gate::allows('create', \App\Models\Transfer::class) ? route('transfers.create') : null"
        create-label="Transfer nou"
    >
        <x-slot:actions><x-live-view view-key="transfers-index" /></x-slot:actions>
    </x-resource-page-header>

    <form class="resource-filter-panel" method="get" action="{{ route('transfers.index') }}" data-auto-submit-filters data-live-filter-target="#transfers-results">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="resource-filter-toolbar row g-2 align-items-end">
            <div class="resource-filter-search col">
                <label for="transfer-search" class="resource-filter-label">Cautare</label>
                <input id="transfer-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Numar transfer sau document">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit" aria-label="Cauta transferuri"><i class="fa-solid fa-magnifying-glass me-sm-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Cauta</span></button>
            </div>
            <div class="col-auto d-md-none">
                <button class="btn btn-outline-secondary resource-filter-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#transferAdvancedFilters" aria-expanded="false" aria-controls="transferAdvancedFilters">
                    <i class="fa-solid fa-sliders me-1"></i>Filtre
                    <span class="badge text-bg-primary ms-1" data-live-filter-summary @if($advancedTransferFilterCount === 0) hidden @endif>{{ $advancedTransferFilterCount }}</span>
                </button>
            </div>
        </div>

        <div class="collapse d-md-block resource-filter-advanced" id="transferAdvancedFilters">
            <div class="row g-2 align-items-end mt-1">
                <div class="col-xl-2 col-md-3"><label class="resource-filter-label">Flux</label><select name="purpose" class="form-select"><option value="">Toate</option><option value="transfer" @selected(request('purpose') === 'transfer')>Transfer</option><option value="return" @selected(request('purpose') === 'return')>Retur</option></select></div>
                <div class="col-xl-2 col-md-3"><label class="resource-filter-label">Status</label><select name="status" class="form-select"><option value="">Toate</option>@foreach($transferStatusLabels as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Sursa</label><select name="source_location_id" class="form-select" data-tom-select><option value="">Toate</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('source_location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Destinatie</label><select name="destination_location_id" class="form-select" data-tom-select><option value="">Toate</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('destination_location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                @unless($isDriver)
                    <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Proiect</label><select name="project_id" class="form-select" data-tom-select><option value="">Toate proiectele</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((int) request('project_id') === $project->id)>{{ $project->code }} — {{ $project->name }}</option>@endforeach</select></div>
                @endunless
                @unless($isDriver)
                    <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Sofer</label><select name="driver_id" class="form-select" data-tom-select><option value="">Toti</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" data-search="{{ $driver->login_code }}" @selected((string) request('driver_id') === (string) $driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
                @endunless
                <div class="col-xl-2 col-md-6"><label class="resource-filter-label">Aprobari</label><select name="approval_status" class="form-select"><option value="">Toate</option>@foreach($approvalStatusLabels as $status => $label)<option value="{{ $status }}" @selected(request('approval_status') === $status)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-xl-2 col-md-6 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter me-1"></i>Aplica</button><a href="{{ route('transfers.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a></div>
                <div class="col-xl-10 col-12 d-flex flex-wrap align-items-center gap-3 pb-1">
                    <div class="form-check"><input name="overdue" value="1" type="checkbox" class="form-check-input" id="transfer-overdue" @checked(request()->boolean('overdue'))><label for="transfer-overdue" class="form-check-label small">Doar intarziate</label></div>
                    <div class="form-check"><input name="archived" value="1" type="checkbox" class="form-check-input" id="transfer-archived" @checked(request()->boolean('archived'))><label for="transfer-archived" class="form-check-label small">Include arhivate</label></div>
                </div>
            </div>
        </div>
    </form>

    <div id="transfers-results" data-live-filter-results>
    <nav class="resource-filter-presets" aria-label="Vizualizari rapide transferuri">
        <span class="results-meta">Vizualizari rapide</span>
        <a class="resource-filter-preset {{ request('status') === 'pending_approval' ? 'active' : '' }}" href="{{ route('transfers.index', ['status' => 'pending_approval', 'filters_submitted' => 1]) }}"><i class="fa-solid fa-user-check"></i>Asteapta aprobari</a>
        <a class="resource-filter-preset {{ request()->boolean('overdue') ? 'active' : '' }}" href="{{ route('transfers.index', ['overdue' => 1, 'filters_submitted' => 1]) }}"><i class="fa-solid fa-triangle-exclamation"></i>Intarziate</a>
        <a class="resource-filter-preset {{ request('status') === 'in_transit' ? 'active' : '' }}" href="{{ route('transfers.index', ['status' => 'in_transit', 'filters_submitted' => 1]) }}"><i class="fa-solid fa-truck-fast"></i>In tranzit</a>
        <a class="resource-filter-preset {{ request('purpose') === 'return' ? 'active' : '' }}" href="{{ route('transfers.index', ['purpose' => 'return', 'filters_submitted' => 1]) }}"><i class="fa-solid fa-rotate-left"></i>Retururi</a>
    </nav>

    @if($activeTransferFilters)
        <div class="resource-filter-chips mb-2 px-1" aria-label="Filtre active">
            <span class="results-meta">Filtre active:</span>
            @foreach($activeTransferFilters as $filterKey => $filterLabel)
                <a class="filter-chip" href="{{ route('transfers.index', array_merge(request()->except([$filterKey, 'page']), ['filters_submitted' => 1])) }}" title="Elimina filtrul {{ $filterLabel }}">
                    {{ $filterLabel }} <i class="fa-solid fa-xmark ms-1" aria-hidden="true"></i>
                </a>
            @endforeach
            <a class="filter-chip filter-chip-clear" href="{{ route('transfers.index', ['filters_reset' => 1]) }}">Sterge toate</a>
        </div>
    @endif

    <div class="resource-table-card">
        <div class="table-responsive d-none d-md-block">
            <table class="table resource-table">
                <thead><tr><th>Transfer</th><th>Traseu si continut</th><th>Sofer</th><th>Termene</th><th>Aprobari</th><th>Status</th><th class="text-end">Actiuni</th></tr></thead>
                <tbody>
                @forelse($transfers as $transfer)
                    @php
                        $approvals = $transfer->approvals->where('revision', $transfer->revision)->sortBy(fn ($approval) => $approvalScopeOrder[$approval->scope] ?? 99);
                        $pendingApprovals = $approvals->where('status', 'pending');
                        $rejectedApproval = $approvals->firstWhere('status', 'rejected');
                        $actionableTransfer = ! in_array($transfer->status, ['received', 'cancelled'], true) && ! $transfer->archived_at;
                        $pendingForUser = $actionableTransfer ? $pendingApprovals->filter(fn ($approval) => (auth()->user()->hasGlobalAbility('transfers.approve') && $approval->scope !== 'driver')
                            || ($approval->scope === 'driver' && $approval->expected_user_id === auth()->id())
                            || ($approval->location && $approval->location->activeManagers->contains('id', auth()->id()))) : collect();
                        $assignment = $transfer->task?->currentAssignment;
                        $viewerAssignment = $assignment;
                        if ($isDriver && $assignment?->driver_id !== auth()->id() && $assignment?->status === 'pending' && $assignment?->replacedAssignment?->driver_id === auth()->id() && in_array($assignment->replacedAssignment->status, ['accepted', 'reassignment_requested'], true)) {
                            $viewerAssignment = $assignment->replacedAssignment;
                        }
                        $assignmentBelongsToDriver = ! $isDriver || ! $viewerAssignment || $viewerAssignment->driver_id === auth()->id();
                        $displayAssignment = $assignmentBelongsToDriver ? $viewerAssignment : null;
                        $needsDriverResponse = $actionableTransfer && $isDriver && $assignment?->driver_id === auth()->id() && $assignment->status === 'pending';
                        $needsApprovalAction = ! $isDriver && $pendingForUser->isNotEmpty();
                        $needsAllocation = ! $isDriver && $transfer->task?->status === 'unassigned';
                        $isDueSoon = $transfer->task?->manager_deadline && ! $transfer->task->isOverdue() && ! in_array($transfer->task->status, ['completed', 'cancelled', 'archived'], true) && $transfer->task->manager_deadline->lte(now()->addHours(4));
                        $firstLine = $transfer->lines->first();
                        $firstLineLabel = $firstLine?->catalogItem?->name;
                        if ($firstLine?->trackedAsset?->asset_code) {
                            $firstLineLabel = ($firstLineLabel ? $firstLineLabel.' · ' : '').$firstLine->trackedAsset->asset_code;
                        }
                        $estimateDelta = $transfer->task?->manager_deadline && $displayAssignment?->driver_estimate_at
                            ? (int) round(($displayAssignment->driver_estimate_at->getTimestamp() - $transfer->task->manager_deadline->getTimestamp()) / 60)
                            : null;
                        $pendingActionLabels = $pendingApprovals->map(fn ($approval) => $approval->scope === 'driver' && ! $approval->expected_user_id ? 'Aloca sofer' : ($approvalScopeLabels[$approval->scope] ?? $approval->scope))->unique()->implode(', ');
                        $projectOverruns = $transfer->project ? $projectProgressById->get($transfer->project->id, collect())->where('has_overrun', true) : collect();
                        $rowAlertClass = ($transfer->task?->isOverdue() || $rejectedApproval || (! $isDriver && $projectOverruns->isNotEmpty())) ? 'resource-row-alert resource-row-alert-danger' : (($needsDriverResponse || $needsApprovalAction || $needsAllocation || $isDueSoon) ? 'resource-row-alert resource-row-alert-warning' : '');
                    @endphp
                    <tr class="{{ $rowAlertClass }}">
                        <td>
                            <div class="resource-cell-stack">
                                <a class="resource-primary text-decoration-none" href="{{ route('transfers.show', $transfer) }}">{{ $transfer->number }}</a>
                                <span class="resource-secondary">{{ $transfer->purpose === 'return' ? 'Retur' : 'Transfer' }} · rev. {{ $transfer->revision }}@if($transfer->document_number) · {{ $transfer->document_number }}@endif</span>
                                <span>@if($transfer->archived_at)<span class="badge text-bg-secondary">Arhivat</span>@endif <span class="badge text-bg-light border">{{ $locationTypeLabels[$transfer->sourceLocation?->type] ?? '-' }} → {{ $locationTypeLabels[$transfer->destinationLocation?->type] ?? '-' }}</span></span>
                                @if($needsDriverResponse)<span class="resource-secondary text-warning fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i>Necesita raspunsul tau</span>@endif
                                @if($needsApprovalAction)<span class="resource-secondary text-warning fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i>Necesita aprobarea ta</span>@endif
                                @if($needsAllocation)<span class="resource-secondary text-warning fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i>Necesita alocare</span>@endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                <span class="resource-primary">{{ $transfer->sourceLocation?->code ?? '-' }} <i class="fa-solid fa-arrow-right mx-1 text-muted"></i> {{ $transfer->destinationLocation?->code ?? '-' }}</span>
                                @if($transfer->sourceLocation?->name || $transfer->destinationLocation?->name)<span class="resource-secondary">{{ collect([$transfer->sourceLocation?->name, $transfer->destinationLocation?->name])->filter()->implode(' / ') }}</span>@endif
                                @if($firstLine)
                                    <span title="{{ $firstLineLabel }}">{{ \Illuminate\Support\Str::limit($firstLineLabel, 48) }} <span class="resource-secondary">{{ $formatQuantity((float) $firstLine->quantity) }} {{ $firstLine->unit }}</span></span>
                                    @if($transfer->lines_count > 1)<span class="resource-secondary">+{{ $transfer->lines_count - 1 }} {{ $transfer->lines_count === 2 ? 'alta pozitie' : 'alte pozitii' }}</span>@endif
                                @endif
                                @if(! $isDriver && $transfer->project)<span><span class="badge {{ $projectOverruns->isNotEmpty() ? 'text-bg-danger' : 'text-bg-light border' }}">{{ $transfer->project->code }}{{ $projectOverruns->isNotEmpty() ? ' · plan depășit' : '' }}</span></span>@endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                @if($isDriver && $assignment && ! $assignmentBelongsToDriver)<span class="resource-primary">Realocat</span><span class="resource-secondary">Nu mai este alocat tie</span>
                                @else<span class="resource-primary">{{ $displayAssignment?->driver?->name ?? 'Nealocat' }}</span>@if($displayAssignment)<span class="resource-secondary {{ $displayAssignment->status === 'reassignment_requested' ? 'text-warning fw-bold' : '' }}">{{ $assignmentLabels[$displayAssignment->status] ?? $displayAssignment->status }}</span>@endif
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                @if($transfer->task?->manager_deadline)<span class="resource-deadline-manager {{ $transfer->task->isOverdue() ? 'deadline-overdue fw-bold' : '' }}"><i class="fa-solid fa-flag-checkered me-1"></i>Manager: {{ $transfer->task->manager_deadline->format('d.m.Y H:i') }}</span>@endif
                                @if($displayAssignment?->driver_estimate_at)<span class="resource-secondary"><i class="fa-solid fa-user-clock me-1"></i>Sofer: {{ $displayAssignment->driver_estimate_at->format('d.m.Y H:i') }} @if($estimateDelta !== null)<strong class="{{ $estimateDelta > 0 ? 'resource-deadline-late text-danger' : ($estimateDelta < 0 ? 'resource-deadline-early text-success' : '') }}">({{ $formatMinuteDelta($estimateDelta) }})</strong>@endif</span>
                                @elseif($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true))<span class="resource-secondary text-warning"><i class="fa-solid fa-user-clock me-1"></i>Estimare necomunicata</span>@endif
                                @if($transfer->task?->isOverdue())<span class="resource-deadline-overdue text-danger fw-bold">Intarziat cu {{ ltrim($formatMinuteDelta(max(1, (int) ceil((now()->getTimestamp() - $transfer->task->manager_deadline->getTimestamp()) / 60))), '+') }}</span>@endif
                                @if($isDueSoon)<span class="resource-deadline-soon text-warning fw-bold">Expira in {{ ltrim($formatMinuteDelta(max(1, (int) ceil(($transfer->task->manager_deadline->getTimestamp() - now()->getTimestamp()) / 60))), '+') }}</span>@endif
                                @if(! $transfer->task?->manager_deadline && ! $displayAssignment?->driver_estimate_at && ! ($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true)))<span class="text-muted">&mdash;</span>@endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                <div class="approval-progress">
                                    @foreach($approvals as $approval)
                                        <span class="approval-step approval-step-{{ $approval->status }}" title="{{ $approvalScopeLabels[$approval->scope] ?? $approval->scope }}: {{ $approvalStatusLabels[$approval->status] ?? $approval->status }}">
                                            <i class="fa-solid {{ $approval->status === 'approved' ? 'fa-circle-check' : ($approval->status === 'rejected' ? 'fa-circle-xmark' : 'fa-clock') }}" aria-hidden="true"></i>{{ $approvalScopeLabels[$approval->scope] ?? $approval->scope }}
                                        </span>
                                    @endforeach
                                </div>
                                @if($rejectedApproval)<span class="resource-secondary text-danger"><i class="fa-solid fa-circle-exclamation me-1"></i>Refuz: {{ $approvalScopeLabels[$rejectedApproval->scope] ?? $rejectedApproval->scope }}@if($rejectedApproval->decision_note) · {{ \Illuminate\Support\Str::limit($rejectedApproval->decision_note, 38) }}@endif</span>
                                @elseif(! $actionableTransfer && $pendingApprovals->isNotEmpty())<span class="resource-secondary">Inchis cu aprobari nefinalizate</span>
                                @elseif($pendingForUser->isNotEmpty())<span class="resource-secondary text-warning fw-bold">Asteapta actiunea ta</span>
                                @elseif($pendingApprovals->isNotEmpty())<span class="resource-secondary">Urmeaza: {{ $pendingActionLabels }}</span>
                                @else<span class="resource-secondary text-success">Circuit complet</span>@endif
                            </div>
                        </td>
                        <td><x-status :status="$transfer->status" :href="route('transfers.show', $transfer)" /></td>
                        <td>
                            <div class="resource-row-actions">
                                <x-resource-icon-button :href="route('transfers.show', $transfer)" icon="fa-eye" label="Deschide transferul" />
                                @can('update', $transfer)<x-resource-icon-button :href="route('transfers.edit', $transfer)" icon="fa-pen" label="Modifica transferul" variant="outline-secondary" />@endcan
                                @can('create', \App\Models\Transfer::class)
                                    @can('create', \App\Models\Transfer::class)@if($transfer->purpose === 'transfer' && $transfer->status === 'received')
                                        <div class="dropdown"><button class="btn btn-outline-secondary resource-overflow-button" data-bs-toggle="dropdown" aria-expanded="false" title="Mai multe actiuni" aria-label="Mai multe actiuni"><i class="fa-solid fa-ellipsis-vertical"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><a class="dropdown-item" href="{{ route('transfers.create', ['return_of' => $transfer->id]) }}"><i class="fa-solid fa-rotate-left me-2"></i>Initiaza retur</a></li></ul></div>
                                    @endif @endcan
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Nu exista transferuri pentru filtrele selectate. @if($activeTransferFilters)<a href="{{ route('transfers.index') }}" class="ms-1">Sterge filtrele</a>@endif</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list d-md-none">
            @forelse($transfers as $transfer)
                @php
                    $approvals = $transfer->approvals->where('revision', $transfer->revision)->sortBy(fn ($approval) => $approvalScopeOrder[$approval->scope] ?? 99);
                    $pendingApprovals = $approvals->where('status', 'pending');
                    $rejectedApproval = $approvals->firstWhere('status', 'rejected');
                    $actionableTransfer = ! in_array($transfer->status, ['received', 'cancelled'], true) && ! $transfer->archived_at;
                    $pendingForUser = $actionableTransfer ? $pendingApprovals->filter(fn ($approval) => (auth()->user()->hasGlobalAbility('transfers.approve') && $approval->scope !== 'driver')
                        || ($approval->scope === 'driver' && $approval->expected_user_id === auth()->id())
                        || ($approval->location && $approval->location->activeManagers->contains('id', auth()->id()))) : collect();
                    $assignment = $transfer->task?->currentAssignment;
                    $viewerAssignment = $assignment;
                    if ($isDriver && $assignment?->driver_id !== auth()->id() && $assignment?->status === 'pending' && $assignment?->replacedAssignment?->driver_id === auth()->id() && in_array($assignment->replacedAssignment->status, ['accepted', 'reassignment_requested'], true)) {
                        $viewerAssignment = $assignment->replacedAssignment;
                    }
                    $assignmentBelongsToDriver = ! $isDriver || ! $viewerAssignment || $viewerAssignment->driver_id === auth()->id();
                    $displayAssignment = $assignmentBelongsToDriver ? $viewerAssignment : null;
                    $needsDriverResponse = $actionableTransfer && $isDriver && $assignment?->driver_id === auth()->id() && $assignment->status === 'pending';
                    $needsApprovalAction = ! $isDriver && $pendingForUser->isNotEmpty();
                    $needsAllocation = ! $isDriver && $transfer->task?->status === 'unassigned';
                    $isDueSoon = $transfer->task?->manager_deadline && ! $transfer->task->isOverdue() && ! in_array($transfer->task->status, ['completed', 'cancelled', 'archived'], true) && $transfer->task->manager_deadline->lte(now()->addHours(4));
                    $firstLine = $transfer->lines->first();
                    $firstLineLabel = $firstLine?->catalogItem?->name;
                    if ($firstLine?->trackedAsset?->asset_code) {
                        $firstLineLabel = ($firstLineLabel ? $firstLineLabel.' · ' : '').$firstLine->trackedAsset->asset_code;
                    }
                    $estimateDelta = $transfer->task?->manager_deadline && $displayAssignment?->driver_estimate_at
                        ? (int) round(($displayAssignment->driver_estimate_at->getTimestamp() - $transfer->task->manager_deadline->getTimestamp()) / 60)
                        : null;
                    $pendingActionLabels = $pendingApprovals->map(fn ($approval) => $approval->scope === 'driver' && ! $approval->expected_user_id ? 'Aloca sofer' : ($approvalScopeLabels[$approval->scope] ?? $approval->scope))->unique()->implode(', ');
                    $projectOverruns = $transfer->project ? $projectProgressById->get($transfer->project->id, collect())->where('has_overrun', true) : collect();
                    $mobileAlertClass = ($transfer->task?->isOverdue() || $rejectedApproval || (! $isDriver && $projectOverruns->isNotEmpty())) ? 'resource-row-alert resource-row-alert-danger' : (($needsDriverResponse || $needsApprovalAction || $needsAllocation || $isDueSoon) ? 'resource-row-alert resource-row-alert-warning' : '');
                @endphp
                <article class="card resource-mobile-card {{ $mobileAlertClass }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div><a class="resource-primary text-decoration-none" href="{{ route('transfers.show', $transfer) }}">{{ $transfer->number }}</a><div class="resource-secondary">{{ $transfer->purpose === 'return' ? 'Retur' : 'Transfer' }} · rev. {{ $transfer->revision }}@if($transfer->document_number) · {{ $transfer->document_number }}@endif</div></div>
                            <x-status :status="$transfer->status" :href="route('transfers.show', $transfer)" />
                        </div>

                        @if($needsDriverResponse || $needsApprovalAction || $needsAllocation || $transfer->task?->isOverdue() || $isDueSoon || $rejectedApproval || (! $isDriver && $projectOverruns->isNotEmpty()))
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @if($needsDriverResponse)<span class="badge text-bg-warning">Raspunsul tau</span>@endif
                                @if($needsApprovalAction)<span class="badge text-bg-warning">Aprobarea ta</span>@endif
                                @if($needsAllocation)<span class="badge text-bg-warning">Alocare necesara</span>@endif
                                @if($transfer->task?->isOverdue())<span class="badge text-bg-danger">Intarziat</span>@endif
                                @if($isDueSoon)<span class="badge text-bg-warning">Termen apropiat</span>@endif
                                @if($rejectedApproval)<span class="badge text-bg-danger">Aprobare refuzata</span>@endif
                                @if(! $isDriver && $projectOverruns->isNotEmpty())<span class="badge text-bg-danger">Plan proiect depășit</span>@endif
                            </div>
                        @endif

                        <div class="resource-mobile-card-grid">
                            <div class="resource-mobile-card-wide"><span class="resource-filter-label">Traseu</span><strong>{{ $transfer->sourceLocation?->code ?? '-' }} <i class="fa-solid fa-arrow-right mx-1 text-muted"></i> {{ $transfer->destinationLocation?->code ?? '-' }}</strong><span class="resource-secondary">{{ collect([$transfer->sourceLocation?->name, $transfer->destinationLocation?->name])->filter()->implode(' / ') }}</span></div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Continut</span>
                                @if($firstLine)<strong>{{ $firstLineLabel }}</strong><span class="resource-secondary">{{ $formatQuantity((float) $firstLine->quantity) }} {{ $firstLine->unit }}@if($transfer->lines_count > 1) · +{{ $transfer->lines_count - 1 }} {{ $transfer->lines_count === 2 ? 'pozitie' : 'pozitii' }}@endif</span>@else<strong>Fara continut</strong>@endif
                                @if(! $isDriver && $transfer->project)<span class="resource-secondary">Proiect: {{ $transfer->project->code }}</span>@endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Sofer</span>
                                @if($isDriver && $assignment && ! $assignmentBelongsToDriver)<strong>Realocat</strong><span class="resource-secondary">Nu mai este alocat tie</span>
                                @else<strong>{{ $displayAssignment?->driver?->name ?? 'Nealocat' }}</strong>@if($displayAssignment)<span class="resource-secondary">{{ $assignmentLabels[$displayAssignment->status] ?? $displayAssignment->status }}</span>@endif
                                @endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Termene</span>
                                @if($transfer->task?->manager_deadline)<strong class="{{ $transfer->task->isOverdue() ? 'deadline-overdue' : '' }}">Manager: {{ $transfer->task->manager_deadline->format('d.m.Y H:i') }}</strong>@if($isDueSoon)<span class="resource-secondary text-warning fw-bold">Expira in {{ ltrim($formatMinuteDelta(max(1, (int) ceil(($transfer->task->manager_deadline->getTimestamp() - now()->getTimestamp()) / 60))), '+') }}</span>@endif @endif
                                @if($displayAssignment?->driver_estimate_at)<span class="resource-secondary">Sofer: {{ $displayAssignment->driver_estimate_at->format('d.m.Y H:i') }} @if($estimateDelta !== null)<strong class="{{ $estimateDelta > 0 ? 'text-danger' : ($estimateDelta < 0 ? 'text-success' : '') }}">({{ $formatMinuteDelta($estimateDelta) }})</strong>@endif</span>
                                @elseif($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true))<span class="resource-secondary text-warning">Estimare necomunicata</span>@endif
                                @if(! $transfer->task?->manager_deadline && ! $displayAssignment?->driver_estimate_at && ! ($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true)))<span class="text-muted">&mdash;</span>@endif
                            </div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Aprobari</span>
                                <div class="approval-progress">@foreach($approvals as $approval)<span class="approval-step approval-step-{{ $approval->status }}"><i class="fa-solid {{ $approval->status === 'approved' ? 'fa-circle-check' : ($approval->status === 'rejected' ? 'fa-circle-xmark' : 'fa-clock') }}" aria-hidden="true"></i>{{ $approvalScopeLabels[$approval->scope] ?? $approval->scope }}</span>@endforeach</div>
                                @if($rejectedApproval)<span class="resource-secondary text-danger">Refuz: {{ $approvalScopeLabels[$rejectedApproval->scope] ?? $rejectedApproval->scope }}@if($rejectedApproval->decision_note) · {{ \Illuminate\Support\Str::limit($rejectedApproval->decision_note, 70) }}@endif</span>
                                @elseif(! $actionableTransfer && $pendingApprovals->isNotEmpty())<span class="resource-secondary">Inchis cu aprobari nefinalizate</span>
                                @elseif($pendingForUser->isNotEmpty())<span class="resource-secondary text-warning fw-bold">Asteapta actiunea ta</span>
                                @elseif($pendingApprovals->isNotEmpty())<span class="resource-secondary">Urmeaza: {{ $pendingActionLabels }}</span>
                                @else<span class="resource-secondary text-success">Circuit complet</span>@endif
                            </div>
                        </div>

                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('transfers.show', $transfer) }}" class="btn btn-primary btn-sm flex-grow-1"><i class="fa-solid fa-eye me-1"></i>Deschide</a>
                            @can('update', $transfer)<a href="{{ route('transfers.edit', $transfer) }}" class="btn btn-outline-secondary btn-sm" aria-label="Modifica transferul"><i class="fa-solid fa-pen"></i></a>@endcan
                            @can('create', \App\Models\Transfer::class)@if($transfer->purpose === 'transfer' && $transfer->status === 'received')<a href="{{ route('transfers.create', ['return_of' => $transfer->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Retur</a>@endif @endcan
                        </div>
                    </div>
                </article>
            @empty
                <div class="text-center text-muted py-4">Nu exista transferuri pentru filtrele selectate. @if($activeTransferFilters)<a href="{{ route('transfers.index') }}" class="d-block mt-2">Sterge filtrele</a>@endif</div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $transfers->links() }}</div>
    </div>
    </div>
</div>
@endsection
