@extends('layouts.app')

@section('title', $item->name)

@php
    $formatQuantity = static fn ($value) => number_format((float) $value, 3, ',', '.');
    $movementLabels = [
        'opening_balance' => 'Sold inițial',
        'reception' => 'Recepție',
        'consumption' => 'Consum',
        'consumption_correction_reversal' => 'Anulare pentru corecție',
        'transfer_out' => 'Transfer ieșire',
        'transfer_in' => 'Transfer intrare',
        'correction' => 'Corecție',
    ];
@endphp

@section('content')
<div class="resource-shell inventory-shell">
    <div class="resource-page-header">
        <div class="resource-page-heading">
            <span class="resource-page-icon"><i class="fa-solid fa-box"></i></span>
            <div>
                <h1>{{ $item->name }}</h1>
                <p>{{ $item->sku ?: 'Fără cod intern' }} · {{ $item->unit }}</p>
            </div>
        </div>
        <div class="resource-page-actions">
            <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Inventar</a>
        </div>
    </div>

    <form class="resource-filter-panel" method="get">
        <input type="hidden" name="filters_submitted" value="1">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="resource-filter-label">Locație</label>
                <select name="location_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Toate locațiile permise</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) $selectedLocationId === (int) $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="{{ route('inventory.show', ['catalogItem' => $item, 'filters_reset' => 1]) }}" class="btn btn-outline-secondary">Resetează filtrul</a>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Stoc curent pe loturi</strong>
                    <span class="badge text-bg-light">{{ $lots->count() }} loturi</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Lot / origine</th>
                                <th>Locație</th>
                                <th class="text-end">Cantitate</th>
                                <th>Expirare</th>
                                @if($canViewCommercial)<th class="text-end">Preț</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($lots as $lot)
                            @php($positiveBalances = $lot->balances->where('quantity', '>', 0))
                            @forelse($positiveBalances as $balance)
                                <tr>
                                    <td>
                                        <strong>{{ $lot->lot_code ?: ($lot->is_opening_balance ? 'Sold inițial' : 'Fără cod lot') }}</strong>
                                        <span class="resource-secondary">{{ $lot->supplier?->name ?: $lot->document_number ?: 'Fără furnizor/document' }}</span>
                                    </td>
                                    <td>{{ $balance->location?->code }} — {{ $balance->location?->name }}</td>
                                    <td class="text-end fw-semibold">{{ $formatQuantity($balance->quantity) }} {{ $item->unit }}</td>
                                    <td>{{ $lot->expires_at?->format('d.m.Y') ?? '—' }}</td>
                                    @if($canViewCommercial)
                                        <td class="text-end">{{ $lot->unit_price !== null ? number_format((float) $lot->unit_price, 4, ',', '.').' '.$lot->currency : '—' }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr class="text-secondary">
                                    <td>{{ $lot->lot_code ?: ($lot->is_opening_balance ? 'Sold inițial' : 'Lot epuizat') }}</td>
                                    <td colspan="{{ $canViewCommercial ? 4 : 3 }}">Fără cantitate curentă în locațiile selectate.</td>
                                </tr>
                            @endforelse
                        @empty
                            <tr><td colspan="{{ $canViewCommercial ? 5 : 4 }}" class="text-center text-secondary py-4">Nu există loturi în aria selectată.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white"><strong>Ce arată această fișă</strong></div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>stocul disponibil în locațiile pe care le poți consulta;</li>
                        <li>originea cantităților și loturile încă active;</li>
                        <li>istoricul intrărilor, consumurilor și transferurilor;</li>
                        <li>soldurile inițiale preluate fără a inventa documente sau prețuri.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="resource-table-card">
        <div class="resource-results-meta">
            <strong>Istoric mișcări</strong>
            <span>{{ $movements->total() }} înregistrări</span>
        </div>
        <div class="table-responsive">
            <table class="table resource-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Operațiune</th>
                        <th>Locație</th>
                        <th>Lot / document</th>
                        <th class="text-end">Mișcare</th>
                        <th>Înregistrat de</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($movements as $movement)
                    <tr>
                        <td class="text-nowrap">{{ $movement->occurred_at?->format('d.m.Y H:i') }}</td>
                        <td>
                            <span class="badge {{ (float) $movement->quantity >= 0 ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $movementLabels[$movement->movement_type] ?? ucfirst(str_replace('_', ' ', $movement->movement_type)) }}
                            </span>
                            @if($movement->reference_id && $movement->movement_type !== 'opening_balance')
                                <span class="resource-secondary">Operațiunea #{{ $movement->reference_id }}</span>
                            @endif
                        </td>
                        <td>{{ $movement->location?->code }} — {{ $movement->location?->name }}</td>
                        <td>{{ $movement->lot?->lot_code ?: ($movement->lot?->is_opening_balance ? 'Sold inițial' : ($movement->lot?->document_number ?: '—')) }}</td>
                        <td class="text-end fw-semibold {{ (float) $movement->quantity >= 0 ? 'text-success' : 'text-danger' }}">
                            {{ (float) $movement->quantity >= 0 ? '+' : '' }}{{ $formatQuantity($movement->quantity) }} {{ $item->unit }}
                        </td>
                        <td>{{ $movement->poster?->name ?? 'Sistem' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Nu există mișcări pentru filtrul selectat.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($movements->hasPages())<div class="resource-pagination">{{ $movements->links() }}</div>@endif
    </div>
</div>
@endsection
