@extends('layouts.app')

@section('title', 'Locatii')

@section('content')
<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
            <div>
                <span class="dashboard-pill"><i class="fa-solid fa-building me-2"></i> Locatii</span>
                <h3 class="mb-2">Baze, santiere si puncte de lucru</h3>
                <p class="mb-0 text-muted">Structura operationala unde se afla materialele, utilajele si sculele.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('tracked-assets.index') }}" class="btn btn-outline-primary rounded-3">
                    <i class="fa-solid fa-screwdriver-wrench me-1"></i> Echipamente
                </a>
                <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary rounded-3">
                    <i class="fa-solid fa-chart-column me-1"></i> Rapoarte
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-slate">
                <span>Total</span>
                <strong>{{ $stats['total'] }}</strong>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-rose">
                <span>Santiere</span>
                <strong>{{ $stats['sites'] }}</strong>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-teal">
                <span>Baze</span>
                <strong>{{ $stats['bases'] }}</strong>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-forest">
                <span>Cu manager</span>
                <strong>{{ $stats['with_manager'] }}</strong>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-amber">
                <span>Pozitii stoc</span>
                <strong>{{ $stats['stock_positions'] }}</strong>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="mini-stat accent-danger">
                <span>Asset-uri QR</span>
                <strong>{{ $stats['assets'] }}</strong>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card dashboard-chart-card h-100">
                <div class="card-header bg-white"><strong><i class="fa-solid fa-plus me-1"></i> Adauga locatie</strong></div>
                <div class="card-body">
                    <form method="post" action="{{ route('locations.store') }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-2">
                            <label class="form-label">Tip</label>
                            <select name="type" class="form-select rounded-3">
                                <option value="site">Santier</option>
                                <option value="base">Baza</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cod</label>
                            <input name="code" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Denumire</label>
                            <input name="name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Adresa</label>
                            <input name="address" class="form-control rounded-3">
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-success rounded-3 w-100"><i class="fa-solid fa-plus"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="insight-panel h-100">
                <div class="insight-row">
                    <i class="fa-solid fa-ranking-star"></i>
                    <div>
                        <span>Cele mai multe echipamente</span>
                        <strong>{{ $insights['top_location']?->name ?? '-' }}</strong>
                        <small>{{ $insights['top_location']?->assets_count ?? 0 }} asset-uri</small>
                    </div>
                </div>
                <div class="insight-row">
                    <i class="fa-solid fa-user-slash"></i>
                    <div>
                        <span>Locatii fara manager</span>
                        <strong>{{ $insights['without_manager'] }}</strong>
                        <small>Merita completate inainte de productie</small>
                    </div>
                </div>
                <div class="insight-row">
                    <i class="fa-solid fa-clock"></i>
                    <div>
                        <span>Ultima locatie adaugata</span>
                        <strong>{{ $insights['latest']?->code ?? '-' }}</strong>
                        <small>{{ $insights['latest']?->name ?? '-' }}</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card dashboard-chart-card">
        <div class="card-header bg-white">
            <form class="row g-2 align-items-center">
                <div class="col-lg-7">
                    <input name="search" value="{{ request('search') }}" class="form-control rounded-3" placeholder="Cauta dupa cod, nume sau adresa">
                </div>
                <div class="col-lg-2">
                    <select name="type" class="form-select rounded-3">
                        <option value="">Toate tipurile</option>
                        <option value="base" @selected(request('type')==='base')>Baze</option>
                        <option value="site" @selected(request('type')==='site')>Santiere</option>
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button class="btn btn-outline-primary rounded-3 flex-fill">
                        <i class="fa-solid fa-filter me-1"></i> Filtreaza
                    </button>
                    <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary rounded-3">
                        <i class="fa-solid fa-rotate-right"></i>
                    </a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Cod</th>
                        <th class="culoare2 text-white">Denumire</th>
                        <th class="culoare2 text-white">Tip</th>
                        <th class="culoare2 text-white">Adresa</th>
                        <th class="culoare2 text-white">Manager</th>
                        <th class="culoare2 text-white">Status</th>
                        <th class="culoare2 text-white text-end">Actiuni</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($locations as $location)
                    <tr>
                        <td class="fw-semibold">{{ $location->code }}</td>
                        <td>
                            <strong>{{ $location->name }}</strong>
                            <div class="small text-secondary">ID intern #{{ $location->id }}</div>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-{{ $location->type === 'base' ? 'primary' : 'info' }}">
                                {{ $location->type === 'base' ? 'Baza' : 'Santier' }}
                            </span>
                        </td>
                        <td>{{ $location->address ?: '-' }}</td>
                        <td>
                            @if($location->manager)
                                <span class="badge rounded-pill text-bg-success">{{ $location->manager->name }}</span>
                            @else
                                <span class="badge rounded-pill text-bg-warning">Fara manager</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-{{ $location->active ? 'success' : 'secondary' }}">
                                {{ $location->active ? 'Activ' : 'Inactiv' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-outline-primary" href="{{ route('tracked-assets.index', ['search' => $location->code]) }}">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                </a>
                                <a class="btn btn-outline-secondary" href="{{ route('reports.index') }}">
                                    <i class="fa-solid fa-chart-column"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Nu exista locatii.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $locations->links() }}</div>
    </div>
</div>
@endsection
