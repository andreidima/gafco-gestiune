@extends('layouts.app')

@section('title', 'Utilizatori')

@section('content')
@php
    $roleLabels = config('roles.labels', []);
    $hasFilters = request()->filled('search')
        || request()->filled('role')
        || (request()->has('active') && request('active') !== '');
    $impersonationService = app(\App\Services\ImpersonationService::class);
    $impersonationActor = auth()->user();
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Utilizatori"
        description="Conturi, coduri de conectare, roluri si responsabilitati."
        :count="$totalUsers"
        :filtered-count="$users->total()"
        icon="fa-users"
        :create-route="route('users.create')"
        create-label="Utilizator nou"
    />

    <form class="resource-filter-panel" data-auto-submit-filters data-live-filter-target="#users-results">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-xl-6"><label class="resource-filter-label">Cautare</label><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Nume, cod, telefon sau email"></div>
            <div class="col-xl-2 col-md-4"><label class="resource-filter-label">Rol</label><select name="role" class="form-select"><option value="">Toate</option>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $roleLabels[$role->name] ?? $role->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="resource-filter-label">Stare</label><select name="active" class="form-select"><option value="">Oricare</option><option value="1" @selected(request('active') === '1')>Activi</option><option value="0" @selected(request('active') === '0')>Inactivi</option></select></div>
            <div class="col-xl-2 d-flex gap-2"><button class="btn btn-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Cauta</button><a href="{{ route('users.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Reseteaza filtrele" aria-label="Reseteaza filtrele"><i class="fa-solid fa-rotate-left"></i></a></div>
        </div>
    </form>

    <div id="users-results" class="resource-table-card" data-live-filter-results>
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table">
                <thead><tr><th>Utilizator</th><th>Contact</th><th>Roluri</th><th>Locatii gestionate</th><th>Status</th><th class="text-end">Actiuni</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    @php
                        $userRoleNames = $user->roles->pluck('name');
                        $visibleRoles = $user->roles->where('name', '!=', 'super-admin');
                        $requiresManagedLocation = $userRoleNames->intersect(['sef-santier', 'gestionar-baza'])->isNotEmpty();
                        $requiresPhone = $userRoleNames->intersect(['sofer', 'sef-santier', 'gestionar-baza', 'dispecer'])->isNotEmpty();
                    @endphp
                    <tr>
                        <td><div class="resource-cell-stack"><span class="resource-primary">{{ $user->name }}</span><span class="resource-code">{{ $user->login_code }}</span></div></td>
                        <td>
                            <div class="resource-cell-stack">
                                @if($user->phone)<span><i class="fa-solid fa-phone me-1 text-muted"></i>{{ $user->phone }}</span>@elseif($requiresPhone)<span class="text-warning"><i class="fa-brands fa-whatsapp me-1"></i>Fara telefon / WhatsApp</span>@else<span class="text-muted">-</span>@endif
                                @if($user->email && !str_ends_with($user->email, '@login.invalid'))<span class="resource-secondary"><i class="fa-regular fa-envelope me-1"></i>{{ $user->email }}</span>@endif
                            </div>
                        </td>
                        <td>@forelse($visibleRoles as $role)<span class="badge text-bg-light border me-1 mb-1">{{ $roleLabels[$role->name] ?? $role->name }}</span>@empty<span class="{{ $user->isProtectedAdministrator() ? 'text-muted' : 'text-warning' }}">@unless($user->isProtectedAdministrator())<i class="fa-solid fa-triangle-exclamation me-1"></i>@endunless{{ $user->isProtectedAdministrator() ? '-' : 'Fara rol' }}</span>@endforelse</td>
                        <td>
                            <div class="resource-cell-stack">
                                @forelse($user->activeManagedLocations->take(2) as $location)<span>{{ $location->code }} - {{ $location->name }}</span>@empty<span class="{{ $requiresManagedLocation ? 'text-warning' : 'resource-secondary' }}">{{ $requiresManagedLocation ? 'Lipseste locatia gestionata' : '-' }}</span>@endforelse
                                @if($user->activeManagedLocations->count() > 2)<span class="resource-secondary">+{{ $user->activeManagedLocations->count() - 2 }} alte locatii</span>@endif
                            </div>
                        </td>
                        <td><span class="badge text-bg-{{ $user->active ? 'success' : 'secondary' }}">{{ $user->active ? 'Activ' : 'Inactiv' }}</span></td>
                        <td>
                            <div class="resource-row-actions">
                                <x-resource-icon-button :href="route('access.show', $user)" icon="fa-shield-halved" label="Explică accesul utilizatorului" />
                                @if($impersonationService->canTake($impersonationActor, $user))
                                    <form method="post" action="{{ route('impersonation.take', $user) }}">
                                        @csrf
                                        <button
                                            class="btn btn-outline-warning resource-icon-button"
                                            title="Intră în contul utilizatorului"
                                            aria-label="Intră în contul utilizatorului {{ $user->name }}"
                                        >
                                            <i class="fa-solid fa-user-secret"></i>
                                        </button>
                                    </form>
                                @endif
                                <x-resource-icon-button :href="route('users.edit', $user)" icon="fa-pen" label="Modifica utilizatorul" />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            @if($hasFilters)
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Niciun utilizator nu corespunde filtrelor selectate.</span>
                                    <a href="{{ route('users.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                                </div>
                            @else
                                <div class="d-flex flex-column align-items-center gap-2">
                                    <span class="text-muted">Nu exista inca utilizatori.</span>
                                    <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus me-1"></i>Adauga primul utilizator</a>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($users as $user)
                @php
                    $userRoleNames = $user->roles->pluck('name');
                    $visibleRoles = $user->roles->where('name', '!=', 'super-admin');
                    $requiresManagedLocation = $userRoleNames->intersect(['sef-santier', 'gestionar-baza'])->isNotEmpty();
                    $requiresPhone = $userRoleNames->intersect(['sofer', 'sef-santier', 'gestionar-baza', 'dispecer'])->isNotEmpty();
                    $hasMissingProfileData = ($visibleRoles->isEmpty() && ! $user->isProtectedAdministrator())
                        || ($requiresManagedLocation && $user->activeManagedLocations->isEmpty())
                        || ($requiresPhone && ! $user->phone);
                @endphp
                <article class="card resource-mobile-card {{ $hasMissingProfileData ? 'resource-row-alert resource-row-alert-warning' : '' }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div class="min-w-0">
                                <h2 class="resource-mobile-card-title">{{ $user->name }}</h2>
                                <div class="resource-code">{{ $user->login_code }}</div>
                            </div>
                            <span class="badge text-bg-{{ $user->active ? 'success' : 'secondary' }}">{{ $user->active ? 'Activ' : 'Inactiv' }}</span>
                        </div>

                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Contact</span>
                                @if($user->phone)<strong><i class="fa-solid fa-phone me-1 text-muted"></i>{{ $user->phone }}</strong>@elseif($requiresPhone)<span class="text-warning fw-semibold"><i class="fa-brands fa-whatsapp me-1"></i>Fara telefon / WhatsApp</span>@else<span class="text-muted">-</span>@endif
                                @if($user->email && !str_ends_with($user->email, '@login.invalid'))<span class="resource-secondary text-break"><i class="fa-regular fa-envelope me-1"></i>{{ $user->email }}</span>@endif
                            </div>
                            <div>
                                <span class="resource-filter-label">Roluri</span>
                                @forelse($visibleRoles as $role)<span class="badge text-bg-light border me-1 mb-1">{{ $roleLabels[$role->name] ?? $role->name }}</span>@empty<span class="{{ $user->isProtectedAdministrator() ? 'text-muted' : 'text-warning fw-semibold' }}">@unless($user->isProtectedAdministrator())<i class="fa-solid fa-triangle-exclamation me-1"></i>@endunless{{ $user->isProtectedAdministrator() ? '-' : 'Fara rol' }}</span>@endforelse
                            </div>
                            <div class="resource-mobile-card-wide">
                                <span class="resource-filter-label">Locatii gestionate</span>
                                @forelse($user->activeManagedLocations->take(2) as $location)<strong class="d-block">{{ $location->code }} - {{ $location->name }}</strong>@empty<span class="{{ $requiresManagedLocation ? 'text-warning fw-semibold' : 'text-muted' }}">{{ $requiresManagedLocation ? 'Lipseste locatia gestionata' : '-' }}</span>@endforelse
                                @if($user->activeManagedLocations->count() > 2)<span class="resource-secondary">+{{ $user->activeManagedLocations->count() - 2 }} alte locatii</span>@endif
                            </div>
                        </div>

                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('access.show', $user) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-shield-halved me-1"></i>Acces</a>
                            @if($impersonationService->canTake($impersonationActor, $user))
                                <form method="post" action="{{ route('impersonation.take', $user) }}" class="d-flex flex-fill">
                                    @csrf
                                    <button class="btn btn-outline-warning btn-sm flex-fill">
                                        <i class="fa-solid fa-user-secret me-1"></i>Intră în cont
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-pen me-1"></i>Modifica</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">
                    @if($hasFilters)
                        <p class="mb-2">Niciun utilizator nu corespunde filtrelor selectate.</p>
                        <a href="{{ route('users.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-rotate-left me-1"></i>Reseteaza filtrele</a>
                    @else
                        <p class="mb-2">Nu exista inca utilizatori.</p>
                        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-user-plus me-1"></i>Adauga primul utilizator</a>
                    @endif
                </div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $users->links() }}</div>
    </div>
</div>
@endsection
