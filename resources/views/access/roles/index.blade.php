@extends('layouts.app')

@section('title', 'Roluri și drepturi')

@section('content')
<div class="resource-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div><a href="{{ route('access.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>Administrare acces</a><h1 class="h3 mt-2 mb-1">Roluri și drepturi</h1><p class="text-muted mb-0">Configurează rolurile aplicației fără a delega capabilitățile rezervate.</p></div>
        <a href="{{ route('access.roles.create') }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Rol personalizat nou</a>
    </div>

    <div class="alert alert-warning"><i class="fa-solid fa-shield-halved me-2"></i>Modificarea unui rol afectează imediat toți utilizatorii care îl au. Salvarea este disponibilă numai după previzualizarea diferențelor.</div>

    <div class="resource-table-card">
        <div class="table-responsive">
            <table class="table resource-table align-middle">
                <thead><tr><th>Rol</th><th>Tip</th><th>Utilizatori</th><th>Drepturi</th><th>Descriere</th><th class="text-end">Acțiuni</th></tr></thead>
                <tbody>
                @foreach($roles as $role)
                    @php($profile = $profiles->get($role->id))
                    @php($system = array_key_exists($role->name, $catalog->roles()))
                    <tr>
                        <td><strong>{{ $catalog->roleLabel($role->name) }}</strong><div><code>{{ $role->name }}</code></div></td>
                        <td><span class="badge text-bg-{{ $system ? 'secondary' : 'info' }}">{{ $system ? 'Standard' : 'Personalizat' }}</span>@if($role->name === 'super-admin')<span class="badge text-bg-dark ms-1">Protejat</span>@endif</td>
                        <td>{{ $role->users_count }}</td>
                        <td>{{ $role->permissions->count() }}</td>
                        <td>{{ $system ? config('access.roles.'.$role->name.'.description') : $profile?->description }}</td>
                        <td class="text-end"><a href="{{ route('access.roles.edit', $role) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-{{ $role->name === 'super-admin' ? 'eye' : 'pen' }} me-1"></i>{{ $role->name === 'super-admin' ? 'Consultă' : 'Configurează' }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
