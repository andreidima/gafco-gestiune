@extends('layouts.app')

@section('title', 'Completează '.$reception->number)

@section('content')
<x-resource-form-shell
    :title="'Completează '.$reception->number"
    description="Cantitatea, materialul și locația rămân neschimbate. Modificările de detaliu sunt păstrate în istoricul intern."
    :back-route="route('supplier-receptions.show', $reception)"
    icon="fa-pen-to-square"
>
    <form
        method="post"
        action="{{ route('supplier-receptions.update', $reception) }}"
        class="resource-form-card"
        enctype="multipart/form-data"
    >
        @csrf
        @method('PUT')

        <section class="resource-form-section">
            <div class="resource-form-section-title">Recepție</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Locație</label>
                    <input class="form-control" value="{{ $reception->location?->code }} — {{ $reception->location?->name }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Furnizor</label>
                    @if($canEditAll)
                        <select name="supplier_id" class="form-select" data-tom-select>
                            <option value="">Nespecificat</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id', $reception->supplier_id) === (string) $supplier->id)>{{ $supplier->name }}{{ $supplier->active ? '' : ' (inactiv)' }}</option>
                            @endforeach
                        </select>
                    @else
                        <input class="form-control" value="{{ $reception->supplier?->name ?? 'Nespecificat' }}" readonly>
                    @endif
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tip document</label>
                    @if($canEditAll)
                        <select name="document_type" class="form-select">
                            <option value="aviz" @selected(old('document_type', $reception->document_type) === 'aviz')>Aviz</option>
                            <option value="factura" @selected(old('document_type', $reception->document_type) === 'factura')>Factură</option>
                        </select>
                    @else
                        <input class="form-control" value="{{ $reception->document_type === 'factura' ? 'Factură' : 'Aviz' }}" readonly>
                    @endif
                </div>
                <div class="col-md-2">
                    <label class="form-label">Număr</label>
                    @if($canEditAll)
                        <input name="document_number" value="{{ old('document_number', $reception->document_number) }}" class="form-control" maxlength="255">
                    @else
                        <input class="form-control" value="{{ $reception->document_number }}" readonly>
                    @endif
                </div>
                @if($canEditAll)
                    <div class="col-12">
                        <label class="form-label">Observații generale</label>
                        <textarea name="notes" class="form-control" rows="3" maxlength="4000">{{ old('notes', $reception->notes) }}</textarea>
                    </div>
                @endif
            </div>
        </section>

        <section class="resource-form-section">
            <div class="resource-form-section-title">Detalii materiale</div>
            <div class="reception-line-list">
                @foreach($reception->lines as $index => $line)
                    <article class="reception-line-card">
                        <input type="hidden" name="lines[{{ $index }}][id]" value="{{ $line->id }}">
                        <div class="reception-line-number">{{ $line->catalogItem?->name }}</div>
                        <div class="row g-3">
                            <div class="col-lg-3 col-md-6">
                                <label class="form-label">Material și cantitate</label>
                                <input class="form-control" value="{{ $line->catalogItem?->name }} · {{ \App\Support\LocalizedNumber::quantity($line->quantity) }} {{ $line->unit }}" readonly>
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label">Număr lot</label>
                                @if($canEditAll)
                                    <input name="lines[{{ $index }}][lot_code]" value="{{ old("lines.$index.lot_code", $line->lot_code) }}" class="form-control" maxlength="120">
                                @else
                                    <input class="form-control" value="{{ $line->lot_code }}" readonly>
                                @endif
                            </div>
                            <div class="col-lg-2 col-md-6">
                                <label class="form-label">Expirare</label>
                                <input name="lines[{{ $index }}][expires_at]" type="date" value="{{ old("lines.$index.expires_at", $line->expires_at?->format('Y-m-d')) }}" class="form-control">
                            </div>
                            @if($canEditAll || $canViewCommercial)
                                <div class="col-lg-2 col-md-6">
                                    <label class="form-label">Preț unitar fără TVA</label>
                                    @if($canEditAll)
                                        <input name="lines[{{ $index }}][unit_price]" type="number" step="0.0001" min="0" value="{{ old("lines.$index.unit_price", $line->unit_price) }}" class="form-control">
                                    @else
                                        <input class="form-control" value="{{ $line->unit_price }}" readonly>
                                    @endif
                                </div>
                                <div class="col-lg-1 col-md-4">
                                    <label class="form-label">Monedă</label>
                                    @if($canEditAll)
                                        <select name="lines[{{ $index }}][currency]" class="form-select">
                                            @foreach($currencies as $currency)
                                                <option value="{{ $currency }}" @selected(old("lines.$index.currency", $line->currency) === $currency)>{{ $currency }}</option>
                                            @endforeach
                                        </select>
                                    @else
                                        <input class="form-control" value="{{ $line->currency }}" readonly>
                                    @endif
                                </div>
                            @endif
                            <div class="col-lg-2 col-md-8">
                                <label class="form-label">Observații</label>
                                @if($canEditAll)
                                    <input name="lines[{{ $index }}][notes]" value="{{ old("lines.$index.notes", $line->notes) }}" class="form-control" maxlength="2000">
                                @else
                                    <input class="form-control" value="{{ $line->notes }}" readonly>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        @if($canEditAll)
            <section class="resource-form-section">
                <div class="resource-form-section-title">Fișiere existente</div>
                @if($reception->documents->isEmpty())
                    <p class="text-secondary mb-0">Nu există fișiere atașate.</p>
                @else
                    <div class="reception-document-grid">
                        @foreach($reception->documents as $document)
                            <a href="{{ route('reception-documents.download', $document) }}" class="reception-document-card">
                                <span class="reception-document-icon"><i class="fa-solid fa-paperclip"></i></span>
                                <span class="min-w-0"><strong>{{ $document->label() }}</strong><span>{{ $document->original_name }}</span></span>
                                <i class="fa-solid fa-download"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
            <x-reception-attachment-fields :document-types="$documentTypes" title="Adaugă alte documente" />
        @endif

        <div class="resource-form-actions-bar">
            <a href="{{ route('supplier-receptions.show', $reception) }}" class="btn btn-outline-secondary">Renunță</a>
            <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Salvează detaliile</button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
