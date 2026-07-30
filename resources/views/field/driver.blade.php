@extends('layouts.app')

@section('title', 'Mod sofer')

@section('content')
<div class="dashboard-shell mx-3">
    <div class="dashboard-hero mb-4">
        <span class="dashboard-pill"><i class="fa-solid fa-truck-fast me-2"></i> Mod sofer</span>
        <h3 class="mb-2">Curse alocate si marfa in tranzit</h3>
        <p class="mb-0 text-muted">Lista scurta pentru telefon: ridicare, destinatie, aviz si confirmare predare.</p>
    </div>

    <div class="row g-3">
        @forelse($transfers as $transfer)
            <div class="col-xl-4 col-md-6">
                <div class="field-card h-100">
                    <div class="d-flex justify-content-between gap-2 mb-3">
                        <div>
                            <div class="fw-bold">{{ $transfer->number }}</div>
                            <div class="small text-muted">{{ $transfer->document_number ?: 'Fara aviz' }}</div>
                        </div>
                        <x-status :status="$transfer->status" :href="route('transfers.show', $transfer)" />
                    </div>
                    <div class="field-route mb-3">
                        <div><i class="fa-solid fa-location-dot"></i> {{ $transfer->sourceLocation?->name }}</div>
                        <i class="fa-solid fa-arrow-down"></i>
                        <div><i class="fa-solid fa-flag-checkered"></i> {{ $transfer->destinationLocation?->name }}</div>
                    </div>
                    <div class="small fw-bold mb-2">Continut</div>
                    @foreach($transfer->lines as $line)
                        <div class="field-line">
                            <span>{{ $line->trackedAsset?->asset_code ?? $line->catalogItem?->name }}</span>
                            <span>{{ \App\Support\LocalizedNumber::quantity($line->quantity) }} {{ $line->unit }}</span>
                        </div>
                    @endforeach
                    <form method="post" action="{{ route('transfers.update', $transfer) }}" class="mt-3 d-flex gap-2">
                        @csrf
                        @method('put')
                        <input type="hidden" name="document_number" value="{{ $transfer->document_number }}">
                        <input type="hidden" name="status" value="{{ $transfer->status === 'assigned' ? 'in_transit' : 'received' }}">
                        <button class="btn btn-sm btn-primary rounded-3 w-100">
                            <i class="fa-solid fa-check me-1"></i>
                            {{ $transfer->status === 'assigned' ? 'Plecat' : 'Predat' }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-light border">Nu sunt curse active.</div>
            </div>
        @endforelse
    </div>

    <div class="card dashboard-chart-card mt-4">
        <div class="card-header bg-white"><strong>Cereri sofer deschise</strong></div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th class="culoare2 text-white">Cerere</th>
                        <th class="culoare2 text-white">Santier</th>
                        <th class="culoare2 text-white">Cand</th>
                        <th class="culoare2 text-white">Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($driverRequests as $request)
                    <tr>
                        <td>{{ $request->number }}</td>
                        <td>{{ $request->site?->name }}</td>
                        <td>{{ optional($request->needed_at)->format('d.m.Y H:i') }}</td>
                        <td><x-status :status="$request->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
