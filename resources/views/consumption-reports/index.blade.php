@extends('layouts.app')

@section('title', 'Consum')

@section('content')
<div class="mx-3 px-3 card crud-card">
    <div class="row card-header align-items-center">
        <div class="col-lg-3">
            <span class="badge culoare1 fs-5">
                <i class="fa-solid fa-clipboard-check"></i> Consum materiale
            </span>
            <div class="small text-muted mt-2">Raportare consum pe santier cu scadere automata din stoc</div>
        </div>
        <div class="col-lg-9">
            <form method="post" action="{{ route('consumption-reports.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-lg-3">
                    <label class="form-label small mb-1">Locatie</label>
                    <select name="location_id" class="form-select form-select-sm rounded-3" required>
                        <option value="">Alege</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} - {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <label class="form-label small mb-1">Material</label>
                    <select name="catalog_item_id" class="form-select form-select-sm rounded-3" required>
                        <option value="">Alege</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label small mb-1">Cantitate</label>
                    <input name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control form-control-sm rounded-3" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label small mb-1">Observatii</label>
                    <input name="notes" class="form-control form-control-sm rounded-3" placeholder="Lucrare / echipa / zona">
                </div>
                <div class="col-lg-1">
                    <button class="btn btn-sm btn-success text-white border border-dark rounded-3 w-100">
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card-body px-0 py-3">
        <div class="table-responsive rounded">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Raport</th>
                        <th class="culoare2 text-white">Locatie</th>
                        <th class="culoare2 text-white">Materiale</th>
                        <th class="culoare2 text-white">Responsabil</th>
                        <th class="culoare2 text-white">Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>
                            <strong>{{ $report->number }}</strong>
                            <div class="small text-muted">{{ optional($report->reported_at)->format('d.m.Y H:i') }}</div>
                        </td>
                        <td>{{ $report->location?->name }}</td>
                        <td>
                            @foreach($report->lines as $line)
                                <div>{{ $line->catalogItem?->name }} <span class="text-muted">({{ number_format((float) $line->quantity, 2) }} {{ $line->unit }})</span></div>
                            @endforeach
                        </td>
                        <td>{{ $report->reporter?->name ?? '-' }}</td>
                        <td><x-status :status="$report->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">Nu exista rapoarte de consum.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3">{{ $reports->links() }}</div>
    </div>
</div>
@endsection
