@extends('layouts.app')

@section('title', 'Recepție nouă')

@php
    $selectedLocation = old('location_id', $intake?->location_id ?? $negotiatedOrder?->location_id);
    $prefilledLines = $negotiatedOrder
        ? $negotiatedOrder->lines->map(fn ($line) => [
            'catalog_item_id' => $line->catalog_item_id,
            'quantity' => $line->quantity,
            'lot_code' => '',
            'expires_at' => '',
            'unit_price' => $line->unit_price,
            'currency' => $negotiatedOrder->currency,
            'notes' => $line->notes,
        ])->all()
        : [[
        'catalog_item_id' => '',
        'quantity' => 1,
        'lot_code' => '',
        'expires_at' => '',
        'unit_price' => '',
        'currency' => 'RON',
        'notes' => '',
    ]];
    $oldLines = old('lines', $prefilledLines);
    $backRoute = $intake
        ? route('reception-intakes.show', $intake)
        : ($negotiatedOrder
            ? route('negotiated-orders.show', $negotiatedOrder)
            : route('supplier-receptions.index'));
@endphp

@section('content')
<x-resource-form-shell
    title="Recepție nouă"
    description="Poți introduce mai multe materiale. Stocul se actualizează numai când salvezi recepția."
    :back-route="$backRoute"
    icon="fa-truck-ramp-box"
>
    <form
        method="post"
        action="{{ route('supplier-receptions.store') }}"
        class="resource-form-card"
        enctype="multipart/form-data"
        data-reception-form
    >
        @csrf
        @if($intake)
            <input type="hidden" name="intake_id" value="{{ $intake->id }}">
            <div class="alert alert-info m-3 mb-0">
                <div class="fw-semibold"><i class="fa-solid fa-file-image me-1"></i>Recepție pornită din {{ $intake->number }}</div>
                <div class="small">
                    Cele {{ $intake->documents->count() }} documente existente vor fi legate automat de recepție.
                    Verifică informațiile și completează materialele.
                </div>
            </div>
        @endif
        @if($negotiatedOrder)
            <input type="hidden" name="negotiated_order_id" value="{{ $negotiatedOrder->id }}">
            <div class="alert alert-info m-3 mb-0">
                <div class="fw-semibold">
                    <i class="fa-solid fa-file-invoice-dollar me-1"></i>Recepție pornită din {{ $negotiatedOrder->number }}
                </div>
                <div class="small">
                    Locația, furnizorul, materialele, cantitățile și prețurile sunt precompletate.
                    Le poți corecta după livrarea reală. Comanda se închide numai după salvarea recepției.
                </div>
            </div>
        @endif

        <section class="resource-form-section">
            <div class="resource-form-section-title">Locație și document principal</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Locație</label>
                    <select name="location_id" class="form-select" data-tom-select required autofocus>
                        <option value="">Alege locația</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) $selectedLocation === (string) $location->id)>
                                {{ $location->code }} — {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Furnizor</label>
                    <select name="supplier_id" class="form-select" data-tom-select>
                        <option value="">Nespecificat</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $negotiatedOrder?->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Același material poate proveni de la furnizori diferiți.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tip document</label>
                    <select name="document_type" class="form-select" required>
                        <option value="aviz" @selected(old('document_type', 'aviz') === 'aviz')>Aviz</option>
                        <option value="factura" @selected(old('document_type') === 'factura')>Factură</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Număr document</label>
                    <input name="document_number" value="{{ old('document_number') }}" class="form-control" maxlength="255" placeholder="Opțional">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Data înregistrării</label>
                    <input class="form-control" value="{{ now()->format('d.m.Y H:i') }}" readonly>
                    <div class="form-text">Se salvează automat la confirmare.</div>
                </div>
                <div class="col-12">
                    <label class="form-label">Observații generale</label>
                    <textarea name="notes" class="form-control" rows="3" maxlength="4000" placeholder="Starea livrării, diferențe sau alte mențiuni">{{ old('notes', $negotiatedOrder?->notes) }}</textarea>
                </div>
            </div>
        </section>

        <section class="resource-form-section" data-reception-lines>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="resource-form-section-title mb-1">Materiale primite</div>
                    <div class="resource-secondary">Prețul este unitar, fără TVA. Lotul și expirarea sunt opționale.</div>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" data-add-reception-line>
                    <i class="fa-solid fa-plus me-1"></i>Adaugă material
                </button>
            </div>

            <div class="reception-line-list" data-reception-line-list>
                @foreach($oldLines as $index => $line)
                    <article class="reception-line-card" data-reception-line>
                        <div class="reception-line-number">Material {{ $loop->iteration }}</div>
                        <button type="button" class="btn btn-outline-danger btn-sm reception-line-remove" data-remove-reception-line title="Elimină">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <label class="form-label">Material</label>
                                <select name="lines[{{ $index }}][catalog_item_id]" class="form-select" data-reception-item required>
                                    <option value="">Alege materialul</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->id }}" data-unit="{{ $item->unit }}" @selected((string) ($line['catalog_item_id'] ?? '') === (string) $item->id)>
                                            {{ $item->name }} ({{ $item->unit }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">Cantitate</label>
                                <input name="lines[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" value="{{ $line['quantity'] ?? 1 }}" class="form-control" required>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">Preț unitar fără TVA</label>
                                <input name="lines[{{ $index }}][unit_price]" type="number" step="0.0001" min="0" value="{{ $line['unit_price'] ?? '' }}" class="form-control">
                            </div>
                            <div class="col-lg-3 col-md-4">
                                <label class="form-label">Monedă</label>
                                <select name="lines[{{ $index }}][currency]" class="form-select" required>
                                    @foreach($currencies as $currency)
                                        <option value="{{ $currency }}" @selected(($line['currency'] ?? 'RON') === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label">Număr lot</label>
                                <input name="lines[{{ $index }}][lot_code]" value="{{ $line['lot_code'] ?? '' }}" class="form-control" maxlength="120" placeholder="Opțional">
                            </div>
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Termen de expirare</label>
                                <input name="lines[{{ $index }}][expires_at]" type="date" value="{{ $line['expires_at'] ?? '' }}" class="form-control">
                            </div>
                            <div class="col-lg-5">
                                <label class="form-label">Observații material</label>
                                <input name="lines[{{ $index }}][notes]" value="{{ $line['notes'] ?? '' }}" class="form-control" maxlength="2000" placeholder="Opțional">
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <template data-reception-line-template>
                <article class="reception-line-card" data-reception-line>
                    <div class="reception-line-number">Material</div>
                    <button type="button" class="btn btn-outline-danger btn-sm reception-line-remove" data-remove-reception-line title="Elimină">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label">Material</label>
                            <select name="lines[__INDEX__][catalog_item_id]" class="form-select" data-reception-item required>
                                <option value="">Alege materialul</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-unit="{{ $item->unit }}">{{ $item->name }} ({{ $item->unit }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Cantitate</label>
                            <input name="lines[__INDEX__][quantity]" type="number" step="0.001" min="0.001" value="1" class="form-control" required>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Preț unitar fără TVA</label>
                            <input name="lines[__INDEX__][unit_price]" type="number" step="0.0001" min="0" class="form-control">
                        </div>
                        <div class="col-lg-3 col-md-4">
                            <label class="form-label">Monedă</label>
                            <select name="lines[__INDEX__][currency]" class="form-select" required>
                                @foreach($currencies as $currency)<option value="{{ $currency }}" @selected($currency === 'RON')>{{ $currency }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label">Număr lot</label>
                            <input name="lines[__INDEX__][lot_code]" class="form-control" maxlength="120" placeholder="Opțional">
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Termen de expirare</label>
                            <input name="lines[__INDEX__][expires_at]" type="date" class="form-control">
                        </div>
                        <div class="col-lg-5">
                            <label class="form-label">Observații material</label>
                            <input name="lines[__INDEX__][notes]" class="form-control" maxlength="2000" placeholder="Opțional">
                        </div>
                    </div>
                </article>
            </template>
        </section>

        <x-reception-attachment-fields
            :document-types="$documentTypes"
            title="Documente suplimentare"
        />

        <div class="resource-form-actions-bar">
            <a href="{{ $backRoute }}" class="btn btn-outline-secondary">Renunță</a>
            <button class="btn btn-success">
                <i class="fa-solid fa-check me-1"></i>Confirmă recepția și actualizează stocul
            </button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
