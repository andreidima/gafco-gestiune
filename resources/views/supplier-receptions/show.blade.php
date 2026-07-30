@extends('layouts.app')

@section('title', $reception->number)

@php
    $formatQuantity = fn ($value) => \App\Support\LocalizedNumber::quantity($value);
@endphp

@section('content')
<div class="resource-shell">
    <div class="resource-page-header">
        <div class="resource-page-heading">
            <span class="resource-page-icon"><i class="fa-solid fa-truck-ramp-box"></i></span>
            <div>
                <h1>{{ $reception->number }}</h1>
                <p>{{ $reception->location?->code }} — {{ $reception->location?->name }} · {{ $reception->received_at?->format('d.m.Y H:i') }}</p>
            </div>
        </div>
        <div class="resource-page-actions">
            <x-back-link :fallback="route('supplier-receptions.index')" class="btn-sm" />
            @if($canEdit)
                <a href="{{ route('supplier-receptions.edit', $reception) }}" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-pen me-1"></i>Completează detaliile
                </a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="resource-form-card h-100">
                <section class="resource-form-section border-0">
                    <div class="resource-form-section-title">Document principal</div>
                    <dl class="reception-detail-list">
                        <div><dt>Furnizor</dt><dd>{{ $reception->supplier?->name ?? 'Nespecificat' }}</dd></div>
                        <div><dt>Tip</dt><dd>{{ $reception->document_type === 'factura' ? 'Factură' : 'Aviz' }}</dd></div>
                        <div><dt>Număr</dt><dd>{{ $reception->document_number ?: '—' }}</dd></div>
                        <div><dt>Înregistrat de</dt><dd>{{ $reception->receiver?->name ?? '—' }}</dd></div>
                        @if($reception->relationLoaded('negotiatedOrder') && $reception->negotiatedOrder)
                            <div>
                                <dt>Comandă negociată</dt>
                                <dd><a href="{{ route('negotiated-orders.show', $reception->negotiatedOrder) }}">{{ $reception->negotiatedOrder->number }}</a></dd>
                            </div>
                        @endif
                        @if($reception->intakes->isNotEmpty())
                            <div>
                                <dt>Sursă</dt>
                                <dd><a href="{{ route('reception-intakes.show', $reception->intakes->first()) }}">{{ $reception->intakes->first()->number }}</a></dd>
                            </div>
                        @endif
                    </dl>
                    @if($reception->notes)<p class="mt-3 mb-0">{{ $reception->notes }}</p>@endif
                </section>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="resource-form-card h-100">
                <section class="resource-form-section border-0">
                    <div class="resource-form-section-title">Materiale recepționate</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Material</th>
                                    <th class="text-end">Cantitate</th>
                                    <th>Lot</th>
                                    <th>Expirare</th>
                                    @if($canViewCommercial)<th class="text-end">Preț unitar fără TVA</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($reception->lines as $line)
                                <tr>
                                    <td>
                                        <strong>{{ $line->catalogItem?->name ?? 'Articol indisponibil' }}</strong>
                                        @if($line->notes)<span class="resource-secondary">{{ $line->notes }}</span>@endif
                                    </td>
                                    <td class="text-end text-nowrap">{{ $formatQuantity($line->quantity) }} {{ $line->unit }}</td>
                                    <td>{{ $line->lot_code ?: '—' }}</td>
                                    <td>{{ $line->expires_at?->format('d.m.Y') ?? '—' }}</td>
                                    @if($canViewCommercial)
                                        <td class="text-end text-nowrap">
                                            {{ $line->unit_price !== null ? number_format((float) $line->unit_price, 4, ',', '.').' '.$line->currency : '—' }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div class="resource-form-card">
        <section class="resource-form-section border-0">
            <div class="resource-form-section-title">Documente și fotografii</div>
            @if($reception->documents->isEmpty())
                <p class="text-secondary mb-0">Nu există fișiere atașate.</p>
            @else
                <div class="reception-document-grid">
                    @foreach($reception->documents as $document)
                        <a href="{{ route('reception-documents.download', $document) }}" class="reception-document-card">
                            <span class="reception-document-icon">
                                <i class="fa-solid {{ str_contains($document->mime_type ?? '', 'pdf') ? 'fa-file-pdf' : 'fa-file-image' }}"></i>
                            </span>
                            <span class="min-w-0">
                                <strong>{{ $document->label() }}</strong>
                                <span>{{ $document->original_name }}</span>
                                <small>{{ number_format($document->size_bytes / 1024, 0, ',', '.') }} KB</small>
                            </span>
                            <i class="fa-solid fa-download"></i>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
