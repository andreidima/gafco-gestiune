@extends('layouts.app')

@php
    $editing = isset($report) && $report;
    $locationId = old('location_id', $editing ? $report->location_id : '');
    $initialLines = old('lines');
    if (!$initialLines) {
        $initialLines = $editing
            ? $report->lines->map(fn ($line) => [
                'catalog_item_id' => $line->catalog_item_id,
                'quantity' => (float) $line->quantity,
                'notes' => $line->notes,
                'allocations' => [],
            ])->values()->all()
            : [['catalog_item_id' => '', 'quantity' => 1, 'notes' => '', 'allocations' => []]];
    }
@endphp

@section('title', $editing ? 'Corectează consumul '.$report->number : 'Raportează consum')

@section('content')
<x-resource-form-shell
    :title="$editing ? 'Corectează consumul '.$report->number : 'Raportează consum'"
    :description="$editing
        ? 'Corecția păstrează versiunea anterioară și înregistrează separat ajustările de stoc.'
        : 'Poți include mai multe materiale; toate cantitățile se scad într-o singură operațiune.'"
    :back-route="route('consumption-reports.index')"
    icon="fa-clipboard-check"
>
    <form
        method="post"
        action="{{ $editing ? route('consumption-reports.update', $report) : route('consumption-reports.store') }}"
        class="resource-form-card"
        data-consumption-form
        data-stock-url="{{ $stockOptionsUrl }}"
        data-allocation-url="{{ $allocationProposalUrl }}"
        data-report-id="{{ $editing ? $report->id : '' }}"
    >
        @csrf
        @if($editing) @method('put') @endif

        <div class="resource-form-section">
            <div class="resource-form-section-title">{{ $editing ? 'Datele corectate' : 'Consum raportat' }}</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Locație</label>
                    <select name="location_id" class="form-select" data-tom-select required autofocus>
                        <option value="">Alege locația</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) $locationId === (string) $location->id)>
                                {{ $location->code }} - {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Observații generale</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Lucrare, echipă, zonă sau explicații">{{ old('notes', $editing ? $report->notes : '') }}</textarea>
                </div>
                @if($editing)
                    <div class="col-12">
                        <label class="form-label">Motivul corecției</label>
                        <textarea name="correction_reason" class="form-control" rows="2" required placeholder="Descrie eroarea și ce trebuie corectat.">{{ old('correction_reason') }}</textarea>
                        <div class="form-text">Motivul, utilizatorul și momentul modificării rămân în istoricul raportului.</div>
                    </div>
                @endif
            </div>
        </div>

        <section class="resource-form-section">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <div class="resource-form-section-title mb-1">Materiale consumate</div>
                    <div class="resource-secondary">Sunt afișate numai materialele cu stoc disponibil în locația aleasă. Loturile sunt propuse FEFO, apoi FIFO.</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-consumption-line>
                    <i class="fa-solid fa-plus me-1"></i>Adaugă material
                </button>
            </div>

            <div class="small text-muted mt-2" data-consumption-stock-state>Alege locația pentru a încărca materialele disponibile.</div>
            <div class="mt-3" data-consumption-lines>
                @foreach($initialLines as $index => $line)
                    <article class="consumption-line border rounded-3 p-3 mb-3" data-consumption-line>
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <strong class="consumption-line-number">Material {{ $index + 1 }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-consumption-line aria-label="Șterge materialul">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-5">
                                <label class="form-label">Material</label>
                                <select
                                    name="lines[{{ $index }}][catalog_item_id]"
                                    class="form-select consumption-line-item"
                                    data-selected-value="{{ $line['catalog_item_id'] ?? '' }}"
                                    data-tom-select
                                    required
                                >
                                    <option value="">Alege materialul</option>
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">Disponibil</label>
                                <div class="input-group">
                                    <input class="form-control consumption-line-available" value="—" readonly tabindex="-1" aria-label="Stoc disponibil">
                                    <span class="input-group-text consumption-line-unit">u.m.</span>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4">
                                <label class="form-label">Cantitate</label>
                                <input name="lines[{{ $index }}][quantity]" type="number" step="0.001" min="0.001" value="{{ $line['quantity'] ?? 1 }}" class="form-control consumption-line-quantity" required>
                            </div>
                            <div class="col-lg-3 col-md-4">
                                <label class="form-label">Observații material</label>
                                <input name="lines[{{ $index }}][notes]" value="{{ $line['notes'] ?? '' }}" class="form-control" placeholder="Opțional">
                            </div>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <span class="small text-muted" data-line-allocation-state>Alege materialul și cantitatea pentru propunerea pe loturi.</span>
                            <span class="badge text-bg-light border" data-line-allocation-total>Fără propunere</span>
                        </div>
                        <div class="table-responsive d-none mt-2" data-line-allocation-wrap>
                            <table class="table table-sm align-middle consumption-allocation-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Lot / sursă</th>
                                        <th>Furnizor</th>
                                        <th>Intrare</th>
                                        <th>Expirare</th>
                                        <th class="text-end">Disponibil</th>
                                        <th class="text-end">De scăzut</th>
                                    </tr>
                                </thead>
                                <tbody data-line-allocation-rows></tbody>
                            </table>
                        </div>
                        <script type="application/json" data-old-line-allocations>@json($line['allocations'] ?? [])</script>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="resource-form-actions-bar">
            <a href="{{ route('consumption-reports.index') }}" class="btn btn-outline-secondary">Renunță</a>
            <button class="btn btn-success">
                <i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salvează corecția' : 'Înregistrează consumul' }}
            </button>
        </div>
    </form>

    @if($editing)
        <section class="resource-form-card mt-3">
            <div class="resource-form-section">
                <div class="resource-form-section-title">Istoric corecții</div>
                @forelse($report->revisions as $revision)
                    <div class="border rounded-3 p-3 mb-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <strong>Versiunea {{ $revision->revision }}</strong>
                            <span class="resource-secondary">{{ $revision->changed_at?->format('d.m.Y H:i') }} · {{ $revision->changedBy?->name ?? 'Utilizator indisponibil' }}</span>
                        </div>
                        <div class="mt-1">{{ $revision->reason }}</div>
                    </div>
                @empty
                    <div class="text-muted">Raportul nu a mai fost corectat.</div>
                @endforelse
            </div>
        </section>
    @endif
</x-resource-form-shell>

<template data-consumption-line-template>
    <article class="consumption-line border rounded-3 p-3 mb-3" data-consumption-line>
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <strong class="consumption-line-number">Material</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-consumption-line aria-label="Șterge materialul">
                <i class="fa-solid fa-trash"></i>
            </button>
        </div>
        <div class="row g-3 align-items-end">
            <div class="col-lg-5">
                <label class="form-label">Material</label>
                <select data-name="catalog_item_id" class="form-select consumption-line-item" data-tom-select required>
                    <option value="">Alege materialul</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Disponibil</label>
                <div class="input-group">
                    <input class="form-control consumption-line-available" value="—" readonly tabindex="-1" aria-label="Stoc disponibil">
                    <span class="input-group-text consumption-line-unit">u.m.</span>
                </div>
            </div>
            <div class="col-lg-2 col-md-4">
                <label class="form-label">Cantitate</label>
                <input data-name="quantity" type="number" step="0.001" min="0.001" value="1" class="form-control consumption-line-quantity" required>
            </div>
            <div class="col-lg-3 col-md-4">
                <label class="form-label">Observații material</label>
                <input data-name="notes" class="form-control" placeholder="Opțional">
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
            <span class="small text-muted" data-line-allocation-state>Alege materialul și cantitatea pentru propunerea pe loturi.</span>
            <span class="badge text-bg-light border" data-line-allocation-total>Fără propunere</span>
        </div>
        <div class="table-responsive d-none mt-2" data-line-allocation-wrap>
            <table class="table table-sm align-middle consumption-allocation-table mb-0">
                <thead>
                    <tr>
                        <th>Lot / sursă</th>
                        <th>Furnizor</th>
                        <th>Intrare</th>
                        <th>Expirare</th>
                        <th class="text-end">Disponibil</th>
                        <th class="text-end">De scăzut</th>
                    </tr>
                </thead>
                <tbody data-line-allocation-rows></tbody>
            </table>
        </div>
        <script type="application/json" data-old-line-allocations>[]</script>
    </article>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-consumption-form]');
    if (!form) return;

    const location = form.querySelector('[name="location_id"]');
    const list = form.querySelector('[data-consumption-lines]');
    const template = document.querySelector('[data-consumption-line-template]');
    const stockState = form.querySelector('[data-consumption-stock-state]');
    let stockItems = [];
    let stockRequest;

    const formatQuantity = value => Number(value).toLocaleString('ro-RO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });

    const nextIndex = () => {
        const indexes = [...list.querySelectorAll('[name^="lines["]')]
            .map(input => Number(input.name.match(/^lines\[(\d+)]/)?.[1] ?? -1));
        return indexes.length ? Math.max(...indexes) + 1 : 0;
    };

    const lineIndex = row => Number(row.querySelector('[name^="lines["]')?.name.match(/^lines\[(\d+)]/)?.[1] ?? 0);

    const renumberVisuals = () => {
        const rows = [...list.querySelectorAll('[data-consumption-line]')];
        rows.forEach((row, index) => {
            row.querySelector('.consumption-line-number').textContent = `Material ${index + 1}`;
            row.querySelector('[data-remove-consumption-line]').disabled = rows.length === 1;
        });
    };

    const oldAllocations = row => {
        try {
            return JSON.parse(row.querySelector('[data-old-line-allocations]')?.textContent || '[]');
        } catch {
            return [];
        }
    };

    const selectedStock = row => stockItems.find(item =>
        String(item.id) === String(row.querySelector('.consumption-line-item').value)
    );

    const syncAvailable = row => {
        const item = selectedStock(row);
        const available = row.querySelector('.consumption-line-available');
        const unit = row.querySelector('.consumption-line-unit');
        const quantity = row.querySelector('.consumption-line-quantity');
        available.value = item ? formatQuantity(item.available) : '—';
        unit.textContent = item?.unit || 'u.m.';
        if (item) quantity.max = item.available;
        else quantity.removeAttribute('max');
    };

    const updateAllocationTotal = row => {
        const requested = Number(row.querySelector('.consumption-line-quantity').value || 0);
        const allocated = [...row.querySelectorAll('[data-allocation-quantity]')]
            .reduce((total, input) => total + Number(input.value || 0), 0);
        const badge = row.querySelector('[data-line-allocation-total]');
        const matches = requested > 0 && Math.abs(requested - allocated) <= 0.0005;
        badge.className = `badge ${matches ? 'text-bg-success' : 'text-bg-warning'}`;
        badge.textContent = `${formatQuantity(allocated)} din ${formatQuantity(requested)} alocate`;
    };

    const showAllocationState = (row, message, tone = 'muted') => {
        row.querySelector('[data-line-allocation-rows]').replaceChildren();
        row.querySelector('[data-line-allocation-wrap]').classList.add('d-none');
        const state = row.querySelector('[data-line-allocation-state]');
        state.className = `small text-${tone}`;
        state.textContent = message;
        const badge = row.querySelector('[data-line-allocation-total]');
        badge.className = 'badge text-bg-light border';
        badge.textContent = 'Fără propunere';
    };

    const renderAllocations = (row, allocations) => {
        const index = lineIndex(row);
        const rows = row.querySelector('[data-line-allocation-rows]');
        const previous = oldAllocations(row);
        rows.replaceChildren();

        allocations.forEach((allocation, allocationIndex) => {
            const tr = document.createElement('tr');
            const values = [
                allocation.label,
                allocation.supplier || '—',
                allocation.received_at || '—',
                allocation.expires_at || '—',
            ];
            values.forEach((value, position) => {
                const cell = document.createElement('td');
                if (position === 0) {
                    const strong = document.createElement('strong');
                    strong.textContent = value;
                    cell.append(strong);
                } else {
                    cell.textContent = value;
                }
                tr.append(cell);
            });

            const available = document.createElement('td');
            available.className = 'text-end text-nowrap';
            available.textContent = formatQuantity(allocation.available);

            const amount = document.createElement('td');
            amount.className = 'text-end';
            const lotId = document.createElement('input');
            lotId.type = 'hidden';
            lotId.name = `lines[${index}][allocations][${allocationIndex}][inventory_lot_id]`;
            lotId.value = allocation.inventory_lot_id;
            const quantity = document.createElement('input');
            quantity.type = 'number';
            quantity.name = `lines[${index}][allocations][${allocationIndex}][quantity]`;
            quantity.className = 'form-control form-control-sm text-end consumption-allocation-input';
            quantity.step = '0.001';
            quantity.min = '0';
            quantity.max = allocation.available;
            quantity.dataset.allocationQuantity = '';
            const old = previous.find(entry => Number(entry.inventory_lot_id) === Number(allocation.inventory_lot_id));
            quantity.value = old?.quantity ?? allocation.quantity;
            quantity.addEventListener('input', () => updateAllocationTotal(row));
            amount.append(lotId, quantity);
            tr.append(available, amount);
            rows.append(tr);
        });

        row.querySelector('[data-old-line-allocations]').textContent = '[]';
        row.querySelector('[data-line-allocation-state]').classList.add('d-none');
        row.querySelector('[data-line-allocation-wrap]').classList.remove('d-none');
        updateAllocationTotal(row);
    };

    const loadAllocation = async row => {
        row._allocationRequest?.abort();
        const item = row.querySelector('.consumption-line-item').value;
        const quantity = Number(row.querySelector('.consumption-line-quantity').value || 0);
        if (!location.value || !item || quantity <= 0) {
            showAllocationState(row, 'Alege materialul și o cantitate mai mare decât zero.');
            return;
        }

        row._allocationRequest = new AbortController();
        showAllocationState(row, 'Se calculează propunerea FEFO/FIFO…');
        const url = new URL(form.dataset.allocationUrl, window.location.origin);
        url.searchParams.set('location_id', location.value);
        url.searchParams.set('catalog_item_id', item);
        url.searchParams.set('quantity', quantity);
        if (form.dataset.reportId) url.searchParams.set('report_id', form.dataset.reportId);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: row._allocationRequest.signal,
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(Object.values(payload.errors ?? {}).flat()[0] || 'Propunerea nu a putut fi calculată.');
            }
            renderAllocations(row, payload.allocations || []);
        } catch (error) {
            if (error.name !== 'AbortError') showAllocationState(row, error.message, 'danger');
        }
    };

    const scheduleAllocation = row => {
        window.clearTimeout(row._allocationTimer);
        row._allocationTimer = window.setTimeout(() => loadAllocation(row), 300);
    };

    const populateLine = (row, preserve = true) => {
        const select = row.querySelector('.consumption-line-item');
        const selected = preserve ? String(select.value || select.dataset.selectedValue || '') : '';
        select.replaceChildren(new Option(stockItems.length ? 'Alege materialul' : 'Nu există materiale disponibile', ''));
        stockItems.forEach(item => {
            const option = new Option(`${item.name} — disponibil ${formatQuantity(item.available)} ${item.unit}`, item.id);
            option.dataset.search = [item.sku, item.barcode].filter(Boolean).join(' ');
            select.add(option);
        });
        if ([...select.options].some(option => String(option.value) === selected)) select.value = selected;
        select.dataset.selectedValue = '';
        window.GafcoSearchableSelect?.sync(select);
        syncAvailable(row);
        if (select.value) scheduleAllocation(row);
        else showAllocationState(row, 'Alege materialul și cantitatea pentru propunerea pe loturi.');
    };

    const loadStock = async (preserve = true) => {
        stockRequest?.abort();
        stockItems = [];
        if (!location.value) {
            stockState.textContent = 'Alege locația pentru a încărca materialele disponibile.';
            list.querySelectorAll('[data-consumption-line]').forEach(row => populateLine(row, false));
            return;
        }

        stockRequest = new AbortController();
        stockState.classList.remove('text-danger');
        stockState.textContent = 'Se încarcă materialele disponibile…';
        const url = new URL(form.dataset.stockUrl, window.location.origin);
        url.searchParams.set('location_id', location.value);
        if (form.dataset.reportId) url.searchParams.set('report_id', form.dataset.reportId);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: stockRequest.signal,
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(Object.values(payload.errors ?? {}).flat()[0] || 'Stocul nu a putut fi încărcat.');
            }
            stockItems = payload.items || [];
            stockState.textContent = `${stockItems.length} materiale au stoc disponibil în locația aleasă.`;
            list.querySelectorAll('[data-consumption-line]').forEach(row => populateLine(row, preserve));
        } catch (error) {
            if (error.name !== 'AbortError') {
                stockState.classList.add('text-danger');
                stockState.textContent = error.message;
            }
        }
    };

    form.querySelector('[data-add-consumption-line]').addEventListener('click', () => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex())).trim();
        const row = wrapper.firstElementChild;
        if (!row) return;
        row.querySelectorAll('[data-name]').forEach(input => {
            input.name = `lines[${nextIndex()}][${input.dataset.name}]`;
        });
        list.append(row);
        renumberVisuals();
        populateLine(row, false);
        window.GafcoSearchableSelect?.focus(row.querySelector('select'));
    });

    list.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-consumption-line]');
        if (button && list.querySelectorAll('[data-consumption-line]').length > 1) {
            button.closest('[data-consumption-line]').remove();
            renumberVisuals();
        }
    });
    list.addEventListener('change', event => {
        const row = event.target.closest('[data-consumption-line]');
        if (row && event.target.matches('.consumption-line-item')) {
            syncAvailable(row);
            scheduleAllocation(row);
        }
    });
    list.addEventListener('input', event => {
        const row = event.target.closest('[data-consumption-line]');
        if (row && event.target.matches('.consumption-line-quantity')) scheduleAllocation(row);
    });
    location.addEventListener('change', () => loadStock(false));

    renumberVisuals();
    loadStock(true);
});
</script>
@endpush
