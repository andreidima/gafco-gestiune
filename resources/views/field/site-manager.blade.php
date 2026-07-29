@extends('layouts.app')

@section('title', 'Mod sef santier')

@section('content')
@php
    $scopeLabels = [
        'source_manager' => 'Manager sursa',
        'destination_manager' => 'Manager destinatie',
        'driver' => 'Sofer',
    ];
@endphp

<div class="resource-shell">
    <x-resource-page-header
        title="Operatiuni santier"
        description="Aprobarile si intarzierile apar primele, urmate de transferurile active si rapoartele recente."
        :count="$managedLocationsCount"
        icon="fa-user-check"
        :create-route="route('transfers.create')"
        create-label="Transfer nou"
    >
        <x-slot:actions>
            <x-live-view view-key="site-manager-operations" />
            <a href="{{ route('consumption-reports.create') }}" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-clipboard-check me-1"></i>Raporteaza consum</a>
        </x-slot:actions>
    </x-resource-page-header>

    <div class="action-queue-grid mb-4" aria-label="Actiuni prioritare santier">
        <a class="action-queue-card accent-rose text-decoration-none" href="#manager-approvals">
            <span class="action-queue-icon"><i class="fa-solid fa-user-check"></i></span>
            <span class="action-queue-content"><strong>{{ $pendingApprovalsCount }}</strong><span class="action-queue-title">Necesita decizia mea</span><small>Aprobari pe revizia curenta.</small></span>
            <i class="fa-solid fa-arrow-right action-queue-arrow" aria-hidden="true"></i>
        </a>
        <a class="action-queue-card accent-danger text-decoration-none" href="{{ route('tasks.index', ['overdue' => 1]) }}">
            <span class="action-queue-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
            <span class="action-queue-content"><strong>{{ $overdueTasksCount }}</strong><span class="action-queue-title">Sarcini intarziate</span><small>Termen oficial depasit.</small></span>
            <i class="fa-solid fa-arrow-right action-queue-arrow" aria-hidden="true"></i>
        </a>
        <a class="action-queue-card accent-amber text-decoration-none" href="#active-transfers">
            <span class="action-queue-icon"><i class="fa-solid fa-right-left"></i></span>
            <span class="action-queue-content"><strong>{{ $activeTransfersCount }}</strong><span class="action-queue-title">Transferuri active</span><small>Aprobari, pregatire sau tranzit.</small></span>
            <i class="fa-solid fa-arrow-right action-queue-arrow" aria-hidden="true"></i>
        </a>
        <a class="action-queue-card accent-teal text-decoration-none" href="{{ route('consumption-reports.index') }}">
            <span class="action-queue-icon"><i class="fa-solid fa-clipboard-check"></i></span>
            <span class="action-queue-content"><strong>{{ $consumptionThisMonthCount }}</strong><span class="action-queue-title">Consumuri in 30 zile</span><small>Rapoarte din locatiile tale.</small></span>
            <i class="fa-solid fa-arrow-right action-queue-arrow" aria-hidden="true"></i>
        </a>
    </div>

    <div class="site-manager-quick-actions mb-4" aria-label="Actiuni rapide">
        <a href="{{ route('consumption-reports.create') }}" class="site-manager-quick-action"><i class="fa-solid fa-clipboard-check"></i><span><strong>Inregistreaza consum</strong><small>Material si cantitate</small></span></a>
        <a href="{{ route('transfers.create', ['purpose' => 'return']) }}" class="site-manager-quick-action"><i class="fa-solid fa-rotate-left"></i><span><strong>Initiaza retur</strong><small>Santier catre baza</small></span></a>
        <a href="{{ route('tasks.create') }}" class="site-manager-quick-action"><i class="fa-solid fa-list-check"></i><span><strong>Creeaza sarcina</strong><small>Deadline si sofer</small></span></a>
        <a href="{{ route('qr-scan.index') }}" class="site-manager-quick-action"><i class="fa-solid fa-qrcode"></i><span><strong>Scaneaza QR</strong><small>Gaseste echipamentul</small></span></a>
    </div>

    <section id="manager-approvals" class="mb-4" aria-labelledby="manager-approvals-heading">
        <div class="resource-results-meta mb-2">
            <div><h2 id="manager-approvals-heading" class="h6 mb-0">Deciziile mele</h2><span>Oricare manager activ al locatiei poate indeplini aprobarea.</span></div>
            <span>{{ $pendingApprovalsCount }} in asteptare</span>
        </div>

        <div class="site-manager-approval-list">
            @forelse($pendingApprovals as $approval)
                @php
                    $approvalTransfer = $approval->transfer;
                @endphp
                <article class="site-manager-approval-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <a href="{{ route('transfers.show', $approvalTransfer) }}" class="resource-code text-decoration-none">{{ $approvalTransfer->number }}</a>
                            <div class="resource-primary">{{ $approvalTransfer->sourceLocation?->code ?? '-' }} <span aria-hidden="true">&rarr;</span> {{ $approvalTransfer->destinationLocation?->code ?? '-' }}</div>
                        </div>
                        <span class="badge text-bg-warning">Asteapta decizia</span>
                    </div>
                    <div class="site-manager-approval-meta">
                        <span><i class="fa-solid fa-user-check me-1"></i>{{ $scopeLabels[$approval->scope] ?? $approval->scope }}</span>
                        @if($approval->location)<span><i class="fa-solid fa-location-dot me-1"></i>{{ $approval->location->code }} - {{ $approval->location->name }}</span>@endif
                        <span><i class="fa-solid fa-code-branch me-1"></i>Revizia {{ $approval->revision }}</span>
                    </div>
                    <a href="{{ route('transfers.show', $approvalTransfer) }}" class="btn btn-sm btn-primary align-self-start">Deschide pentru decizie <i class="fa-solid fa-arrow-right ms-1"></i></a>
                </article>
            @empty
                <div class="resource-table-card p-4 text-center text-muted"><i class="fa-solid fa-circle-check text-success me-1"></i>Nu ai aprobari in asteptare.</div>
            @endforelse
        </div>
    </section>

    <section id="active-transfers" aria-labelledby="active-transfers-heading">
        <form class="resource-filter-panel" data-auto-submit-filters>
            <input type="hidden" name="filters_submitted" value="1">
            <div class="row g-2 align-items-end">
                <div class="col-md-6 col-xl-4"><label class="resource-filter-label" for="transfer-search">Cautare</label><input id="transfer-search" name="transfer_search" value="{{ request('transfer_search') }}" class="form-control" placeholder="Transfer sau aviz"></div>
                <div class="col-md-4 col-xl-3"><label class="resource-filter-label" for="transfer-status">Status</label><select id="transfer-status" name="transfer_status" class="form-select"><option value="">Toate starile active</option><option value="pending_approval" @selected(request('transfer_status') === 'pending_approval')>Asteapta aprobari</option><option value="approved" @selected(request('transfer_status') === 'approved')>Aprobat</option><option value="in_transit" @selected(request('transfer_status') === 'in_transit')>In tranzit</option></select></div>
                <div class="col-md-2 col-xl-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtreaza</button><a href="{{ route('field.site-manager', ['filters_reset' => 1]) }}#active-transfers" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a></div>
            </div>
        </form>

        <div class="resource-results-meta mb-2">
            <h2 id="active-transfers-heading" class="h6 mb-0">Transferuri active</h2>
            <span>{{ $pendingTransfers->count() }} afisate</span>
        </div>

        <div class="resource-table-card d-none d-lg-block">
            <div class="table-responsive">
                <table class="table resource-table">
                    <thead><tr><th>Transfer</th><th>Traseu si continut</th><th>Termen</th><th>Aprobari</th><th>Status</th><th class="text-end">Actiune</th></tr></thead>
                    <tbody>
                    @forelse($pendingTransfers as $transfer)
                        @php
                            $approvals = $transfer->approvals->where('revision', $transfer->revision);
                            $firstLine = $transfer->lines->first();
                            $firstItem = $firstLine?->trackedAsset?->asset_code ?? $firstLine?->catalogItem?->name;
                        @endphp
                        <tr>
                            <td><div class="resource-cell-stack"><a href="{{ route('transfers.show', $transfer) }}" class="resource-code text-decoration-none">{{ $transfer->number }}</a><span class="resource-secondary">Revizia {{ $transfer->revision }}{{ $transfer->document_number ? ' · '.$transfer->document_number : '' }}</span></div></td>
                            <td><div class="resource-cell-stack"><span class="resource-primary">{{ $transfer->sourceLocation?->code ?? '-' }} <span aria-hidden="true">&rarr;</span> {{ $transfer->destinationLocation?->code ?? '-' }}</span>@if($firstItem)<span class="resource-secondary">{{ $firstItem }}@if($transfer->lines->count() > 1) +{{ $transfer->lines->count() - 1 }} pozitii @endif</span>@endif</div></td>
                            <td><div class="resource-cell-stack">@if($transfer->task?->manager_deadline)<span class="{{ $transfer->task->isOverdue() ? 'deadline-overdue fw-bold' : '' }}">{{ $transfer->task->manager_deadline->format('d.m.Y H:i') }}</span><span class="resource-secondary">{{ $transfer->task->manager_deadline->diffForHumans() }}</span>@else<span class="text-muted">Nespecificat</span>@endif</div></td>
                            <td><div class="resource-cell-stack"><span><strong class="text-success">{{ $approvals->where('status', 'approved')->count() }}</strong> / {{ $approvals->count() }}</span>@if($approvals->where('status', 'pending')->isNotEmpty())<span class="resource-secondary">{{ $approvals->where('status', 'pending')->count() }} in asteptare</span>@else<span class="resource-secondary text-success">Circuit complet</span>@endif</div></td>
                            <td><x-status :status="$transfer->status" /></td>
                            <td><div class="resource-row-actions"><x-resource-icon-button :href="route('transfers.show', $transfer)" icon="fa-eye" label="Deschide fluxul" /></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Nu exista transferuri pentru filtrele selectate.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="site-manager-transfer-list d-lg-none">
            @forelse($pendingTransfers as $transfer)
                @php
                    $approvals = $transfer->approvals->where('revision', $transfer->revision);
                @endphp
                <article class="dispatch-task-card">
                    <div class="d-flex justify-content-between align-items-start gap-2"><div><a href="{{ route('transfers.show', $transfer) }}" class="resource-code text-decoration-none">{{ $transfer->number }}</a><div class="resource-primary mt-1">{{ $transfer->sourceLocation?->code ?? '-' }} <span aria-hidden="true">&rarr;</span> {{ $transfer->destinationLocation?->code ?? '-' }}</div></div><x-status :status="$transfer->status" /></div>
                    <div class="d-flex flex-wrap gap-3 small text-muted mt-2"><span><i class="fa-solid fa-list-check me-1"></i>{{ $transfer->lines->count() }} pozitii</span><span><i class="fa-solid fa-user-check me-1"></i>{{ $approvals->where('status', 'approved')->count() }}/{{ $approvals->count() }} aprobari</span></div>
                    @if($transfer->task?->manager_deadline)<div class="small mt-2 {{ $transfer->task->isOverdue() ? 'deadline-overdue fw-bold' : 'text-muted' }}"><i class="fa-solid fa-flag-checkered me-1"></i>{{ $transfer->task->manager_deadline->format('d.m.Y H:i') }} ({{ $transfer->task->manager_deadline->diffForHumans() }})</div>@endif
                    <a href="{{ route('transfers.show', $transfer) }}" class="btn btn-sm btn-outline-primary mt-3">Deschide fluxul</a>
                </article>
            @empty
                <div class="resource-table-card p-4 text-center text-muted">Nu exista transferuri pentru filtrele selectate.</div>
            @endforelse
        </div>
    </section>

    <div class="card dashboard-chart-card mt-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2"><strong><i class="fa-solid fa-clock-rotate-left me-1"></i>Ultimele consumuri</strong><a href="{{ route('consumption-reports.index') }}" class="btn btn-sm btn-outline-secondary">Vezi toate</a></div>
        <div class="card-body vstack gap-2">
            @forelse($recentConsumption as $report)
                <div class="field-line"><span><strong>{{ $report->location?->code }}</strong> / {{ $report->number }}@if($report->lines->first()?->catalogItem)<small class="d-block text-muted">{{ $report->lines->first()->catalogItem->name }}{{ $report->lines->count() > 1 ? ' +'.($report->lines->count() - 1) : '' }}</small>@endif</span><span class="text-muted">{{ optional($report->reported_at)->format('d.m H:i') }}</span></div>
            @empty
                <div class="text-muted">Nu exista consumuri pentru locatiile tale.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
