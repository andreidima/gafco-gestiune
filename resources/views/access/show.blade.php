@extends('layouts.app')

@section('title', 'Acces: '.$user->name)

@section('content')
@php
    $riskLabels = ['elevated' => 'Acces ridicat', 'sensitive' => 'Acces sensibil', 'protected' => 'Acces protejat'];
    $riskColors = ['elevated' => 'warning', 'sensitive' => 'danger', 'protected' => 'dark'];
@endphp
<div class="resource-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a href="{{ route('access.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>Administrare acces</a>
            <h1 class="h3 mt-2 mb-1">{{ $user->name }}</h1>
            <div class="d-flex flex-wrap align-items-center gap-2"><span class="resource-code">{{ $user->login_code }}</span><span class="badge text-bg-{{ $user->active ? 'success' : 'secondary' }}">{{ $user->active ? 'Activ' : 'Inactiv' }}</span></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if($canManageExceptions)<a href="{{ route('access.exceptions.edit', $user) }}" class="btn btn-outline-primary"><i class="fa-solid fa-bolt me-1"></i>Administrează excepțiile</a>@endif
            @if(auth()->user()->hasAnyRole(['admin', 'super-admin']))<a href="{{ route('users.edit', $user) }}" class="btn btn-primary"><i class="fa-solid fa-user-pen me-1"></i>Modifică utilizatorul</a>@endif
        </div>
    </div>

    @foreach($warnings as $warning)
        <div class="alert alert-{{ $warning['severity'] }} py-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>{{ $warning['message'] }}</div>
    @endforeach

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Permise</div><div class="fs-3 fw-semibold text-success">{{ $summary['allowed'] }}</div></div></div></div>
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Globale</div><div class="fs-3 fw-semibold">{{ $summary['global'] }}</div></div></div></div>
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Condiționate</div><div class="fs-3 fw-semibold text-warning">{{ $summary['conditional'] }}</div></div></div></div>
        <div class="col-6 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Refuzate</div><div class="fs-3 fw-semibold text-secondary">{{ $summary['denied'] }}</div></div></div></div>
        <div class="col-12 col-lg"><div class="card h-100"><div class="card-body"><div class="text-muted small">Excepții directe</div><div class="fs-3 fw-semibold text-info">{{ $summary['direct'] }}</div></div></div></div>
    </div>

    @if($user->permissions->isNotEmpty())
        <div class="card mb-4">
            <div class="card-header bg-white py-3"><h2 class="h5 mb-1">Excepții individuale</h2><p class="text-muted mb-0">Drepturi acordate direct acestui utilizator, separat de rolurile sale.</p></div>
            <div class="list-group list-group-flush">
                @foreach($user->permissions->sortBy('name') as $permission)
                    @php($context = $exceptionContexts->get($permission->name))
                    <div class="list-group-item py-3"><div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ config('access.permissions.'.$permission->name.'.label', $permission->name) }}</strong><code>{{ $permission->name }}</code></div><div class="small mt-1">{{ $context?->reason ?? 'Justificarea nu este disponibilă.' }}</div><div class="small text-muted">Acordată de {{ $context?->granter?->name ?? 'Sistem / configurație anterioară' }}</div></div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-body"><h2 class="h5">Roluri</h2>@forelse($user->roles as $role)<span class="badge text-bg-light border me-1 mb-1">{{ $roleLabels[$role->name] ?? $role->name }}</span>@empty<span class="text-warning">Niciun rol atribuit</span>@endforelse</div></div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100"><div class="card-body"><h2 class="h5">Locații administrate</h2>@forelse($user->activeManagedLocations as $location)<div>{{ $location->code }} - {{ $location->name }}</div>@empty<span class="text-muted">Nicio locație administrată</span>@endforelse</div></div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white py-3"><h2 class="h5 mb-1">Acces efectiv, explicat</h2><p class="text-muted mb-0">Rezultatul combină starea contului, rolurile, excepțiile directe și domeniul fiecărei capabilități.</p></div>
        <div class="accordion accordion-flush" id="effective-access">
            @foreach($decisionsByModule as $module => $decisions)
                @php($allowedCount = $decisions->where('allowed', true)->count())
                <div class="accordion-item">
                    <h3 class="accordion-header" id="access-heading-{{ $loop->index }}"><button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#access-module-{{ $loop->index }}"><span class="me-2">{{ $module }}</span><span class="badge text-bg-success me-1">{{ $allowedCount }} permise</span><span class="badge text-bg-light border">{{ $decisions->count() - $allowedCount }} refuzate</span></button></h3>
                    <div id="access-module-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#effective-access">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th class="ps-3">Capabilitate</th><th>Decizie</th><th>Domeniu</th><th>Explicație</th></tr></thead>
                                <tbody>
                                @foreach($decisions as $decision)
                                    <tr>
                                        <td class="ps-3"><strong>{{ $decision->label }}</strong>@if(isset($riskLabels[$decision->risk]))<span class="badge text-bg-{{ $riskColors[$decision->risk] }} ms-1">{{ $riskLabels[$decision->risk] }}</span>@endif<div class="small text-muted">{{ $decision->description }}</div><code class="small">{{ $decision->ability }}</code></td>
                                        <td><span class="badge text-bg-{{ $decision->allowed ? 'success' : 'secondary' }}"><i class="fa-solid fa-{{ $decision->allowed ? 'check' : 'xmark' }} me-1"></i>{{ $decision->allowed ? 'Permis' : 'Refuzat' }}</span>@if($decision->conditional)<div class="small text-warning mt-1">Acces condiționat</div>@endif</td>
                                        <td>@if($decision->allowed)<strong>{{ $decision->scopeLabel }}</strong>@if($decision->locations)<ul class="small text-muted ps-3 mb-0">@foreach($decision->locations as $location)<li>{{ $location }}</li>@endforeach</ul>@endif @else<span class="text-muted">—</span>@endif</td>
                                        <td><div>{{ $decision->reason }}</div>@foreach($decision->sources as $source)<div class="small text-muted">{{ $source['label'] }} · {{ config('access.scope_labels.'.$source['scope'], $source['scope']) }}</div>@if(isset($source['reason']))<div class="small text-info">Motiv: {{ $source['reason'] }}</div>@endif @endforeach @if($decision->condition)<div class="small text-warning mt-1"><i class="fa-solid fa-filter me-1"></i>{{ $decision->condition }}</div>@endif</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white py-3"><h2 class="h5 mb-1">Istoric recent al accesului</h2><p class="text-muted mb-0">Modificările de rol, stare și responsabilități de locație sunt păstrate în jurnal.</p></div>
        <div class="list-group list-group-flush">
            @forelse($recentActivities as $activity)
                <div class="list-group-item py-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ $activity->description }}</strong><span class="text-muted small">{{ $activity->created_at->format('d.m.Y H:i') }}</span></div>
                    <div class="small text-muted">de {{ $activity->causer?->name ?? 'Sistem' }}</div>
                    @if(data_get($activity->properties, 'location'))<div class="small mt-1">Locație: {{ data_get($activity->properties, 'location') }}</div>@endif
                    @if(data_get($activity->properties, 'reason'))<div class="small mt-1">Motiv: {{ data_get($activity->properties, 'reason') }}</div>@endif
                    @php($removedLocations = data_get($activity->properties, 'removed_location_responsibilities', []))
                    @if($removedLocations)<div class="small text-warning mt-1">Responsabilități retrase automat: {{ implode(', ', $removedLocations) }}</div>@endif
                </div>
            @empty
                <div class="list-group-item text-muted py-4 text-center">Nu există încă modificări de acces înregistrate.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
