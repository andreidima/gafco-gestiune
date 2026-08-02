@php
    $currentOrder = $order ?? null;
    $formLines = old('lines', $currentOrder
        ? $currentOrder->lines->map(fn ($line) => [
            'catalog_item_id' => $line->catalog_item_id,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'notes' => $line->notes,
        ])->all()
        : [[
            'catalog_item_id' => '',
            'quantity' => 1,
            'unit_price' => '',
            'notes' => '',
        ]]);
@endphp

<form method="post" action="{{ $action }}" class="resource-form-card">
    @csrf
    @if($method !== 'POST')
        @method($method)
    @endif

    <section class="resource-form-section">
        <div class="resource-form-section-title">Datele comenzii</div>
        <div class="row g-3">
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Locația de destinație</label>
                <select name="location_id" class="form-select" data-tom-select required autofocus>
                    <option value="">Alege locația</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) old('location_id', $currentOrder?->location_id) === (string) $location->id)>
                            {{ $location->code }} — {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Furnizor</label>
                <select name="supplier_id" class="form-select" data-tom-select>
                    <option value="">Nespecificat încă</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" data-search="{{ $supplier->cui }} {{ $supplier->registration_number }}" @selected((string) old('supplier_id', $currentOrder?->supplier_id) === (string) $supplier->id)>
                            {{ $supplier->name }}{{ $supplier->active ? '' : ' (inactiv)' }}
                        </option>
                    @endforeach
                </select>
                @can('suppliers.manage')
                    <div class="form-text">
                        Nu găsești furnizorul?
                        <a href="{{ route('suppliers.create') }}" target="_blank" rel="noopener">Adaugă-l în lista de furnizori</a>.
                    </div>
                @endcan
                <div class="form-text">Poți salva comanda și completa furnizorul ulterior.</div>
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Moneda comenzii</label>
                <select name="currency" class="form-select" required>
                    @foreach($currencies as $currency)
                        <option value="{{ $currency }}" @selected(old('currency', $currentOrder?->currency ?? 'RON') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">Observații generale</label>
                <textarea name="notes" class="form-control" rows="3" maxlength="4000" placeholder="Condiții negociate, persoană de contact sau alte mențiuni">{{ old('notes', $currentOrder?->notes) }}</textarea>
            </div>
        </div>
    </section>

    <section class="resource-form-section" data-reception-lines>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <div class="resource-form-section-title mb-1">Materiale comandate</div>
                <div class="resource-secondary">Prețurile sunt unitare, fără TVA, în moneda aleasă pentru comandă.</div>
            </div>
        </div>

        <div class="reception-line-list" data-reception-line-list>
            @foreach($formLines as $index => $line)
                <article class="reception-line-card" data-reception-line>
                    <div class="reception-line-number">Material {{ $loop->iteration }}</div>
                    <button type="button" class="btn btn-outline-danger btn-sm reception-line-remove" data-remove-reception-line title="Elimină">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <label class="form-label">Material</label>
                            <select name="lines[{{ $index }}][catalog_item_id]" class="form-select" data-reception-item data-tom-select required>
                                <option value="">Alege materialul</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" data-search="{{ $item->sku }} {{ $item->barcode }}" @selected((string) ($line['catalog_item_id'] ?? '') === (string) $item->id)>
                                        {{ $item->name }} ({{ $item->unit }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Cantitate</label>
                            <input name="lines[{{ $index }}][quantity]" type="number" data-quantity-stepper step="0.001" min="0.001" value="{{ $line['quantity'] ?? 1 }}" class="form-control" required>
                        </div>
                        <div class="col-lg-2 col-md-4">
                            <label class="form-label">Preț unitar</label>
                            <input name="lines[{{ $index }}][unit_price]" type="number" step="0.0001" min="0" value="{{ $line['unit_price'] ?? '' }}" class="form-control" required>
                        </div>
                        <div class="col-lg-3 col-md-4">
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
                        <select name="lines[__INDEX__][catalog_item_id]" class="form-select" data-reception-item data-tom-select required>
                            <option value="">Alege materialul</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}" data-search="{{ $item->sku }} {{ $item->barcode }}">{{ $item->name }} ({{ $item->unit }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Cantitate</label>
                        <input name="lines[__INDEX__][quantity]" type="number" data-quantity-stepper step="0.001" min="0.001" value="1" class="form-control" required>
                    </div>
                    <div class="col-lg-2 col-md-4">
                        <label class="form-label">Preț unitar</label>
                        <input name="lines[__INDEX__][unit_price]" type="number" step="0.0001" min="0" class="form-control" required>
                    </div>
                    <div class="col-lg-3 col-md-4">
                        <label class="form-label">Observații material</label>
                        <input name="lines[__INDEX__][notes]" class="form-control" maxlength="2000" placeholder="Opțional">
                    </div>
                </div>
            </article>
        </template>

        <div class="repeatable-list-add">
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-reception-line>
                <i class="fa-solid fa-plus me-1"></i>Adaugă material
            </button>
        </div>
    </section>

    <div class="resource-form-actions-bar">
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary">Renunță</a>
        <button class="btn btn-success">
            <i class="fa-solid fa-check me-1"></i>{{ $submitLabel }}
        </button>
    </div>
</form>
