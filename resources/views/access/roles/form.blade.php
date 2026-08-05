@extends('layouts.app')

@php
    $editing = (bool) $role;
    $systemRole = $editing && array_key_exists($role->name, config('access.roles', []));
    $protectedRole = $editing && $role->name === 'super-admin';
    $metadata = $preview['metadata'] ?? null;
    $reservedNames = config('access.reserved_permissions', []);
@endphp
@section('title', $editing ? 'Configurare rol' : 'Rol personalizat nou')

@section('content')
<div class="resource-shell">
    <div class="mb-4"><a href="{{ route('access.roles.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>Roluri și drepturi</a><h1 class="h3 mt-2 mb-1">{{ $editing ? 'Configurare rol: '.app(\App\Services\AccessCatalog::class)->roleLabel($role->name) : 'Rol personalizat nou' }}</h1><p class="text-muted mb-0">{{ $editing ? 'Verifică metadatele și drepturile acordate rolului.' : 'Creează mai întâi rolul, apoi configurează drepturile sale.' }}</p></div>

    @if($protectedRole)<div class="alert alert-dark"><i class="fa-solid fa-lock me-2"></i>Rolul Super administrator este protejat. Configurația poate fi consultată, dar nu poate fi modificată.</div>@endif
    @if($reservedPermissions)<div class="alert alert-info"><strong>Drepturi rezervate păstrate automat:</strong> {{ implode(', ', $reservedPermissions) }}</div>@endif

    <form method="post" action="{{ $editing ? route('access.roles.preview', $role) : route('access.roles.store') }}" class="resource-form-card">
        @csrf
        <div class="resource-form-section">
            <div class="resource-form-section-title">Identitatea rolului</div>
            @if($systemRole)
                <input type="hidden" name="name" value="{{ $role->name }}">
                <div class="row g-3"><div class="col-md-4"><label class="form-label">Cod intern</label><input class="form-control" value="{{ $role->name }}" readonly></div><div class="col-md-8"><label class="form-label">Denumire</label><input class="form-control" value="{{ config('roles.labels.'.$role->name, $role->name) }}" readonly></div></div>
            @else
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Cod intern</label><input name="name" class="form-control" value="{{ old('name', $role?->name) }}" placeholder="ex: coordonator-logistica" @readonly($editing) required><div class="form-text">Litere mici, cifre și cratimă. Codul nu se schimbă după creare.</div></div>
                    <div class="col-md-8"><label class="form-label">Denumire afișată</label><input name="label" class="form-control" value="{{ old('label', data_get($metadata, 'label', $profile?->label)) }}" required></div>
                    <div class="col-md-8"><label class="form-label">Descriere</label><textarea name="description" class="form-control" rows="3" required>{{ old('description', data_get($metadata, 'description', $profile?->description)) }}</textarea></div>
                    <div class="col-md-4"><label class="form-label">Spațiu de lucru</label><input name="workspace" class="form-control" value="{{ old('workspace', data_get($metadata, 'workspace', $profile?->workspace)) }}" required><div class="form-check mt-3"><input type="hidden" name="requires_locations" value="0"><input class="form-check-input" type="checkbox" name="requires_locations" value="1" id="requires-locations" @checked(old('requires_locations', data_get($metadata, 'requires_locations', $profile?->requires_locations ?? false)))><label class="form-check-label" for="requires-locations">Necesită locație administrată</label></div></div>
                </div>
            @endif
        </div>

        @if($editing)
            <div class="resource-form-section">
                <div class="resource-form-section-title">Drepturi configurabile</div>
                <p class="text-muted">Drepturile rezervate nu apar în selecție și sunt păstrate automat.</p>
                <div class="accordion" id="role-permissions">
                    @foreach($permissionsByModule as $module => $permissions)
                        @php($editablePermissions = $permissions->filter(fn($entry) => ($entry['definition']['driver'] ?? 'permission') === 'permission' && !in_array($entry['ability'], $reservedNames, true)))
                        @if($editablePermissions->isNotEmpty())
                            <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#role-permissions-{{ $loop->index }}">{{ $module }} <span class="badge text-bg-light border ms-2">{{ $editablePermissions->count() }}</span></button></h2><div id="role-permissions-{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#role-permissions"><div class="list-group list-group-flush">@foreach($editablePermissions as $entry)<label class="list-group-item d-flex gap-3 align-items-start"><input class="form-check-input mt-1" type="checkbox" name="permissions[]" value="{{ $entry['ability'] }}" @checked(in_array($entry['ability'], old('permissions', $selectedPermissions), true)) @disabled($protectedRole)><span><strong>{{ $entry['definition']['label'] }}</strong><code class="ms-1">{{ $entry['ability'] }}</code><span class="d-block small text-muted">{{ $entry['definition']['description'] }}</span></span></label>@endforeach</div></div></div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <div class="resource-form-actions-bar"><a href="{{ route('access.roles.index') }}" class="btn btn-outline-secondary">Renunță</a>@unless($protectedRole)<button class="btn btn-primary"><i class="fa-solid fa-{{ $editing ? 'magnifying-glass' : 'plus' }} me-1"></i>{{ $editing ? 'Previzualizează modificările' : 'Creează rolul' }}</button>@endunless</div>
    </form>

    @if($preview)
        <div class="card border-primary mt-4"><div class="card-header bg-primary text-white"><strong>Previzualizarea modificărilor</strong></div><div class="card-body"><p>Modificarea afectează <strong>{{ $preview['affected_users'] }}</strong> utilizatori.</p>@if($preview['metadata_changed'])<div class="alert alert-info py-2">Denumirea afișată, descrierea sau cerințele rolului vor fi actualizate.</div>@endif<div class="row g-3"><div class="col-md-6"><h2 class="h6 text-success">Drepturi adăugate</h2>@forelse($preview['added'] as $ability)<div><i class="fa-solid fa-plus me-1"></i>{{ config('access.permissions.'.$ability.'.label', $ability) }} <code>{{ $ability }}</code></div>@empty<span class="text-muted">Niciunul</span>@endforelse</div><div class="col-md-6"><h2 class="h6 text-danger">Drepturi retrase</h2>@forelse($preview['removed'] as $ability)<div><i class="fa-solid fa-minus me-1"></i>{{ config('access.permissions.'.$ability.'.label', $ability) }} <code>{{ $ability }}</code></div>@empty<span class="text-muted">Niciunul</span>@endforelse</div></div></div>@if($preview['added'] || $preview['removed'] || $preview['metadata_changed'])<div class="card-footer d-flex justify-content-end"><form method="post" action="{{ route('access.roles.update', $role) }}">@csrf @method('put')<input type="hidden" name="confirmation_token" value="{{ $preview['token'] }}"><button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Aplică modificările verificate</button></form></div>@else<div class="card-footer text-muted">Nu există modificări de aplicat.</div>@endif</div>
    @endif

    @if($editing && !$systemRole)
        <div class="card border-danger mt-4"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><strong>Ștergerea rolului</strong><div class="small text-muted">Este permisă numai dacă rolul nu mai este atribuit niciunui utilizator.</div></div><form method="post" action="{{ route('access.roles.destroy', $role) }}" onsubmit="return confirm('Ștergi definitiv acest rol personalizat?')">@csrf @method('delete')<button class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Șterge rolul</button></form></div></div>
    @endif
</div>
@endsection
