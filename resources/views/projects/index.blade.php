@extends('layouts.app')

@section('title', 'Proiecte materiale')

@section('content')
@php
    $formatQuantity = static fn (float $quantity): string => \App\Support\LocalizedNumber::quantity($quantity);
    $hasFilters = request()->filled('search') || request()->filled('status') || request()->filled('location_id');
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Proiecte și planuri de materiale"
        description="Cantitățile planificate pentru fiecare locație, comparate cu transferurile solicitate."
        :count="$totalProjects"
        :filtered-count="$projects->total()"
        icon="fa-diagram-project"
        :create-route="Gate::allows('create', \App\Models\Project::class) ? route('projects.create') : null"
        create-label="Proiect nou"
    >
        <x-slot:actions><x-live-view view-key="projects-index" /></x-slot:actions>
    </x-resource-page-header>

    <form class="resource-filter-panel" data-auto-submit-filters data-live-filter-target="#projects-results">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-4 col-md-6">
                <label class="resource-filter-label">Căutare</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cod sau denumire proiect">
            </div>
            <div class="col-xl-2 col-md-3">
                <label class="resource-filter-label">Stare</label>
                <select name="status" class="form-select">
                    <option value="">Toate</option>
                    @foreach(\App\Models\Project::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-4 col-md-6">
                <label class="resource-filter-label">Locație</label>
                <select name="location_id" class="form-select" data-tom-select>
                    <option value="">Toate locațiile</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) request('location_id') === $location->id)>
                            {{ $location->code }} — {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-3 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrează</button>
                <a href="{{ route('projects.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Resetează filtrele">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </div>
    </form>

    <div id="projects-results" class="resource-table-card" data-live-filter-results>
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table mb-0 align-middle">
                <thead><tr><th>Proiect</th><th>Locație</th><th>Plan și utilizare</th><th>Depășiri</th><th>Stare</th><th class="text-end">Acțiuni</th></tr></thead>
                <tbody>
                @forelse($projects as $project)
                    @php
                        $progress = $progressByProject->get($project->id, collect());
                        $overruns = $progress->where('has_overrun', true);
                        $usedMaterials = $progress->where('committed_quantity', '>', 0);
                    @endphp
                    <tr class="{{ $overruns->isNotEmpty() ? 'resource-row-alert resource-row-alert-danger' : '' }}">
                        <td>
                            <div class="resource-cell-stack">
                                <a href="{{ route('projects.show', $project) }}" class="resource-primary text-decoration-none">{{ $project->name }}</a>
                                <span class="resource-code">{{ $project->code }}</span>
                                <span class="resource-secondary">Creat de {{ $project->creator?->name ?? 'utilizator indisponibil' }}</span>
                            </div>
                        </td>
                        <td><strong>{{ $project->location?->code }}</strong><span class="resource-secondary">{{ $project->location?->name }}</span></td>
                        <td>
                            <strong>{{ $project->material_plans_count }} materiale planificate</strong>
                            <span class="resource-secondary">{{ $project->active_transfers_count }} transferuri luate în calcul · {{ $usedMaterials->count() }} materiale solicitate</span>
                        </td>
                        <td>
                            @if($overruns->isNotEmpty())
                                <span class="badge text-bg-danger">{{ $overruns->count() }} {{ $overruns->count() === 1 ? 'material depășit' : 'materiale depășite' }}</span>
                                <span class="resource-secondary">{{ $overruns->take(2)->map(fn ($line) => $line['catalog_item']->name.' +'.$formatQuantity($line['overrun_quantity']).' '.$line['unit'])->implode(' · ') }}</span>
                            @else
                                <span class="badge text-bg-success">În limitele planului</span>
                            @endif
                        </td>
                        <td><a href="{{ route('projects.show', $project) }}" class="badge status-badge-link text-bg-{{ $project->status === 'active' ? 'success' : ($project->status === 'draft' ? 'warning' : 'secondary') }}">{{ \App\Models\Project::STATUS_LABELS[$project->status] }}<span class="visually-hidden"> — deschide proiectul</span></a></td>
                        <td class="text-end">
                            <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-primary">Deschide</a>
                            @can('update', $project)<a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-outline-secondary">Modifică</a>@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5 text-secondary">{{ $hasFilters ? 'Nu există proiecte pentru filtrele selectate.' : 'Nu există încă proiecte cu planuri de materiale.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($projects as $project)
                @php
                    $progress = $progressByProject->get($project->id, collect());
                    $overruns = $progress->where('has_overrun', true);
                @endphp
                <article class="card resource-mobile-card {{ $overruns->isNotEmpty() ? 'resource-row-alert resource-row-alert-danger' : '' }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div><span class="resource-code">{{ $project->code }}</span><h2 class="resource-mobile-card-title">{{ $project->name }}</h2></div>
                            <a href="{{ route('projects.show', $project) }}" class="badge status-badge-link text-bg-{{ $project->status === 'active' ? 'success' : ($project->status === 'draft' ? 'warning' : 'secondary') }}">{{ \App\Models\Project::STATUS_LABELS[$project->status] }}<span class="visually-hidden"> — deschide proiectul</span></a>
                        </div>
                        <div class="resource-mobile-card-grid">
                            <div><span class="resource-filter-label">Locație</span><strong>{{ $project->location?->code }}</strong><span class="resource-secondary">{{ $project->location?->name }}</span></div>
                            <div><span class="resource-filter-label">Plan</span><strong>{{ $project->material_plans_count }} materiale</strong><span class="resource-secondary">{{ $project->active_transfers_count }} transferuri</span></div>
                        </div>
                        <div class="mt-3">
                            @if($overruns->isNotEmpty())<span class="badge text-bg-danger">{{ $overruns->count() }} depășiri</span>@else<span class="badge text-bg-success">În limite</span>@endif
                        </div>
                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-primary btn-sm">Deschide</a>
                            @can('update', $project)<a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-secondary btn-sm">Modifică</a>@endcan
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">{{ $hasFilters ? 'Nu există proiecte pentru filtrele selectate.' : 'Nu există încă proiecte.' }}</div>
            @endforelse
        </div>
        <div class="resource-table-footer">{{ $projects->links() }}</div>
    </div>
</div>
@endsection
