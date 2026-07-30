@extends('layouts.app')

@section('title', $order->number)

@php
    $formatQuantity = fn ($value) => \App\Support\LocalizedNumber::quantity($value);
    $formatMoney = fn ($value) => number_format((float) $value, 2, ',', '.');
    $total = $order->lines->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price);
@endphp

@section('content')
<div class="resource-form-shell">
    <div class="resource-form-header">
        <div class="resource-page-heading">
            <span class="resource-page-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
            <div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <h1>{{ $order->number }}</h1>
                    <x-status :status="$order->status" />
                </div>
                <p>{{ $order->supplier?->name ?? 'Furnizor nespecificat' }} · {{ $order->location?->code }} — {{ $order->location?->name }}</p>
            </div>
        </div>
        <div class="resource-page-actions">
            <x-back-link :fallback="route('negotiated-orders.index')" class="btn-sm" />
            @if($order->isCreated())
                <a href="{{ route('negotiated-orders.edit', $order) }}" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen me-1"></i>Modifică</a>
                <a href="{{ route('supplier-receptions.create', ['negotiated_order_id' => $order->id]) }}" class="btn btn-success btn-sm"><i class="fa-solid fa-truck-ramp-box me-1"></i>Transformă în recepție</a>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelOrderModal"><i class="fa-solid fa-ban me-1"></i>Anulează</button>
            @endif
        </div>
    </div>

    <div class="row g-4 mx-0">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Materiale comandate</h2>
                    <div class="table-responsive d-none d-md-block">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">Cantitate</th>
                                    <th class="text-end">Preț unitar fără TVA</th>
                                    <th class="text-end">Valoare fără TVA</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->lines as $line)
                                    <tr>
                                        <td>
                                            <div class="resource-cell-stack">
                                                <span class="resource-primary">{{ $line->catalogItem?->name ?? 'Material indisponibil' }}</span>
                                                @if($line->notes)<span class="resource-secondary">{{ $line->notes }}</span>@endif
                                            </div>
                                        </td>
                                        <td class="text-end">{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</td>
                                        <td class="text-end">{{ $formatMoney($line->unit_price) }} {{ $order->currency }}</td>
                                        <td class="text-end fw-semibold">{{ $formatMoney((float) $line->quantity * (float) $line->unit_price) }} {{ $order->currency }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total fără TVA</th>
                                    <th class="text-end">{{ $formatMoney($total) }} {{ $order->currency }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="d-grid gap-3 d-md-none">
                        @foreach($order->lines as $line)
                            <div class="border rounded-3 p-3">
                                <div class="fw-bold">{{ $line->catalogItem?->name ?? 'Material indisponibil' }}</div>
                                @if($line->notes)<div class="resource-secondary mb-3">{{ $line->notes }}</div>@endif
                                <div class="row g-2 mt-1">
                                    <div class="col-6">
                                        <div class="resource-mobile-card-label">Cantitate</div>
                                        <strong>{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</strong>
                                    </div>
                                    <div class="col-6 text-end">
                                        <div class="resource-mobile-card-label">Preț unitar fără TVA</div>
                                        <strong>{{ $formatMoney($line->unit_price) }} {{ $order->currency }}</strong>
                                    </div>
                                </div>
                                <div class="border-top mt-3 pt-2 d-flex justify-content-between gap-3">
                                    <span>Valoare fără TVA</span>
                                    <strong>{{ $formatMoney((float) $line->quantity * (float) $line->unit_price) }} {{ $order->currency }}</strong>
                                </div>
                            </div>
                        @endforeach
                        <div class="d-flex justify-content-between gap-3 px-2">
                            <strong>Total fără TVA</strong>
                            <strong>{{ $formatMoney($total) }} {{ $order->currency }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Detalii</h2>
                    <dl class="reception-detail-list">
                        <div><dt>Destinație</dt><dd>{{ $order->location?->code }} — {{ $order->location?->name }}</dd></div>
                        <div><dt>Furnizor</dt><dd>{{ $order->supplier?->name ?? 'Nespecificat' }}</dd></div>
                        <div><dt>Creată de</dt><dd>{{ $order->creator?->name ?? '—' }}</dd></div>
                        <div><dt>Data creării</dt><dd>{{ $order->created_at->format('d.m.Y H:i') }}</dd></div>
                        @if($order->closed_at)
                            <div><dt>Închisă de</dt><dd>{{ $order->closer?->name ?? '—' }}</dd></div>
                            <div><dt>Data închiderii</dt><dd>{{ $order->closed_at->format('d.m.Y H:i') }}</dd></div>
                        @endif
                    </dl>
                    @if($order->notes)
                        <hr>
                        <h3 class="h6">Observații</h3>
                        <p class="mb-0">{{ $order->notes }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($order->status === 'closed')
        <div class="alert {{ $order->closure_type === 'cancelled' ? 'alert-secondary' : 'alert-success' }} mt-4 mb-0">
            @if($order->closure_type === 'cancelled')
                <div class="fw-semibold"><i class="fa-solid fa-ban me-1"></i>Comandă închisă prin anulare</div>
                <div>{{ $order->closure_reason }}</div>
            @elseif($order->closure_type === 'reception' && $order->reception)
                <div class="fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Comandă transformată în recepție</div>
                <div>Stocul a fost actualizat prin <a href="{{ route('supplier-receptions.show', $order->reception) }}">{{ $order->reception->number }}</a>.</div>
            @endif
        </div>
    @endif
</div>

@if($order->isCreated())
    <div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="post" action="{{ route('negotiated-orders.cancel', $order) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="cancelOrderModalLabel">Anulează {{ $order->number }}</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
                </div>
                <div class="modal-body">
                    <p>Comanda va fi închisă și păstrată în istoric. Stocul nu va fi modificat.</p>
                    <label class="form-label">Motivul anulării</label>
                    <textarea name="closure_reason" class="form-control" rows="4" minlength="5" maxlength="4000" required placeholder="De ce nu mai este continuată comanda?">{{ old('closure_reason') }}</textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Renunță</button>
                    <button class="btn btn-danger">Confirmă anularea</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
