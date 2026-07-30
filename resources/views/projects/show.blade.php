@extends('layouts.app')

@section('title', $project->code)

@section('content')
@php
    $formatQuantity = static fn (float $quantity): string => \App\Support\LocalizedNumber::quantity($quantity);
    $overruns = $progress->where('has_overrun', true);
    $usedMaterials = $progress->where('committed_quantity', '>', 0);
@endphp
<div class="resource-shell">
    <x-resource-page-header
        :title="$project->name"
        :description="$project->code.' · '.$project->location?->code.' — '.$project->location?->name"
        icon="fa-diagram-project"
    >
        <x-slot:actions>
            <x-live-view :view-key="'project-'.$project->id" />
            @if($project->status === 'active')
                @can('create', \App\Models\Transfer::class)
                    <a href="{{ route('transfers.create', ['project_id' => $project->id, 'destination_location_id' => $project->location_id]) }}" class="btn btn-success btn-sm"><i class="fa-solid fa-right-left me-1"></i>Transfer pentru proiect</a>
                @endcan
            @endif
            @can('update', $project)<a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-primary btn-sm">Modifică planul</a>@endcan
            <x-back-link :fallback="route('projects.index')" class="btn-sm" />
        </x-slot:actions>
    </x-resource-page-header>

    @if($overruns->isNotEmpty())
        <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="fa-solid fa-triangle-exclamation mt-1"></i>
            <div><strong>Planul este depășit pentru {{ $overruns->count() }} {{ $overruns->count() === 1 ? 'material' : 'materiale' }}.</strong> Transferurile rămân înregistrate, iar responsabilii primesc alerta pentru verificare.</div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="project-summary-card"><span>Stare</span><strong>{{ \App\Models\Project::STATUS_LABELS[$project->status] }}</strong></div></div>
        <div class="col-md-3"><div class="project-summary-card"><span>Materiale planificate</span><strong>{{ $project->materialPlans->count() }}</strong></div></div>
        <div class="col-md-3"><div class="project-summary-card"><span>Materiale solicitate</span><strong>{{ $usedMaterials->count() }}</strong></div></div>
        <div class="col-md-3"><div class="project-summary-card {{ $overruns->isNotEmpty() ? 'project-summary-danger' : '' }}"><span>Depășiri</span><strong>{{ $overruns->count() }}</strong></div></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-9">
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2"><strong>Plan și cantități solicitate</strong><span class="small text-muted">Sunt incluse transferurile neanulate legate de proiect.</span></div>
                <div class="table-responsive d-none d-md-block">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Material</th><th class="text-end">Planificat</th><th class="text-end">Solicitat</th><th class="text-end">Rămas / depășit</th><th style="min-width:180px">Progres</th></tr></thead>
                        <tbody>
                        @foreach($progress as $line)
                            <tr id="material-plan-{{ $line['catalog_item']->id }}" class="{{ $line['has_overrun'] ? 'table-danger' : '' }}">
                                <td><strong>{{ $line['catalog_item']->name }}</strong><span class="resource-secondary">{{ $line['catalog_item']->sku }}@if(! $line['is_planned']) · <span class="text-danger fw-semibold">Neplanificat</span>@endif</span></td>
                                <td class="text-end">{{ $formatQuantity($line['planned_quantity']) }} {{ $line['unit'] }}</td>
                                <td class="text-end fw-semibold">{{ $formatQuantity($line['committed_quantity']) }} {{ $line['unit'] }}</td>
                                <td class="text-end">
                                    @if($line['has_overrun'])<span class="text-danger fw-bold">+{{ $formatQuantity($line['overrun_quantity']) }} {{ $line['unit'] }}</span>@else<span class="text-success">{{ $formatQuantity($line['remaining_quantity']) }} {{ $line['unit'] }} rămas</span>@endif
                                </td>
                                <td>
                                    <div class="progress project-material-progress" role="progressbar" aria-label="Progres {{ $line['catalog_item']->name }}">
                                        <div class="progress-bar {{ $line['has_overrun'] ? 'bg-danger' : 'bg-success' }}" style="width: {{ $line['visual_percent'] }}%"></div>
                                    </div>
                                    <span class="resource-secondary">{{ $line['progress_percent'] !== null ? number_format($line['progress_percent'], 1, ',', '.').'%' : ($line['committed_quantity'] > 0 ? 'Material neplanificat' : '0%') }}</span>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="resource-mobile-list p-2">
                    @foreach($progress as $line)
                        <article id="material-plan-mobile-{{ $line['catalog_item']->id }}" class="project-material-mobile {{ $line['has_overrun'] ? 'project-material-mobile-danger' : '' }}">
                            <div class="d-flex justify-content-between gap-2"><strong>{{ $line['catalog_item']->name }}</strong>@if(! $line['is_planned'])<span class="badge text-bg-danger">Neplanificat</span>@endif</div>
                            <div class="project-material-mobile-grid"><span>Plan: <strong>{{ $formatQuantity($line['planned_quantity']) }} {{ $line['unit'] }}</strong></span><span>Solicitat: <strong>{{ $formatQuantity($line['committed_quantity']) }} {{ $line['unit'] }}</strong></span></div>
                            <div class="{{ $line['has_overrun'] ? 'text-danger fw-bold' : 'text-success' }}">{{ $line['has_overrun'] ? '+'.$formatQuantity($line['overrun_quantity']).' '.$line['unit'].' peste plan' : $formatQuantity($line['remaining_quantity']).' '.$line['unit'].' rămas' }}</div>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white"><strong>Transferuri legate de proiect</strong></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Transfer</th><th>Traseu</th><th>Solicitat de</th><th>Stare</th><th></th></tr></thead>
                        <tbody>
                        @forelse($transfers as $transfer)
                            <tr>
                                <td><strong>{{ $transfer->number }}</strong><span class="resource-secondary">{{ $transfer->lines->count() }} poziții</span></td>
                                <td>{{ $transfer->sourceLocation?->code }} → {{ $transfer->destinationLocation?->code }}</td>
                                <td>{{ $transfer->requester?->name ?? '—' }}</td>
                                <td><x-status :status="$transfer->status" :href="route('transfers.show', $transfer)" /></td>
                                <td class="text-end"><a href="{{ route('transfers.show', $transfer) }}" class="btn btn-sm btn-outline-primary">Deschide</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">Nu există încă transferuri legate de proiect.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="resource-table-footer">{{ $transfers->links() }}</div>
            </div>
        </div>

        <div class="col-xl-3">
            <div class="card mb-3"><div class="card-header bg-white"><strong>Detalii proiect</strong></div><div class="card-body vstack gap-3">
                <div><span class="resource-filter-label">Locație</span><strong class="d-block">{{ $project->location?->code }} — {{ $project->location?->name }}</strong></div>
                <div><span class="resource-filter-label">Responsabil plan</span><strong class="d-block">{{ $project->creator?->name ?? 'Utilizator indisponibil' }}</strong></div>
                <div><span class="resource-filter-label">Perioadă</span><strong class="d-block">{{ $project->starts_on?->format('d.m.Y') ?? 'Nespecificată' }} — {{ $project->ends_on?->format('d.m.Y') ?? 'Nespecificată' }}</strong></div>
                @if($project->notes)<div><span class="resource-filter-label">Observații</span><div>{{ $project->notes }}</div></div>@endif
            </div></div>
            <div class="card"><div class="card-header bg-white"><strong>Cine vede alertele</strong></div><div class="card-body small text-muted">Responsabilul care a creat planul, administratorii și responsabilii activi ai locației sunt anunțați când o cantitate este depășită.</div></div>
        </div>
    </div>
</div>
@endsection
