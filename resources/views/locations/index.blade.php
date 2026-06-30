@extends('layouts.app')

@section('title', 'Locatii')
@section('page_title', 'Locatii')
@section('page_subtitle', 'Baze, santiere si puncte de lucru')

@section('content')
    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ route('locations.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Tip</label>
                    <select name="type" class="form-select">
                        <option value="site">Santier</option>
                        <option value="base">Baza</option>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Cod</label><input name="code" class="form-control" required></div>
                <div class="col-md-3"><label class="form-label">Denumire</label><input name="name" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Adresa</label><input name="address" class="form-control"></div>
                <div class="col-md-1"><button class="btn btn-success w-100">Adauga</button></div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header bg-white">
            <form class="row g-2">
                <div class="col-md-8"><input name="search" value="{{ request('search') }}" class="form-control" placeholder="Cauta dupa cod, nume sau adresa"></div>
                <div class="col-md-2">
                    <select name="type" class="form-select">
                        <option value="">Toate</option>
                        <option value="base" @selected(request('type')==='base')>Baze</option>
                        <option value="site" @selected(request('type')==='site')>Santiere</option>
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filtreaza</button></div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th>Cod</th><th>Denumire</th><th>Tip</th><th>Adresa</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($locations as $location)
                    <tr><td class="fw-semibold">{{ $location->code }}</td><td>{{ $location->name }}</td><td>{{ $location->type }}</td><td>{{ $location->address }}</td><td><span class="badge text-bg-{{ $location->active ? 'success' : 'secondary' }}">{{ $location->active ? 'Activ' : 'Inactiv' }}</span></td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">Nu exista locatii.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $locations->links() }}</div>
    </div>
@endsection
