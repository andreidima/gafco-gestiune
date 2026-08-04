@extends('layouts.app')

@section('title', 'Sarcini soferi')

@section('content')
@php
    $taskStatusLabels = ['unassigned' => 'Nealocat', 'pending_acceptance' => 'Asteapta soferul', 'accepted' => 'Acceptat', 'in_progress' => 'In lucru', 'completed' => 'Finalizat', 'rejected' => 'Refuzat', 'cancelled' => 'Anulat', 'archived' => 'Arhivat'];
    $priorityLabels = ['low' => 'Scazuta', 'normal' => 'Normala', 'high' => 'Ridicata', 'urgent' => 'Urgenta'];
    $categoryLabels = ['general' => 'Generala', 'transport' => 'Transport', 'documente' => 'Documente', 'aprovizionare' => 'Aprovizionare', 'altele' => 'Altele'];
    $assignmentLabels = ['pending' => 'Asteapta raspuns', 'accepted' => 'Acceptata', 'reassignment_requested' => 'Realocare solicitata'];
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

    $activeTaskFilters = [];
    if (request()->filled('search')) {
        $activeTaskFilters['search'] = 'Cautare: „'.request('search').'”';
    }
    if (request()->filled('status')) {
        $activeTaskFilters['status'] = 'Status: '.($taskStatusLabels[request('status')] ?? request('status'));
    }
    if (request()->filled('priority')) {
        $activeTaskFilters['priority'] = 'Prioritate: '.($priorityLabels[request('priority')] ?? request('priority'));
    }
    if (! $isDriver && request()->filled('driver_id')) {
        $activeTaskFilters['driver_id'] = 'Sofer: '.($drivers->firstWhere('id', (int) request('driver_id'))?->name ?? '#'.request('driver_id'));
    }
    if (request()->filled('location_id')) {
        $selectedLocation = $locations->firstWhere('id', (int) request('location_id'));
        $activeTaskFilters['location_id'] = 'Locatie: '.($selectedLocation?->code ?? '#'.request('location_id'));
    }
    if (request()->boolean('overdue')) {
        $activeTaskFilters['overdue'] = 'Doar intarziate';
    }
    if (request()->boolean('archived')) {
        $activeTaskFilters['archived'] = 'Include arhivate';
    }
    $advancedTaskFilterCount = count($activeTaskFilters) - (isset($activeTaskFilters['search']) ? 1 : 0);
@endphp

<div class="resource-shell {{ $isDriver ? 'driver-task-index' : '' }}">
    @can('create', \App\Models\Task::class)
        <x-resource-page-header
            title="Sarcini soferi"
            description="Alocari, acceptari, deadline-uri si estimarile comunicate de soferi."
            :count="$totalTasks"
            :filtered-count="$tasks->total()"
            icon="fa-list-check"
            :create-route="route('tasks.create')"
            create-label="Sarcina noua"
        >
            <x-slot:actions>
                <x-live-view view-key="tasks-index" />
                <a href="{{ route('tasks.dispatch') }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-users-viewfinder me-1"></i>Situatie soferi</a>
            </x-slot:actions>
        </x-resource-page-header>
    @else
        <x-resource-page-header title="Sarcinile mele" description="Deadline-uri, estimari si starea sarcinilor care ti-au fost alocate." :count="$totalTasks" :filtered-count="$tasks->total()" icon="fa-list-check">
            <x-slot:actions><x-live-view view-key="tasks-index" /></x-slot:actions>
        </x-resource-page-header>
    @endcan

    @if($isDriver)
        <nav class="driver-task-tabs" aria-label="Starea sarcinilor mele" data-live-filter-summary>
            <a class="driver-task-tab {{ ! request()->filled('status') && ! request()->boolean('overdue') ? 'active' : '' }}" href="{{ route('tasks.index', ['filters_reset' => 1]) }}">
                <span>Active</span><span class="driver-task-tab-count">{{ $driverTaskCounts['active'] }}</span>
            </a>
            <a class="driver-task-tab {{ request('status') === 'pending_acceptance' ? 'active' : '' }}" href="{{ route('tasks.index', ['status' => 'pending_acceptance', 'filters_submitted' => 1]) }}">
                <span>De răspuns</span><span class="driver-task-tab-count">{{ $driverTaskCounts['pending_acceptance'] }}</span>
            </a>
            <a class="driver-task-tab {{ request('status') === 'accepted' ? 'active' : '' }}" href="{{ route('tasks.index', ['status' => 'accepted', 'filters_submitted' => 1]) }}">
                <span>Acceptate</span><span class="driver-task-tab-count">{{ $driverTaskCounts['accepted'] }}</span>
            </a>
            <a class="driver-task-tab {{ request('status') === 'in_progress' ? 'active' : '' }}" href="{{ route('tasks.index', ['status' => 'in_progress', 'filters_submitted' => 1]) }}">
                <span>În lucru</span><span class="driver-task-tab-count">{{ $driverTaskCounts['in_progress'] }}</span>
            </a>
            <a class="driver-task-tab {{ request('status') === 'completed' ? 'active' : '' }}" href="{{ route('tasks.index', ['status' => 'completed', 'filters_submitted' => 1]) }}">
                <span>Finalizate</span><span class="driver-task-tab-count">{{ $driverTaskCounts['completed'] }}</span>
            </a>
        </nav>
    @endif

    <form class="resource-filter-panel" method="get" action="{{ route('tasks.index') }}" data-auto-submit-filters data-live-filter-target="#tasks-results">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="resource-filter-toolbar row g-2 align-items-end">
            <div class="resource-filter-search col">
                <label for="task-search" class="resource-filter-label">Cautare</label>
                <input id="task-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Numar sau titlu">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit" aria-label="Cauta sarcini"><i class="fa-solid fa-magnifying-glass me-sm-1" aria-hidden="true"></i><span class="d-none d-sm-inline">Cauta</span></button>
            </div>
            <div class="col-auto d-md-none">
                <button class="btn btn-outline-secondary resource-filter-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#taskAdvancedFilters" aria-expanded="false" aria-controls="taskAdvancedFilters">
                    <i class="fa-solid fa-sliders me-1"></i>Filtre
                    <span class="badge text-bg-primary ms-1" data-live-filter-summary @if($advancedTaskFilterCount === 0) hidden @endif>{{ $advancedTaskFilterCount }}</span>
                </button>
            </div>
        </div>

        <div class="collapse d-md-block resource-filter-advanced" id="taskAdvancedFilters">
            <div class="row g-2 align-items-end mt-1">
                <div class="col-xl-2 col-md-3"><label class="resource-filter-label">Status</label><select name="status" class="form-select"><option value="">Toate</option>@foreach($taskStatusLabels as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-xl-2 col-md-3"><label class="resource-filter-label">Prioritate</label><select name="priority" class="form-select"><option value="">Toate</option>@foreach($priorityLabels as $priority => $label)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ $label }}</option>@endforeach</select></div>
                @unless($isDriver)
                    <div class="col-xl-3 col-md-6"><label class="resource-filter-label">Sofer</label><select name="driver_id" class="form-select" data-tom-select><option value="">Toti</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" data-search="{{ $driver->login_code }}" @selected((string) request('driver_id') === (string) $driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
                @endunless
                <div class="col-xl-3 col-md-6"><label class="resource-filter-label">Locatie</label><select name="location_id" class="form-select" data-tom-select><option value="">Toate</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-xl-2 col-md-6 d-flex gap-2"><button class="btn btn-primary flex-fill" type="submit"><i class="fa-solid fa-filter me-1"></i>Aplica</button><a href="{{ route('tasks.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a></div>
                <div class="col-12 d-flex flex-wrap gap-3 pt-1">
                    <div class="form-check"><input type="checkbox" name="overdue" value="1" class="form-check-input" id="task-overdue" @checked(request()->boolean('overdue'))><label for="task-overdue" class="form-check-label small">Doar intarziate</label></div>
                    <div class="form-check"><input type="checkbox" name="archived" value="1" class="form-check-input" id="task-archived" @checked(request()->boolean('archived'))><label for="task-archived" class="form-check-label small">Include arhivate</label></div>
                    <span class="resource-filter-memory"><i class="fa-solid fa-bookmark me-1"></i>Selecțiile se salvează în cont; căutarea scrisă nu se memorează.</span>
                </div>
            </div>
        </div>
    </form>

    <div id="tasks-results" data-live-filter-results>
    @unless($isDriver)
        <nav class="resource-filter-presets" aria-label="Vizualizari rapide sarcini">
            <span class="results-meta">Vizualizari rapide</span>
            <a class="resource-filter-preset {{ request('status') === 'unassigned' ? 'active' : '' }}" href="{{ route('tasks.index', ['status' => 'unassigned', 'filters_submitted' => 1]) }}"><i class="fa-solid fa-inbox"></i>Necesita alocare</a>
            <a class="resource-filter-preset {{ request()->boolean('overdue') ? 'active' : '' }}" href="{{ route('tasks.index', ['overdue' => 1, 'filters_submitted' => 1]) }}"><i class="fa-solid fa-triangle-exclamation"></i>Intarziate</a>
            <a class="resource-filter-preset {{ request('status') === 'in_progress' ? 'active' : '' }}" href="{{ route('tasks.index', ['status' => 'in_progress', 'filters_submitted' => 1]) }}"><i class="fa-solid fa-truck-fast"></i>In lucru</a>
        </nav>
    @endunless

    @if($activeTaskFilters)
        <div class="resource-filter-chips mb-2 px-1" aria-label="Filtre active">
            <span class="results-meta">Filtre active:</span>
            @foreach($activeTaskFilters as $filterKey => $filterLabel)
                <a class="filter-chip" href="{{ route('tasks.index', array_merge(request()->except([$filterKey, 'page']), ['filters_submitted' => 1])) }}" title="Elimina filtrul {{ $filterLabel }}">
                    {{ $filterLabel }} <i class="fa-solid fa-xmark ms-1" aria-hidden="true"></i>
                </a>
            @endforeach
            <a class="filter-chip filter-chip-clear" href="{{ route('tasks.index', ['filters_reset' => 1]) }}">Sterge toate</a>
        </div>
    @endif

    <div class="resource-table-card">
        <div class="table-responsive d-none d-md-block">
            <table class="table resource-table">
                <thead><tr><th>Sarcina</th><th>Traseu</th><th>Alocare</th><th>Termene</th><th>Status</th><th class="text-end">Actiuni</th></tr></thead>
                <tbody>
                @forelse($tasks as $task)
                    @php
                        $assignment = $task->currentAssignment;
                        $assignmentBelongsToDriver = ! $isDriver || ! $assignment || $assignment->driver_id === auth()->id();
                        $displayAssignment = $assignmentBelongsToDriver ? $assignment : null;
                        $latestRejection = $task->assignments->sortByDesc('id')->first();
                        $needsDriverResponse = $isDriver && $assignment?->driver_id === auth()->id() && $assignment->status === 'pending';
                        $needsManagerAction = ! $isDriver && ($task->status === 'unassigned' || $assignment?->status === 'reassignment_requested');
                        $isDueSoon = $task->manager_deadline && ! $task->isOverdue() && ! in_array($task->status, ['completed', 'cancelled', 'archived'], true) && $task->manager_deadline->lte(now()->addHours(4));
                        $estimateDelta = $task->manager_deadline && $displayAssignment?->driver_estimate_at
                            ? (int) round(($displayAssignment->driver_estimate_at->getTimestamp() - $task->manager_deadline->getTimestamp()) / 60)
                            : null;
                        $routeNames = collect([$task->sourceLocation?->name, $task->destinationLocation?->name])->filter()->implode(' / ');
                        $rowAlertClass = $task->isOverdue() ? 'resource-row-alert resource-row-alert-danger' : (($needsDriverResponse || $needsManagerAction || $isDueSoon) ? 'resource-row-alert resource-row-alert-warning' : '');
                    @endphp
                    <tr class="{{ $rowAlertClass }}">
                        <td>
                            <div class="resource-cell-stack">
                                <a class="resource-primary text-decoration-none" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                <span class="resource-code">{{ $task->number }}</span>
                                @if($task->category !== 'general' || $task->priority !== 'normal')
                                    <span>
                                        @if($task->category !== 'general')<span class="badge text-bg-light border">{{ $categoryLabels[$task->category] ?? $task->category }}</span>@endif
                                        @if($task->priority !== 'normal')<span class="badge text-bg-{{ in_array($task->priority, ['high', 'urgent'], true) ? 'warning' : 'light' }} border">{{ $priorityLabels[$task->priority] ?? $task->priority }}</span>@endif
                                    </span>
                                @endif
                                @if($needsDriverResponse)<span class="resource-secondary text-warning fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i>Necesita raspunsul tau</span>@endif
                                @if($needsManagerAction)<span class="resource-secondary text-warning fw-bold"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $assignment?->status === 'reassignment_requested' ? 'Necesita realocare' : 'Necesita alocare' }}</span>@endif
                            </div>
                        </td>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $task->sourceLocation?->code ?? 'Nespecificat' }} <i class="fa-solid fa-arrow-right mx-1 text-muted"></i> {{ $task->destinationLocation?->code ?? 'Nespecificat' }}</span>@if($routeNames)<span class="resource-secondary">{{ $routeNames }}</span>@endif</div></td>
                        <td>
                            <div class="resource-cell-stack">
                                @if($isDriver && $assignment && ! $assignmentBelongsToDriver)
                                    <span class="resource-primary">Realocata</span>
                                    <span class="resource-secondary">Nu mai este alocata tie</span>
                                @elseif($isDriver)
                                    <span class="resource-primary">{{ $assignmentLabels[$displayAssignment?->status] ?? 'Sarcina ta' }}</span>
                                @else
                                    <span class="resource-primary">{{ $displayAssignment?->driver?->name ?? 'Nealocat' }}</span>
                                    @if($displayAssignment)<span class="resource-secondary {{ $displayAssignment->status === 'reassignment_requested' ? 'text-warning fw-bold' : '' }}">{{ $assignmentLabels[$displayAssignment->status] ?? $displayAssignment->status }}</span>@endif
                                @endif
                                @if(! $isDriver && ! $displayAssignment && $latestRejection)
                                    <span class="resource-secondary text-danger">Refuzata de {{ $latestRejection->driver?->name ?? 'sofer' }}</span>
                                    @if($latestRejection->response_notes)<span class="resource-secondary" title="{{ $latestRejection->response_notes }}">{{ \Illuminate\Support\Str::limit($latestRejection->response_notes, 55) }}</span>@endif
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="resource-cell-stack">
                                @if($task->manager_deadline)
                                    <span class="resource-deadline-manager {{ $task->isOverdue() ? 'deadline-overdue fw-bold' : '' }}"><i class="fa-solid fa-flag-checkered me-1"></i>{{ $isDriver ? 'Termen' : 'Manager' }}: {{ $task->manager_deadline->format('d.m.Y H:i') }}</span>
                                    @if($task->isOverdue())<span class="resource-deadline-overdue text-danger fw-bold">Intarziata cu {{ ltrim($formatMinuteDelta(max(1, (int) ceil((now()->getTimestamp() - $task->manager_deadline->getTimestamp()) / 60))), '+') }}</span>
                                    @elseif($isDueSoon)<span class="resource-deadline-soon text-warning fw-bold">Expira in {{ ltrim($formatMinuteDelta(max(1, (int) ceil(($task->manager_deadline->getTimestamp() - now()->getTimestamp()) / 60))), '+') }}</span>@endif
                                @endif
                                @if($displayAssignment?->driver_estimate_at)
                                    <span class="resource-secondary"><i class="fa-solid fa-user-clock me-1"></i>{{ $isDriver ? 'Estimarea mea' : 'Sofer' }}: {{ $displayAssignment->driver_estimate_at->format('d.m.Y H:i') }} @if($estimateDelta !== null)<strong class="{{ $estimateDelta > 0 ? 'resource-deadline-late text-danger' : ($estimateDelta < 0 ? 'resource-deadline-early text-success' : '') }}">({{ $formatMinuteDelta($estimateDelta) }})</strong>@endif</span>
                                @elseif($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true))
                                    <span class="resource-secondary {{ $isDriver ? 'text-danger fw-bold' : 'text-warning' }}"><i class="fa-solid {{ $isDriver ? 'fa-triangle-exclamation' : 'fa-user-clock' }} me-1"></i>Estimare necomunicata</span>
                                @endif
                                @if($displayAssignment?->driver_estimate_note)<span class="resource-secondary" title="{{ $displayAssignment->driver_estimate_note }}">{{ \Illuminate\Support\Str::limit($displayAssignment->driver_estimate_note, 55) }}</span>@endif
                                @if(! $task->manager_deadline && ! $displayAssignment?->driver_estimate_at && ! ($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true)))<span class="text-muted">&mdash;</span>@endif
                            </div>
                        </td>
                        <td><x-status :status="$task->status" :href="route('tasks.show', $task)" /></td>
                        <td><div class="resource-row-actions"><x-resource-icon-button :href="route('tasks.show', $task)" icon="fa-eye" label="Deschide sarcina" />@can('update', $task)<x-resource-icon-button :href="route('tasks.edit', $task)" icon="fa-pen" label="Modifica sarcina" variant="outline-secondary" />@endcan</div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Nu exista sarcini pentru filtrele selectate. @if($activeTaskFilters)<a href="{{ route('tasks.index') }}" class="ms-1">Sterge filtrele</a>@endif</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list d-md-none">
            @forelse($tasks as $task)
                @php
                    $assignment = $task->currentAssignment;
                    $assignmentBelongsToDriver = ! $isDriver || ! $assignment || $assignment->driver_id === auth()->id();
                    $displayAssignment = $assignmentBelongsToDriver ? $assignment : null;
                    $latestRejection = $task->assignments->sortByDesc('id')->first();
                    $needsDriverResponse = $isDriver && $assignment?->driver_id === auth()->id() && $assignment->status === 'pending';
                    $needsManagerAction = ! $isDriver && ($task->status === 'unassigned' || $assignment?->status === 'reassignment_requested');
                    $isDueSoon = $task->manager_deadline && ! $task->isOverdue() && ! in_array($task->status, ['completed', 'cancelled', 'archived'], true) && $task->manager_deadline->lte(now()->addHours(4));
                    $estimateDelta = $task->manager_deadline && $displayAssignment?->driver_estimate_at
                        ? (int) round(($displayAssignment->driver_estimate_at->getTimestamp() - $task->manager_deadline->getTimestamp()) / 60)
                        : null;
                    $mobileAlertClass = $task->isOverdue() ? 'resource-row-alert resource-row-alert-danger' : (($needsDriverResponse || $needsManagerAction || $isDueSoon) ? 'resource-row-alert resource-row-alert-warning' : '');
                @endphp
                <article class="card resource-mobile-card {{ $isDriver ? 'driver-task-mobile-card' : '' }} {{ $mobileAlertClass }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0"><a class="resource-primary text-decoration-none" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a><div class="resource-code">{{ $task->number }}</div></div>
                            <x-status :status="$task->status" :href="route('tasks.show', $task)" />
                        </div>

                        @if($needsDriverResponse || $needsManagerAction || $task->isOverdue() || $isDueSoon || $task->priority !== 'normal')
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @if($needsDriverResponse)<span class="badge text-bg-warning"><i class="fa-solid fa-circle-exclamation me-1"></i>Raspunsul tau</span>@endif
                                @if($needsManagerAction)<span class="badge text-bg-warning"><i class="fa-solid fa-circle-exclamation me-1"></i>{{ $assignment?->status === 'reassignment_requested' ? 'Realocare' : 'Alocare necesara' }}</span>@endif
                                @if($task->isOverdue())<span class="badge text-bg-danger">Intarziata</span>@endif
                                @if($isDueSoon)<span class="badge text-bg-warning">Termen apropiat</span>@endif
                                @if($task->priority !== 'normal')<span class="badge text-bg-{{ in_array($task->priority, ['high', 'urgent'], true) ? 'warning' : 'light border' }}">{{ $priorityLabels[$task->priority] ?? $task->priority }}</span>@endif
                            </div>
                        @endif

                        @if($isDriver)
                            <div class="driver-task-card-route">
                                <i class="fa-solid fa-route" aria-hidden="true"></i>
                                <strong>{{ $task->sourceLocation?->code ?? 'Nespecificat' }} <i class="fa-solid fa-arrow-right mx-1 text-muted"></i> {{ $task->destinationLocation?->code ?? 'Nespecificat' }}</strong>
                            </div>
                            <div class="driver-task-card-timing">
                                <div><span>Termen</span><strong class="{{ $task->isOverdue() ? 'deadline-overdue' : '' }}">{{ $task->manager_deadline?->format('d.m.Y H:i') ?? 'Nespecificat' }}</strong></div>
                                <div><span>Estimarea mea</span><strong class="{{ ! $displayAssignment?->driver_estimate_at && $displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true) ? 'text-danger' : '' }}">@if(! $displayAssignment?->driver_estimate_at && $displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true))<i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i>@endif{{ $displayAssignment?->driver_estimate_at?->format('d.m.Y H:i') ?? ($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true) ? 'Necomunicată' : '—') }}</strong></div>
                            </div>
                        @else
                            <div class="resource-mobile-card-grid">
                                <div><span class="resource-filter-label">Traseu</span><strong>{{ $task->sourceLocation?->code ?? 'Nespecificat' }} <i class="fa-solid fa-arrow-right mx-1 text-muted"></i> {{ $task->destinationLocation?->code ?? 'Nespecificat' }}</strong></div>
                                <div>
                                    <span class="resource-filter-label">Alocare</span>
                                    <strong>{{ $displayAssignment?->driver?->name ?? 'Nealocat' }}</strong>@if($displayAssignment)<span class="resource-secondary">{{ $assignmentLabels[$displayAssignment->status] ?? $displayAssignment->status }}</span>@endif
                                    @if(! $displayAssignment && $latestRejection)<span class="resource-secondary text-danger">Refuzata de {{ $latestRejection->driver?->name ?? 'sofer' }}</span>@if($latestRejection->response_notes)<span class="resource-secondary">{{ \Illuminate\Support\Str::limit($latestRejection->response_notes, 70) }}</span>@endif @endif
                                </div>
                                <div class="resource-mobile-card-wide">
                                    <span class="resource-filter-label">Termene</span>
                                    @if($task->manager_deadline)<strong class="{{ $task->isOverdue() ? 'deadline-overdue' : '' }}">Manager: {{ $task->manager_deadline->format('d.m.Y H:i') }}</strong>@if($isDueSoon)<span class="resource-secondary text-warning fw-bold">Expira in {{ ltrim($formatMinuteDelta(max(1, (int) ceil(($task->manager_deadline->getTimestamp() - now()->getTimestamp()) / 60))), '+') }}</span>@endif @endif
                                    @if($displayAssignment?->driver_estimate_at)<span class="resource-secondary">Sofer: {{ $displayAssignment->driver_estimate_at->format('d.m.Y H:i') }} @if($estimateDelta !== null)<strong class="{{ $estimateDelta > 0 ? 'text-danger' : ($estimateDelta < 0 ? 'text-success' : '') }}">({{ $formatMinuteDelta($estimateDelta) }})</strong>@endif</span>
                                    @elseif($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true))<span class="resource-secondary text-warning">Estimare necomunicata</span>@endif
                                    @if($displayAssignment?->driver_estimate_note)<span class="resource-secondary">{{ \Illuminate\Support\Str::limit($displayAssignment->driver_estimate_note, 80) }}</span>@endif
                                    @if(! $task->manager_deadline && ! $displayAssignment?->driver_estimate_at && ! ($displayAssignment && in_array($displayAssignment->status, ['accepted', 'reassignment_requested'], true)))<span class="text-muted">&mdash;</span>@endif
                                </div>
                            </div>
                        @endif

                        @if($isDriver)
                            <x-route-navigation :source="$task->sourceLocation" :destination="$task->destinationLocation" compact class="mt-2" />
                        @endif

                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('tasks.show', $task) }}" class="btn btn-primary btn-sm flex-grow-1">
                                @if($isDriver)
                                    <i class="fa-solid {{ $needsDriverResponse ? 'fa-hand-pointer' : ($task->status === 'accepted' ? 'fa-play' : ($task->status === 'in_progress' ? 'fa-truck-fast' : 'fa-eye')) }} me-1"></i>
                                    {{ $needsDriverResponse ? 'Răspunde' : ($task->status === 'accepted' ? 'Estimează și pornește' : ($task->status === 'in_progress' ? 'Continuă sarcina' : 'Vezi sarcina')) }}
                                @else
                                    <i class="fa-solid fa-eye me-1"></i>Deschide
                                @endif
                            </a>
                            @can('update', $task)<a href="{{ route('tasks.edit', $task) }}" class="btn btn-outline-secondary btn-sm" aria-label="Modifica sarcina"><i class="fa-solid fa-pen"></i></a>@endcan
                        </div>
                    </div>
                </article>
            @empty
                <div class="text-center text-muted py-4">Nu exista sarcini pentru filtrele selectate. @if($activeTaskFilters)<a href="{{ route('tasks.index') }}" class="d-block mt-2">Sterge filtrele</a>@endif</div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $tasks->links() }}</div>
    </div>
    </div>
</div>
@endsection
