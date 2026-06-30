@extends('layouts.app')

@section('title', 'Utilizatori')
@section('page_title', 'Utilizatori')
@section('page_subtitle', 'Conturi si roluri')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ route('users.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2"><label class="form-label">Nume</label><input name="name" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label">Parola</label><input name="password" type="password" class="form-control" required></div>
                <div class="col-md-3">
                    <label class="form-label">Roluri</label>
                    <select name="roles[]" class="form-select" multiple data-tom-select>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <div class="form-check">
                        <input name="active" value="1" class="form-check-input" type="checkbox" checked id="activeUser">
                        <label class="form-check-label" for="activeUser">Activ</label>
                    </div>
                </div>
                <div class="col-md-1"><button class="btn btn-success w-100">Adauga</button></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <form class="row g-2">
                <div class="col-md-10"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cauta dupa nume sau email"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100">Cauta</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Nume</th><th>Email</th><th>Roluri</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="fw-semibold">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @forelse($user->roles as $role)
                                <span class="badge text-bg-light border me-1">{{ $role->name }}</span>
                            @empty
                                <span class="text-secondary">Fara rol</span>
                            @endforelse
                        </td>
                        <td><span class="badge text-bg-{{ $user->active ? 'success' : 'secondary' }}">{{ $user->active ? 'Activ' : 'Inactiv' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-4">Nu exista utilizatori.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $users->links() }}</div>
    </div>
@endsection
