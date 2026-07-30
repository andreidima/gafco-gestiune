@extends('layouts.app')

@section('title', 'Situatie soferi')

@section('content')
@php
    $stateVariants = [
        'free' => 'success',
        'soon' => 'info',
        'pending' => 'warning',
        'busy' => 'secondary',
        'overdue' => 'danger',
    ];
    $freeDrivers = $driverSummaries->where('state', 'free')->count();
    $soonDrivers = $driverSummaries->where('state', 'soon')->count();
    $pendingDrivers = $driverSummaries->where('state', 'pending')->count();
    $busyDrivers = $driverSummaries->whereIn('state', ['busy', 'overdue'])->count();
@endphp

<div class="resource-shell">
    <x-resource-page-header
        title="Situatie soferi"
        description="Soferii liberi si cei care se elibereaza curand apar primii. Alocarea se trimite spre acceptare."
        :count="$driverSummaries->count()"
        icon="fa-users-viewfinder"
        :create-route="route('tasks.create')"
        create-label="Sarcina noua"
    >
        <x-slot:actions>
            <x-live-view view-key="tasks-dispatch" />
            <a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-list-check me-1"></i>Toate sarcinile</a>
        </x-slot:actions>
    </x-resource-page-header>

    <div class="action-queue-grid mb-3" aria-label="Rezumat disponibilitate">
        <div class="action-queue-card accent-forest">
            <span class="action-queue-icon"><i class="fa-solid fa-circle-check"></i></span>
            <span class="action-queue-content"><strong>{{ $freeDrivers }}</strong><span>Liberi acum</span></span>
        </div>
        <div class="action-queue-card accent-teal">
            <span class="action-queue-icon"><i class="fa-solid fa-clock"></i></span>
            <span class="action-queue-content"><strong>{{ $soonDrivers }}</strong><span>Liberi in urmatoarele 4 ore</span></span>
        </div>
        <div class="action-queue-card accent-amber">
            <span class="action-queue-icon"><i class="fa-solid fa-hourglass-half"></i></span>
            <span class="action-queue-content"><strong>{{ $pendingDrivers }}</strong><span>Asteapta raspunsul</span></span>
        </div>
        <div class="action-queue-card accent-rose">
            <span class="action-queue-icon"><i class="fa-solid fa-truck-fast"></i></span>
            <span class="action-queue-content"><strong>{{ $busyDrivers }}</strong><span>Ocupati</span></span>
        </div>
        <a class="action-queue-card accent-slate text-decoration-none" href="#unassigned-tasks">
            <span class="action-queue-icon"><i class="fa-solid fa-inbox"></i></span>
            <span class="action-queue-content"><strong>{{ $unassignedTotal }}</strong><span>Sarcini de alocat</span></span>
        </a>
    </div>

    <div class="dispatch-driver-grid mb-4">
        @forelse($driverSummaries as $summary)
            @php
                $driver = $summary['driver'];
                $task = $summary['currentTask'];
                $assignment = $summary['currentAssignment'];
            @endphp
            <article class="dispatch-driver-card dispatch-driver-card-{{ $summary['state'] }}">
                <div class="dispatch-driver-card-header">
                    <div class="min-w-0">
                        <div class="resource-primary text-truncate">{{ $driver->name }}</div>
                        @if($driver->phone)<div class="resource-secondary"><i class="fa-solid fa-phone me-1"></i>{{ $driver->phone }}</div>@endif
                    </div>
                    <span class="badge text-bg-{{ $stateVariants[$summary['state']] ?? 'secondary' }}">{{ $summary['stateLabel'] }}</span>
                </div>

                @if($task)
                    <div class="dispatch-driver-current">
                        <a href="{{ route('tasks.show', $task) }}" class="resource-code text-decoration-none">{{ $task->number }}</a>
                        <div class="resource-primary text-truncate">{{ $task->title }}</div>
                        <div class="dispatch-driver-route">
                            <i class="fa-solid fa-route me-1 text-muted"></i>
                            {{ $task->sourceLocation?->code ?? 'Nespecificat' }} <span aria-hidden="true">&rarr;</span> {{ $task->destinationLocation?->code ?? 'Nespecificat' }}
                        </div>
                    </div>
                    <div class="dispatch-driver-card-meta">
                        @if($task->manager_deadline)
                            <span class="{{ $task->isOverdue() ? 'deadline-overdue fw-bold' : '' }}"><i class="fa-solid fa-flag-checkered me-1"></i>Manager: {{ $task->manager_deadline->format('d.m H:i') }}</span>
                        @endif
                        @if($assignment?->driver_estimate_at)
                            <span><i class="fa-solid fa-user-clock me-1"></i>Estimare: {{ $assignment->driver_estimate_at->format('d.m H:i') }}</span>
                        @elseif(in_array($summary['state'], ['busy', 'overdue', 'soon'], true))
                            <span class="text-warning-emphasis"><i class="fa-solid fa-circle-exclamation me-1"></i>Estimare necomunicata</span>
                        @endif
                    </div>
                @else
                    <div class="dispatch-driver-availability">
                        <i class="fa-solid fa-circle-check me-1"></i>Poate primi imediat o sarcina.
                    </div>
                @endif

                <div class="dispatch-driver-footer">
                    <span><i class="fa-solid fa-layer-group me-1"></i>{{ $summary['queueCount'] }} {{ $summary['queueCount'] === 1 ? 'sarcina in asteptare' : 'sarcini in asteptare' }}</span>
                    @if($task)<a href="{{ route('tasks.show', $task) }}" class="btn btn-sm btn-outline-primary">Deschide</a>@else<a href="#unassigned-tasks" class="btn btn-sm btn-success">Aloca sarcina</a>@endif
                </div>
            </article>
        @empty
            <div class="resource-table-card p-4 text-center text-muted">Nu exista soferi activi.</div>
        @endforelse
    </div>

    <section id="unassigned-tasks" aria-labelledby="unassigned-heading">
        <form class="resource-filter-panel" data-auto-submit-filters>
            <input type="hidden" name="filters_submitted" value="1">
            <div class="row g-2 align-items-end">
                <div class="col-md-7 col-xl-5">
                    <label class="resource-filter-label" for="dispatch-search">Cautare sarcina</label>
                    <input id="dispatch-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Numar sau titlu" autocomplete="off">
                </div>
                <div class="col-md-3 col-xl-3 pb-1">
                    <div class="form-check">
                        <input name="overdue" value="1" type="checkbox" class="form-check-input" id="dispatch-overdue" @checked(request()->boolean('overdue'))>
                        <label class="form-check-label small" for="dispatch-overdue">Doar intarziate</label>
                    </div>
                </div>
                <div class="col-md-2 col-xl-2 d-flex gap-2">
                    <button class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtreaza</button>
                    <a href="{{ route('tasks.dispatch', ['filters_reset' => 1]) }}#unassigned-tasks" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a>
                </div>
                <div class="col-12">
                    <span class="resource-filter-memory"><i class="fa-solid fa-bookmark me-1"></i>Filtrul „Doar întârziate” se salvează în cont; căutarea scrisă nu se memorează.</span>
                </div>
            </div>
        </form>

        <div class="resource-results-meta mb-2">
            <h2 id="unassigned-heading" class="h6 mb-0">Sarcini nealocate</h2>
            <span>{{ $unassignedTasks->total() }} {{ $unassignedTasks->total() === 1 ? 'rezultat' : 'rezultate' }}</span>
        </div>

        <div class="resource-table-card d-none d-lg-block">
            <div class="table-responsive">
                <table class="table resource-table">
                    <thead><tr><th>Sarcina</th><th>Traseu</th><th>Deadline</th><th>Aloca sofer</th><th class="text-end">Actiuni</th></tr></thead>
                    <tbody>
                    @forelse($unassignedTasks as $task)
                        @php
                            $driverOptions = $driverOptionsByTask->get($task->id, collect());
                            $suggestedDrivers = $driverOptions->where('sameRoute')->take(2);
                        @endphp
                        <tr>
                            <td><div class="resource-cell-stack"><a href="{{ route('tasks.show', $task) }}" class="resource-primary text-decoration-none">{{ $task->title }}</a><span class="resource-code">{{ $task->number }}</span>@if(in_array($task->priority, ['high', 'urgent'], true))<span><span class="badge text-bg-warning">{{ $task->priority === 'urgent' ? 'Urgenta' : 'Ridicata' }}</span></span>@endif</div></td>
                            <td>
                                <div class="resource-cell-stack">
                                    <span class="resource-primary">{{ $task->sourceLocation?->code ?? '-' }} <span aria-hidden="true">&rarr;</span> {{ $task->destinationLocation?->code ?? '-' }}</span>
                                    @if($task->sourceLocation || $task->destinationLocation)<span class="resource-secondary">{{ $task->sourceLocation?->name ?? 'Sursa nespecificata' }} / {{ $task->destinationLocation?->name ?? 'Destinatie nespecificata' }}</span>@endif
                                    @foreach($suggestedDrivers as $suggestion)
                                        <span class="dispatch-route-suggestion"><i class="fa-solid fa-route me-1"></i>{{ $suggestion['driver']->name }} are deja {{ $suggestion['sameRouteTaskNumber'] }} pe aceeași rută</span>
                                    @endforeach
                                </div>
                            </td>
                            <td><div class="resource-cell-stack">@if($task->manager_deadline)<span class="{{ $task->isOverdue() ? 'deadline-overdue fw-bold' : '' }}">{{ $task->manager_deadline->format('d.m.Y H:i') }}</span><span class="resource-secondary">{{ $task->manager_deadline->diffForHumans() }}</span>@else<span class="text-muted">Nespecificat</span>@endif</div></td>
                            <td>
                                <form method="post" action="{{ route('tasks.assignments.store', $task) }}" class="dispatch-assignment-form">
                                    @csrf
                                    <select name="driver_id" class="form-select form-select-sm" data-tom-select required aria-label="Alege sofer pentru {{ $task->number }}">
                                        <option value="">Alege soferul</option>
                                        @foreach($driverOptions as $summary)
                                            <option value="{{ $summary['driver']->id }}" data-search="{{ $summary['driver']->login_code }}">
                                                {{ $summary['sameRoute'] ? 'Recomandat · ' : '' }}{{ $summary['driver']->name }} - {{ $summary['sameRoute'] ? 'aceeași rută · ' : '' }}{{ $summary['stateLabel'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-success"><i class="fa-solid fa-paper-plane me-1"></i>Trimite</button>
                                </form>
                            </td>
                            <td><div class="resource-row-actions"><x-resource-icon-button :href="route('tasks.show', $task)" icon="fa-eye" label="Deschide sarcina" /></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Nu exista sarcini nealocate pentru filtrele selectate.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dispatch-task-list d-lg-none">
            @forelse($unassignedTasks as $task)
                @php
                    $driverOptions = $driverOptionsByTask->get($task->id, collect());
                    $suggestedDrivers = $driverOptions->where('sameRoute')->take(2);
                @endphp
                <article class="dispatch-task-card">
                    <div class="d-flex justify-content-between gap-2 align-items-start">
                        <div><a href="{{ route('tasks.show', $task) }}" class="resource-primary text-decoration-none">{{ $task->title }}</a><div class="resource-code">{{ $task->number }}</div></div>
                        @if($task->isOverdue())<span class="badge text-bg-danger">Intarziata</span>@endif
                    </div>
                    <div class="dispatch-driver-route mt-2">{{ $task->sourceLocation?->code ?? '-' }} <span aria-hidden="true">&rarr;</span> {{ $task->destinationLocation?->code ?? '-' }}</div>
                    @foreach($suggestedDrivers as $suggestion)
                        <div class="dispatch-route-suggestion mt-2"><i class="fa-solid fa-route me-1"></i>{{ $suggestion['driver']->name }} are deja {{ $suggestion['sameRouteTaskNumber'] }} pe această rută</div>
                    @endforeach
                    @if($task->manager_deadline)<div class="small mt-2 {{ $task->isOverdue() ? 'deadline-overdue fw-bold' : 'text-muted' }}"><i class="fa-solid fa-flag-checkered me-1"></i>{{ $task->manager_deadline->format('d.m.Y H:i') }} ({{ $task->manager_deadline->diffForHumans() }})</div>@endif
                    <form method="post" action="{{ route('tasks.assignments.store', $task) }}" class="dispatch-assignment-form mt-3">
                        @csrf
                        <select name="driver_id" class="form-select" data-tom-select required aria-label="Alege sofer pentru {{ $task->number }}">
                            <option value="">Alege soferul</option>
                            @foreach($driverOptions as $summary)
                                <option value="{{ $summary['driver']->id }}" data-search="{{ $summary['driver']->login_code }}">
                                    {{ $summary['sameRoute'] ? 'Recomandat · ' : '' }}{{ $summary['driver']->name }} - {{ $summary['sameRoute'] ? 'aceeași rută · ' : '' }}{{ $summary['stateLabel'] }}
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-success"><i class="fa-solid fa-paper-plane me-1"></i>Trimite</button>
                    </form>
                </article>
            @empty
                <div class="resource-table-card p-4 text-center text-muted">Nu exista sarcini nealocate pentru filtrele selectate.</div>
            @endforelse
        </div>

        @if($unassignedTasks->hasPages())<div class="resource-table-footer mt-2">{{ $unassignedTasks->links() }}</div>@endif
    </section>
</div>
@endsection
