@extends('layouts.app')

@section('title', 'Documente de procesat')

@section('content')
@php
    $hasFilters = request()->filled('search') || request()->filled('status') || request()->filled('location_id');
@endphp
<div class="resource-shell">
    <x-resource-page-header
        title="Documente de procesat"
        description="Fotografii și documente trimise din teren. O recepție actualizează stocul numai după procesare."
        :count="$intakes->total()"
        icon="fa-file-circle-check"
        :create-route="$canUpload ? route('reception-intakes.create') : null"
        create-label="Trimite documente"
    >
        <x-slot:actions>
            @if($openCount)
                <span class="badge rounded-pill text-bg-warning">{{ $openCount }} în așteptare</span>
            @endif
        </x-slot:actions>
    </x-resource-page-header>

    <form class="resource-filter-panel" data-auto-submit-filters>
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-lg-5">
                <label class="resource-filter-label">Căutare</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Număr sau observații">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="resource-filter-label">Locație</label>
                <select name="location_id" class="form-select" data-tom-select>
                    <option value="">Toate locațiile vizibile</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) request('location_id') === (string) $location->id)>
                            {{ $location->code }} — {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="resource-filter-label">Stare</label>
                <select name="status" class="form-select">
                    <option value="">Toate</option>
                    <option value="created" @selected(request('status') === 'created')>Creat</option>
                    <option value="closed" @selected(request('status') === 'closed')>Închis</option>
                </select>
            </div>
            <div class="col-lg-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrează</button>
                <a href="{{ route('reception-intakes.index', ['filters_reset' => 1]) }}" class="btn btn-outline-secondary" title="Resetează">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </div>
    </form>

    <div class="resource-table-card">
        <div class="table-responsive resource-desktop-table">
            <table class="table resource-table mb-0">
                <thead>
                    <tr>
                        <th>Înregistrare</th>
                        <th>Locație</th>
                        <th>Trimis de</th>
                        <th>Fișiere</th>
                        <th>Stare</th>
                        <th class="text-end"><span class="visually-hidden">Detalii</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($intakes as $intake)
                    <tr data-href="{{ route('reception-intakes.show', $intake) }}">
                        <td>
                            <a href="{{ route('reception-intakes.show', $intake) }}" class="resource-primary text-decoration-none">{{ $intake->number }}</a>
                            <span class="resource-secondary">{{ $intake->created_at->format('d.m.Y H:i') }}</span>
                        </td>
                        <td>
                            <strong>{{ $intake->location?->code ?? '—' }}</strong>
                            <span class="resource-secondary">{{ $intake->location?->name }}</span>
                        </td>
                        <td>{{ $intake->submitter?->name ?? 'Utilizator indisponibil' }}</td>
                        <td><i class="fa-solid fa-paperclip me-1"></i>{{ $intake->documents_count }}</td>
                        <td>
                            @if($intake->status === 'created')
                                <span class="badge text-bg-warning">Creat</span>
                            @elseif($intake->closure_type === 'converted')
                                <span class="badge text-bg-success">Închis — recepție creată</span>
                            @else
                                <span class="badge text-bg-secondary">Închis — anulat</span>
                            @endif
                        </td>
                        <td class="text-end"><i class="fa-solid fa-chevron-right text-secondary"></i></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary">
                            {{ $hasFilters ? 'Nu există înregistrări pentru filtrele selectate.' : 'Nu există documente trimise.' }}
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="resource-mobile-list">
            @forelse($intakes as $intake)
                <article class="card resource-mobile-card" data-href="{{ route('reception-intakes.show', $intake) }}">
                    <div class="card-body">
                        <div class="resource-mobile-card-header">
                            <div>
                                <h2 class="resource-mobile-card-title">{{ $intake->number }}</h2>
                                <span class="resource-secondary">{{ $intake->created_at->format('d.m.Y H:i') }}</span>
                            </div>
                            <a href="{{ route('reception-intakes.show', $intake) }}" class="badge status-badge-link {{ $intake->status === 'created' ? 'text-bg-warning' : 'text-bg-secondary' }}">
                                {{ $intake->status === 'created' ? 'Creat' : 'Închis' }}<span class="visually-hidden"> — deschide recepția</span>
                            </a>
                        </div>
                        <div class="resource-mobile-card-grid">
                            <div>
                                <span class="resource-filter-label">Locație</span>
                                <strong>{{ $intake->location?->code ?? '—' }}</strong>
                                <span class="resource-secondary">{{ $intake->location?->name }}</span>
                            </div>
                            <div>
                                <span class="resource-filter-label">Documente</span>
                                <strong>{{ $intake->documents_count }}</strong>
                                <span class="resource-secondary">{{ $intake->submitter?->name }}</span>
                            </div>
                        </div>
                        <div class="resource-mobile-card-actions">
                            <a href="{{ route('reception-intakes.show', $intake) }}" class="btn btn-outline-primary btn-sm">Deschide</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="resource-empty-state">{{ $hasFilters ? 'Nu există înregistrări pentru filtrele selectate.' : 'Nu există documente trimise.' }}</div>
            @endforelse
        </div>

        <div class="resource-table-footer">{{ $intakes->links() }}</div>
    </div>
</div>
@endsection
