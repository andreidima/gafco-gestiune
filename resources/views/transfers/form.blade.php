@extends('layouts.app')

@php
    $editing = isset($transfer) && $transfer;
    $sourceId = old('source_location_id', $editing ? $transfer->source_location_id : $parent?->destination_location_id);
    $destinationId = old('destination_location_id', $editing ? $transfer->destination_location_id : ($parent?->source_location_id ?? request('destination_location_id')));
    $purpose = old('purpose', $editing ? $transfer->purpose : ($parent ? 'return' : 'transfer'));
    $projectId = old('project_id', $editing ? $transfer->project_id : request('project_id'));
    $initialLines = old('lines');
    if (!$initialLines) {
        $sourceLines = $editing ? $transfer->lines : $parent?->lines;
        $initialLines = $sourceLines?->map(fn ($line) => [
            'catalog_item_id' => $line->tracked_asset_id ? '' : $line->catalog_item_id,
            'tracked_asset_id' => $line->tracked_asset_id,
            'quantity' => (float) $line->quantity,
        ])->values()->all() ?: [['catalog_item_id' => '', 'tracked_asset_id' => '', 'quantity' => 1]];
    }
@endphp

@section('title', $editing ? 'Modifică transfer' : ($parent ? 'Inițiază retur' : 'Transfer nou'))

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">{{ $editing ? 'Modifică '.$transfer->number : ($parent ? 'Retur pentru '.$parent->number : 'Transfer nou') }}</h2>
            <div class="text-muted">Materialele și echipamentele disponibile se încarcă după alegerea locației sursă.</div>
        </div>
        <x-back-link :fallback="$editing ? route('transfers.show', $transfer) : route('transfers.index')" />
    </div>

    <form
        method="post"
        action="{{ $editing ? route('transfers.update', $transfer) : route('transfers.store') }}"
        class="card"
        data-transfer-form
        data-source-options-url="{{ $sourceOptionsUrl }}"
        data-transfer-id="{{ $editing ? $transfer->id : '' }}"
    >
        @csrf
        @if($editing) @method('put') @endif
        <input type="hidden" name="parent_transfer_id" value="{{ old('parent_transfer_id', $editing ? $transfer->parent_transfer_id : $parent?->id) }}">

        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Situație</label>
                <select name="purpose" class="form-select">
                    <option value="transfer" @selected($purpose === 'transfer')>Transfer către o locație</option>
                    @if($parent || ($editing && $transfer->purpose === 'return'))
                        <option value="return" @selected($purpose === 'return')>Retur la locația inițială</option>
                    @endif
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Din</label>
                <select name="source_location_id" class="form-select" data-tom-select required>
                    <option value="">Alege locația sursă</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) $sourceId === (string) $location->id) @disabled((string) $destinationId === (string) $location->id && (string) $sourceId !== (string) $location->id)>
                            {{ $location->code }} - {{ $location->name }} ({{ $location->type === 'base' ? 'Bază' : 'Șantier' }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Către</label>
                <select name="destination_location_id" class="form-select @error('destination_location_id') is-invalid @enderror" data-tom-select required>
                    <option value="">Alege destinația</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected((string) $destinationId === (string) $location->id) @disabled((string) $sourceId === (string) $location->id)>
                            {{ $location->code }} - {{ $location->name }} ({{ $location->type === 'base' ? 'Bază' : 'Șantier' }})
                        </option>
                    @endforeach
                </select>
                @error('destination_location_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <div class="form-text">Destinația trebuie să fie diferită de locația sursă.</div>
            </div>
            <div class="col-md-4" data-transfer-project-field>
                <label class="form-label">Proiect / plan de materiale</label>
                <select name="project_id" class="form-select" data-tom-select>
                    <option value="">Fără proiect asociat</option>
                    @foreach($projects as $projectOption)
                        <option
                            value="{{ $projectOption->id }}"
                            data-location-id="{{ $projectOption->location_id }}"
                            @selected((string) $projectId === (string) $projectOption->id)
                        >
                            {{ $projectOption->code }} — {{ $projectOption->name }} · {{ $projectOption->location?->code }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Planul nu blochează transferul; o depășire este evidențiată și notificată.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label">Deadline original</label>
                <input name="manager_deadline" type="datetime-local" value="{{ old('manager_deadline', $editing ? $transfer->task?->manager_deadline?->format('Y-m-d\TH:i') : null) }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Prioritate</label>
                <select name="priority" class="form-select">
                    @foreach(['low' => 'Scăzută', 'normal' => 'Normală', 'high' => 'Ridicată', 'urgent' => 'Urgentă'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', $editing ? $transfer->task?->priority : 'normal') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Șofer inițial / înlocuitor propus</label>
                <select name="driver_id" class="form-select" data-tom-select>
                    <option value="">Alocă ulterior</option>
                    @foreach($drivers as $driver)
                        <option value="{{ $driver->id }}" data-search="{{ $driver->login_code }}" @selected((string) old('driver_id', $editing ? $transfer->task?->currentAssignment?->driver_id : null) === (string) $driver->id)>{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Aviz / document</label>
                <input name="document_number" value="{{ old('document_number', $editing ? $transfer->document_number : null) }}" class="form-control">
            </div>
            <div class="col-12">
                <label class="form-label">Observații</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes', $editing ? $transfer->notes : null) }}</textarea>
            </div>

            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-1">Conținut</h5>
                        <div class="small text-muted">Lista exclude materialele fără stoc și echipamentele rezervate în alte transferuri active.</div>
                    </div>
                </div>
                <div class="small mt-2 text-muted" data-source-inventory-state>Alege locația sursă pentru a încărca stocul disponibil.</div>
            </div>

            <div class="col-12" data-transfer-lines>
                @foreach($initialLines as $index => $line)
                    <div class="row g-2 align-items-end transfer-line border rounded-3 p-2 mb-2">
                        <div class="col-md-6 col-lg-4 transfer-line-item-column">
                            <label class="form-label small">Material</label>
                            <select
                                name="lines[{{ $index }}][catalog_item_id]"
                                class="form-select transfer-line-item"
                                data-selected-value="{{ $line['catalog_item_id'] ?? '' }}"
                                data-tom-select
                                aria-label="Material"
                            >
                                <option value="">Alege pentru cantități</option>
                            </select>
                            <div class="form-text transfer-line-availability">Disponibilitatea apare după alegerea materialului.</div>
                        </div>
                        <div class="col-md-6 col-lg-4 transfer-line-asset-column">
                            <label class="form-label small">Echipament QR</label>
                            <select
                                name="lines[{{ $index }}][tracked_asset_id]"
                                class="form-select transfer-line-asset"
                                data-selected-value="{{ $line['tracked_asset_id'] ?? '' }}"
                                data-tom-select
                                aria-label="Echipament QR"
                            >
                                <option value="">Fără echipament unic</option>
                            </select>
                        </div>
                        <div class="col-9 col-lg-3 transfer-line-quantity-column">
                            <label class="form-label small">Cant.</label>
                            <input name="lines[{{ $index }}][quantity]" type="number" data-quantity-stepper min="0.001" step="0.001" value="{{ $line['quantity'] ?? 1 }}" class="form-control transfer-line-quantity" aria-label="Cantitate" required>
                        </div>
                        <div class="col-3 col-lg-1 transfer-line-remove-column">
                            <button type="button" class="btn btn-outline-danger w-100 remove-transfer-line" aria-label="Șterge poziția">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="col-12 repeatable-list-add">
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-transfer-line>
                    <i class="fa-solid fa-plus me-1"></i>Adaugă poziție
                </button>
            </div>
            <div class="col-12 d-none" data-transfer-project-preview>
                <div class="transfer-project-plan-preview">
                    <div class="fw-semibold mb-1"><i class="fa-solid fa-chart-column me-1"></i>Impact asupra planului</div>
                    <div class="small text-muted mb-2" data-transfer-project-preview-title></div>
                    <div data-transfer-project-preview-lines></div>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white text-end">
            <button class="btn btn-success">{{ $editing ? 'Salvează modificările' : 'Creează și solicită aprobări' }}</button>
        </div>
    </form>
</div>

<template data-transfer-line-template>
    <div class="row g-2 align-items-end transfer-line border rounded-3 p-2 mb-2">
        <div class="col-md-6 col-lg-4 transfer-line-item-column">
            <label class="form-label small">Material</label>
            <select data-name="catalog_item_id" class="form-select transfer-line-item" data-tom-select aria-label="Material">
                <option value="">Alege pentru cantități</option>
            </select>
            <div class="form-text transfer-line-availability">Disponibilitatea apare după alegerea materialului.</div>
        </div>
        <div class="col-md-6 col-lg-4 transfer-line-asset-column">
            <label class="form-label small">Echipament QR</label>
            <select data-name="tracked_asset_id" class="form-select transfer-line-asset" data-tom-select aria-label="Echipament QR">
                <option value="">Fără echipament unic</option>
            </select>
        </div>
        <div class="col-9 col-lg-3 transfer-line-quantity-column">
            <label class="form-label small">Cant.</label>
            <input data-name="quantity" type="number" data-quantity-stepper min="0.001" step="0.001" value="1" class="form-control transfer-line-quantity" aria-label="Cantitate" required>
        </div>
        <div class="col-3 col-lg-1 transfer-line-remove-column">
            <button type="button" class="btn btn-outline-danger w-100 remove-transfer-line" aria-label="Șterge poziția">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-transfer-form]');
    if (!form) return;

    const source = form.querySelector('[name="source_location_id"]');
    const destination = form.querySelector('[name="destination_location_id"]');
    const purpose = form.querySelector('[name="purpose"]');
    const project = form.querySelector('[name="project_id"]');
    const projectField = form.querySelector('[data-transfer-project-field]');
    const projectPreview = form.querySelector('[data-transfer-project-preview]');
    const projectPreviewTitle = form.querySelector('[data-transfer-project-preview-title]');
    const projectPreviewLines = form.querySelector('[data-transfer-project-preview-lines]');
    const projectPlans = {{ Illuminate\Support\Js::from($projectPlanData) }};
    const list = form.querySelector('[data-transfer-lines]');
    const template = document.querySelector('[data-transfer-line-template]');
    const state = form.querySelector('[data-source-inventory-state]');
    let inventory = { materials: [], assets: [] };
    let inventoryLoading = false;
    let request;

    const formatQuantity = value => Number(value).toLocaleString('ro-RO', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });

    const syncLocationOptions = () => {
        if (source.value && destination.value && source.value === destination.value) {
            window.GafcoSearchableSelect?.setValue(destination, '', true);
        }

        [...source.options].forEach(option => {
            option.disabled = Boolean(option.value && destination.value && option.value === destination.value);
        });
        [...destination.options].forEach(option => {
            option.disabled = Boolean(option.value && source.value && option.value === source.value);
        });
        window.GafcoSearchableSelect?.replaceOptions(source);
        window.GafcoSearchableSelect?.replaceOptions(destination);
    };

    const renumber = () => {
        list.querySelectorAll('.transfer-line').forEach((row, index) => {
            row.querySelectorAll('[name],[data-name]').forEach(input => {
                const key = input.dataset.name || input.name.match(/\[([^\]]+)\]$/)?.[1];
                if (key) input.name = `lines[${index}][${key}]`;
            });
        });
    };

    const syncRemoveButtons = () => {
        const rows = [...list.querySelectorAll('.transfer-line')];
        rows.forEach(row => {
            row.querySelector('.remove-transfer-line').disabled = rows.length === 1;
        });
    };

    const populateSelect = (select, options, placeholder, label, searchText, selectedValue) => {
        const selected = String(selectedValue || select.value || select.dataset.selectedValue || '');
        select.replaceChildren(new Option(placeholder, ''));
        options.forEach(option => {
            const selectOption = new Option(label(option), option.id);
            selectOption.dataset.search = searchText(option);
            select.add(selectOption);
        });
        if ([...select.options].some(option => String(option.value) === selected)) {
            select.value = selected;
        }
        select.dataset.selectedValue = '';
        window.GafcoSearchableSelect?.replaceOptions(select);
    };

    const countLabel = (count, singular, plural) => `${count} ${count === 1 ? singular : plural}`;

    const clearRowsForSourceChange = () => {
        list.querySelectorAll('.transfer-line').forEach(row => {
            const item = row.querySelector('.transfer-line-item');
            const asset = row.querySelector('.transfer-line-asset');
            const quantity = row.querySelector('.transfer-line-quantity');

            window.GafcoSearchableSelect?.setValue(item, '', true);
            window.GafcoSearchableSelect?.setValue(asset, '', true);
            item.dataset.selectedValue = '';
            asset.dataset.selectedValue = '';
            quantity.value = 1;
            quantity.readOnly = false;
            quantity.removeAttribute('max');
        });
    };

    const syncAvailability = () => {
        const selectedTotals = {};
        list.querySelectorAll('.transfer-line').forEach(row => {
            const itemId = row.querySelector('.transfer-line-item').value;
            const quantity = Number(row.querySelector('.transfer-line-quantity').value || 0);
            if (itemId) selectedTotals[itemId] = (selectedTotals[itemId] || 0) + quantity;
        });

        list.querySelectorAll('.transfer-line').forEach(row => {
            const item = row.querySelector('.transfer-line-item');
            const asset = row.querySelector('.transfer-line-asset');
            const quantity = row.querySelector('.transfer-line-quantity');
            const help = row.querySelector('.transfer-line-availability');

            if (asset.value) {
                if (item.value) {
                    window.GafcoSearchableSelect?.setValue(item, '', true);
                }
                quantity.value = 1;
                quantity.readOnly = true;
                quantity.removeAttribute('max');
                help.textContent = 'Pentru echipamentul individual cantitatea este 1.';
                help.className = 'form-text transfer-line-availability';
                return;
            }

            quantity.readOnly = false;
            const material = inventory.materials.find(entry => String(entry.id) === String(item.value));
            if (!material) {
                quantity.removeAttribute('max');
                help.textContent = 'Disponibilitatea apare după alegerea materialului.';
                help.className = 'form-text transfer-line-availability';
                return;
            }

            quantity.max = material.available;
            const total = selectedTotals[String(material.id)] || 0;
            const exceeded = total - Number(material.available) > 0.0005;
            help.textContent = `Disponibil nerezervat: ${formatQuantity(material.available)} ${material.unit}; în document: ${formatQuantity(total)} ${material.unit}.`;
            help.className = `form-text transfer-line-availability ${exceeded ? 'text-danger fw-semibold' : ''}`;
        });
        syncProjectPreview();
    };

    const syncProjectOptions = () => {
        const isReturn = purpose.value === 'return';
        projectField.classList.toggle('d-none', isReturn);
        project.disabled = isReturn;
        [...project.options].forEach(option => {
            if (!option.value) return;
            option.disabled = isReturn || String(option.dataset.locationId) !== String(destination.value);
        });
        const selected = project.selectedOptions[0];
        if (project.value && selected?.disabled) project.value = '';
        window.GafcoSearchableSelect?.sync(project);
        syncProjectPreview();
    };

    const syncProjectPreview = () => {
        const plan = projectPlans[project.value];
        if (!plan || purpose.value !== 'transfer') {
            projectPreview.classList.add('d-none');
            projectPreviewLines.replaceChildren();
            return;
        }

        const selectedTotals = {};
        list.querySelectorAll('.transfer-line').forEach(row => {
            const itemId = row.querySelector('.transfer-line-item').value;
            const assetId = row.querySelector('.transfer-line-asset').value;
            const quantity = Number(row.querySelector('.transfer-line-quantity').value || 0);
            if (itemId && !assetId) selectedTotals[itemId] = (selectedTotals[itemId] || 0) + quantity;
        });
        projectPreview.classList.remove('d-none');
        projectPreviewTitle.textContent = `${plan.code} — ${plan.name}. Echipamentele individuale și retururile nu intră în calcul.`;
        projectPreviewLines.replaceChildren();

        const entries = Object.entries(selectedTotals);
        if (!entries.length) {
            const empty = document.createElement('div');
            empty.className = 'small text-muted';
            empty.textContent = 'Documentul nu conține încă materiale cantitative.';
            projectPreviewLines.append(empty);
            return;
        }

        entries.forEach(([itemId, documentQuantity]) => {
            const plannedLine = plan.lines[itemId] || { planned: 0, committed: 0, unit: inventory.materials.find(entry => String(entry.id) === String(itemId))?.unit || 'buc' };
            const after = Number(plannedLine.committed) + Number(documentQuantity);
            const overrun = Math.max(0, after - Number(plannedLine.planned));
            const material = inventory.materials.find(entry => String(entry.id) === String(itemId));
            const row = document.createElement('div');
            row.className = `transfer-project-plan-line ${overrun > 0.0005 ? 'text-danger' : ''}`;
            const name = document.createElement('strong');
            name.textContent = material?.name || `Material #${itemId}`;
            const values = document.createElement('span');
            values.textContent = `Plan ${formatQuantity(plannedLine.planned)} · deja solicitat ${formatQuantity(plannedLine.committed)} · după document ${formatQuantity(after)} ${plannedLine.unit}${overrun > 0.0005 ? ` · +${formatQuantity(overrun)} peste plan` : ''}`;
            row.append(name, values);
            projectPreviewLines.append(row);
        });
    };

    const populateRows = () => {
        list.querySelectorAll('.transfer-line').forEach(row => {
            const item = row.querySelector('.transfer-line-item');
            const asset = row.querySelector('.transfer-line-asset');
            const controlsDisabled = inventoryLoading || !source.value;
            item.disabled = controlsDisabled;
            asset.disabled = controlsDisabled;
            populateSelect(
                item,
                inventory.materials,
                inventoryLoading
                    ? 'Se încarcă materialele…'
                    : (inventory.materials.length ? 'Alege pentru cantități' : 'Nu există materiale disponibile'),
                material => `${material.name} — disponibil ${formatQuantity(material.available)} ${material.unit}`,
                material => [material.sku, material.barcode].filter(Boolean).join(' '),
            );
            populateSelect(
                asset,
                inventory.assets,
                inventoryLoading
                    ? 'Se încarcă echipamentele…'
                    : (inventory.assets.length ? 'Alege echipamentul, dacă este cazul' : 'Nu există echipamente disponibile'),
                entry => `${entry.asset_code} — ${entry.name || 'Echipament'}`,
                entry => [entry.asset_code, entry.qr_code, entry.serial_number].filter(Boolean).join(' '),
            );
        });
        syncAvailability();
    };

    const loadInventory = async ({ resetRows = false } = {}) => {
        request?.abort();
        request = undefined;
        if (resetRows) clearRowsForSourceChange();
        inventory = { materials: [], assets: [] };
        if (!source.value) {
            inventoryLoading = false;
            state.textContent = 'Alege locația sursă pentru a încărca stocul disponibil.';
            populateRows();
            return;
        }

        const controller = new AbortController();
        request = controller;
        inventoryLoading = true;
        state.classList.remove('text-danger');
        state.textContent = 'Se încarcă stocul disponibil…';
        populateRows();
        const url = new URL(form.dataset.sourceOptionsUrl, window.location.origin);
        url.searchParams.set('source_location_id', source.value);
        if (form.dataset.transferId) url.searchParams.set('transfer_id', form.dataset.transferId);

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller.signal,
            });
            const payload = await response.json();
            if (controller !== request) return;
            if (!response.ok) {
                throw new Error(Object.values(payload.errors ?? {}).flat()[0] || 'Stocul nu a putut fi încărcat.');
            }
            inventory = {
                materials: payload.materials || [],
                assets: payload.assets || [],
            };
            inventoryLoading = false;
            state.classList.remove('text-danger');
            state.textContent = `${countLabel(inventory.materials.length, 'material', 'materiale')} și ${countLabel(inventory.assets.length, 'echipament', 'echipamente')} pot fi transferate din ${payload.location.code}.`;
            populateRows();
        } catch (error) {
            if (error.name !== 'AbortError' && controller === request) {
                inventoryLoading = false;
                state.textContent = error.message;
                state.classList.add('text-danger');
                populateRows();
            }
        }
    };

    form.querySelector('[data-add-transfer-line]').addEventListener('click', () => {
        list.append(template.content.cloneNode(true));
        renumber();
        syncRemoveButtons();
        populateRows();
        window.GafcoRepeatableList?.reveal(list.lastElementChild, list.lastElementChild?.querySelector('select'));
    });

    list.addEventListener('change', event => {
        const row = event.target.closest('.transfer-line');
        if (!row) return;
        if (event.target.matches('.transfer-line-item') && event.target.value) {
            window.GafcoSearchableSelect?.setValue(row.querySelector('.transfer-line-asset'), '', true);
        }
        if (event.target.matches('.transfer-line-asset') && event.target.value) {
            window.GafcoSearchableSelect?.setValue(row.querySelector('.transfer-line-item'), '', true);
        }
        syncAvailability();
    });
    list.addEventListener('input', event => {
        if (event.target.matches('.transfer-line-quantity')) syncAvailability();
    });
    list.addEventListener('click', event => {
        const button = event.target.closest('.remove-transfer-line');
        if (button && list.querySelectorAll('.transfer-line').length > 1) {
            button.closest('.transfer-line').remove();
            renumber();
            syncRemoveButtons();
            syncAvailability();
        }
    });

    source.addEventListener('change', () => {
        syncLocationOptions();
        syncProjectOptions();
        loadInventory({ resetRows: true });
    });
    destination.addEventListener('change', () => {
        syncLocationOptions();
        syncProjectOptions();
    });
    purpose.addEventListener('change', syncProjectOptions);
    project.addEventListener('change', syncProjectPreview);
    syncRemoveButtons();
    syncLocationOptions();
    syncProjectOptions();
    loadInventory();
});
</script>
@endpush
