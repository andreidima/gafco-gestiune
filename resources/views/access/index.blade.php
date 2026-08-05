@extends('layouts.app')

@section('title', 'Administrare acces')

@section('content')
@php
    $hasFilters = request()->filled('search') || request()->filled('role') || request()->filled('active');
    $roleNames = array_keys($roles);
    $riskLabels = ['elevated' => 'Acces ridicat', 'sensitive' => 'Acces sensibil', 'protected' => 'Acces protejat'];
    $riskColors = ['elevated' => 'warning', 'sensitive' => 'danger', 'protected' => 'dark'];
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Administrare acces"
        description="Vezi ce poate face fiecare utilizator, din ce rol provine accesul și unde se aplică."
        :count="$stats['total']"
        icon="fa-shield-halved"
    />

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl"><div class="card h-100"><div class="card-body"><div class="text-muted small">Conturi vizibile</div><div class="fs-3 fw-semibold">{{ $stats['total'] }}</div></div></div></div>
        <div class="col-6 col-xl"><div class="card h-100"><div class="card-body"><div class="text-muted small">Conturi active</div><div class="fs-3 fw-semibold text-success">{{ $stats['active'] }}</div></div></div></div>
        <div class="col-6 col-xl"><div class="card h-100"><div class="card-body"><div class="text-muted small">Fără rol</div><div class="fs-3 fw-semibold {{ $stats['without_role'] ? 'text-warning' : '' }}">{{ $stats['without_role'] }}</div></div></div></div>
        <div class="col-6 col-xl"><div class="card h-100"><div class="card-body"><div class="text-muted small">Excepții directe</div><div class="fs-3 fw-semibold {{ $stats['with_direct_permissions'] ? 'text-info' : '' }}">{{ $stats['with_direct_permissions'] }}</div></div></div></div>
        <div class="col-12 col-xl"><div class="card h-100"><div class="card-body"><div class="text-muted small">Rol local fără locație</div><div class="fs-3 fw-semibold {{ $stats['missing_location_scope'] ? 'text-danger' : '' }}">{{ $stats['missing_location_scope'] }}</div></div></div></div>
    </div>

    <div class="alert alert-info d-flex gap-2 align-items-start" role="note">
        <i class="fa-solid fa-circle-info mt-1"></i>
        <div><strong>Cum se citește accesul:</strong> rolul acordă o capabilitate, iar domeniul arată unde poate fi folosită. Condițiile operaționale — starea unei înregistrări, alocarea sau locația — se verifică în continuare la fiecare acțiune.</div>
    </div>

    <form class="resource-filter-panel" data-auto-submit-filters data-live-filter-target="#access-users-results">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-6"><label class="resource-filter-label">Căutare</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Nume, cod, telefon sau email"></div>
            <div class="col-xl-2 col-md-4"><label class="resource-filter-label">Rol</label><select name="role" class="form-select"><option value="">Toate</option>@foreach($roles as $role => $metadata)<option value="{{ $role }}" @selected(request('role') === $role)>{{ $roleLabels[$role] ?? $role }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="resource-filter-label">Stare</label><select name="active" class="form-select"><option value="">Oricare</option><option value="1" @selected(request('active') === '1')>Active</option><option value="0" @selected(request('active') === '0')>Inactive</option></select></div>
            <div class="col-xl-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Caută</button><a href="{{ route('access.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Resetează filtrele" aria-label="Resetează filtrele"><i class="fa-solid fa-rotate-left"></i></a></div>
        </div>
    </form>

    <div id="access-users-results" class="resource-table-card mb-4" data-live-filter-results>
        <div class="table-responsive">
            <table class="table resource-table align-middle">
                <thead><tr><th>Utilizator</th><th>Roluri și domeniu</th><th>Acces efectiv</th><th>Observații</th><th class="text-end">Detalii</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $user->name }}</span><span class="resource-code">{{ $user->login_code }}</span><span class="badge text-bg-{{ $user->active ? 'success' : 'secondary' }} align-self-start">{{ $user->active ? 'Activ' : 'Inactiv' }}</span></div></td>
                        <td>
                            <div class="mb-1">@forelse($user->roles as $role)<span class="badge text-bg-light border me-1 mb-1">{{ $roleLabels[$role->name] ?? $role->name }}</span>@empty<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Fără rol</span>@endforelse</div>
                            @forelse($user->activeManagedLocations->take(2) as $location)<div class="small text-muted">{{ $location->code }} - {{ $location->name }}</div>@empty<div class="small text-muted">Fără locație administrată</div>@endforelse
                            @if($user->activeManagedLocations->count() > 2)<div class="small text-muted">+{{ $user->activeManagedLocations->count() - 2 }} alte locații</div>@endif
                        </td>
                        <td>
                            <div><strong>{{ $user->access_summary['allowed'] }}</strong> capabilități permise</div>
                            <div class="small text-muted">{{ $user->access_summary['global'] }} globale · {{ $user->access_summary['conditional'] }} condiționate · {{ $user->access_summary['denied'] }} refuzate</div>
                            @if($user->access_summary['direct'])<div class="small text-info"><i class="fa-solid fa-bolt me-1"></i>{{ $user->access_summary['direct'] }} excepții directe</div>@endif
                        </td>
                        <td>
                            @forelse($user->access_warnings->take(2) as $warning)
                                <div class="small text-{{ $warning['severity'] === 'secondary' ? 'muted' : $warning['severity'] }} mb-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ $warning['message'] }}</div>
                            @empty
                                <span class="text-success small"><i class="fa-solid fa-circle-check me-1"></i>Configurație coerentă</span>
                            @endforelse
                            @if($user->access_warnings->count() > 2)<div class="small text-muted">+{{ $user->access_warnings->count() - 2 }} observații</div>@endif
                        </td>
                        <td class="text-end"><a href="{{ route('access.show', $user) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-magnifying-glass me-1"></i>Explică accesul</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-4 text-muted">{{ $hasFilters ? 'Niciun utilizator nu corespunde filtrelor.' : 'Nu există utilizatori disponibili.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="resource-table-footer">{{ $users->links() }}</div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white py-3"><h2 class="h5 mb-1"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Rolurile aplicației</h2><p class="text-muted mb-0">Rolul stabilește spațiul principal de lucru și setul standard de capabilități.</p></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th class="ps-3">Rol</th><th>Spațiu de lucru</th><th>Scop</th><th>Cerințe</th></tr></thead>
                <tbody>@foreach($roles as $role => $metadata)<tr><td class="ps-3"><strong>{{ $roleLabels[$role] ?? $role }}</strong>@if($metadata['privileged'])<span class="badge text-bg-danger ms-1">Privilegiat</span>@endif</td><td>{{ $metadata['workspace'] }}</td><td>{{ $metadata['description'] }}</td><td>{{ $metadata['requires_locations'] ? 'Necesită cel puțin o locație administrată' : 'Fără locație obligatorie' }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-1"><i class="fa-solid fa-table-cells-large me-2 text-primary"></i>Matricea rolurilor standard</h2>
            <p class="text-muted mb-0">Matricea descrie accesul implicit. Excepțiile atribuite direct unui utilizator apar numai în fișa persoanei.</p>
        </div>
        <div class="accordion accordion-flush" id="role-matrix">
            @foreach($permissionsByModule as $module => $permissions)
                <div class="accordion-item">
                    <h3 class="accordion-header" id="matrix-heading-{{ $loop->index }}">
                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#matrix-module-{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $module }} <span class="badge text-bg-light border ms-2">{{ $permissions->count() }}</span>
                        </button>
                    </h3>
                    <div id="matrix-module-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#role-matrix">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th class="ps-3" style="min-width: 250px">Capabilitate</th>@foreach($roleNames as $role)<th class="text-center" style="min-width: 115px">{{ $roleLabels[$role] ?? $role }}</th>@endforeach</tr></thead>
                                <tbody>
                                @foreach($permissions as $entry)
                                    <tr>
                                        <td class="ps-3"><strong>{{ $entry['definition']['label'] }}</strong>@if(isset($riskLabels[$entry['definition']['risk']]))<span class="badge text-bg-{{ $riskColors[$entry['definition']['risk']] }} ms-1">{{ $riskLabels[$entry['definition']['risk']] }}</span>@endif<div class="small text-muted"><code>{{ $entry['ability'] }}</code></div></td>
                                        @foreach($roleNames as $role)
                                            @php($scope = $entry['definition']['grants'][$role] ?? null)
                                            <td class="text-center">@if($scope)<span class="badge text-bg-success" title="{{ $catalog->scopeLabel($scope) }}"><i class="fa-solid fa-check"></i><span class="visually-hidden">{{ $catalog->scopeLabel($scope) }}</span></span><div class="small text-muted mt-1">{{ $catalog->scopeLabel($scope) }}</div>@else<span class="text-muted">—</span>@endif</td>
                                        @endforeach
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
</div>
@endsection
