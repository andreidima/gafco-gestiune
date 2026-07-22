@extends('layouts.app')

@php
    $editing = isset($transfer) && $transfer;
    $sourceId = old('source_location_id', $editing ? $transfer->source_location_id : ($parent?->destination_location_id));
    $destinationId = old('destination_location_id', $editing ? $transfer->destination_location_id : ($parent?->source_location_id));
    $purpose = old('purpose', $editing ? $transfer->purpose : ($parent ? 'return' : 'transfer'));
    $initialLines = old('lines');
    if (!$initialLines) {
        $sourceLines = $editing ? $transfer->lines : $parent?->lines;
        $initialLines = $sourceLines?->map(fn($line) => ['catalog_item_id'=>$line->tracked_asset_id ? '' : $line->catalog_item_id,'tracked_asset_id'=>$line->tracked_asset_id,'quantity'=>(float)$line->quantity])->values()->all() ?: [['catalog_item_id'=>'','tracked_asset_id'=>'','quantity'=>1]];
    }
@endphp

@section('title', $editing ? 'Modifica transfer' : ($parent ? 'Initiaza retur' : 'Transfer nou'))

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2 class="mb-1">{{ $editing ? 'Modifica '.$transfer->number : ($parent ? 'Retur pentru '.$parent->number : 'Transfer nou') }}</h2><div class="text-muted">Materiale si echipamente in acelasi document.</div></div><a href="{{ $editing ? route('transfers.show', $transfer) : route('transfers.index') }}" class="btn btn-outline-secondary">Inapoi</a></div>
    <form method="post" action="{{ $editing ? route('transfers.update', $transfer) : route('transfers.store') }}" class="card">
        @csrf
        @if($editing) @method('put') @endif
        <input type="hidden" name="parent_transfer_id" value="{{ old('parent_transfer_id', $editing ? $transfer->parent_transfer_id : $parent?->id) }}">
        <div class="card-body row g-3">
            <div class="col-md-3"><label class="form-label">Situatie</label><select name="purpose" class="form-select"><option value="transfer" @selected($purpose==='transfer')>Transfer catre o locatie</option>@if($parent || ($editing && $transfer->purpose === 'return'))<option value="return" @selected($purpose==='return')>Retur la locatia initiala</option>@endif</select></div>
            <div class="col-md-4"><label class="form-label">Din</label><select name="source_location_id" class="form-select" data-tom-select required><option value="">Alege</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)$sourceId===(string)$location->id)>{{ $location->code }} - {{ $location->name }} ({{ $location->type === 'base' ? 'Baza' : 'Santier' }})</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Catre</label><select name="destination_location_id" class="form-select" data-tom-select required><option value="">Alege</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)$destinationId===(string)$location->id)>{{ $location->code }} - {{ $location->name }} ({{ $location->type === 'base' ? 'Baza' : 'Santier' }})</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Deadline original</label><input name="manager_deadline" type="datetime-local" value="{{ old('manager_deadline', $editing ? $transfer->task?->manager_deadline?->format('Y-m-d\TH:i') : null) }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Prioritate</label><select name="priority" class="form-select">@foreach(['low'=>'Scazuta','normal'=>'Normala','high'=>'Ridicata','urgent'=>'Urgenta'] as $value=>$label)<option value="{{ $value }}" @selected(old('priority', $editing ? $transfer->task?->priority : 'normal')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Sofer initial / inlocuitor propus</label><select name="driver_id" class="form-select" data-tom-select><option value="">Aloca ulterior</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected((string)old('driver_id', $editing ? $transfer->task?->currentAssignment?->driver_id : null)===(string)$driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Aviz / document</label><input name="document_number" value="{{ old('document_number', $editing ? $transfer->document_number : null) }}" class="form-control"></div>
            <div class="col-12"><label class="form-label">Observatii</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $editing ? $transfer->notes : null) }}</textarea></div>

            <div class="col-12"><div class="d-flex justify-content-between align-items-center"><h5 class="mb-0">Continut</h5><button type="button" class="btn btn-sm btn-outline-primary" id="add-transfer-line"><i class="fa-solid fa-plus me-1"></i>Adauga pozitie</button></div><div class="small text-muted">Pentru echipamente QR alege echipamentul; pentru materiale alege articolul si cantitatea.</div></div>
            <div class="col-12" id="transfer-lines">
                @foreach($initialLines as $index => $line)
                    <div class="row g-2 align-items-end transfer-line border rounded-3 p-2 mb-2">
                        <div class="col-md-5"><label class="form-label small">Material / articol</label><select name="lines[{{ $index }}][catalog_item_id]" class="form-select transfer-line-item" aria-label="Material sau articol"><option value="">Alege doar pentru cantitati</option>@foreach($items as $item)<option value="{{ $item->id }}" @selected((string)($line['catalog_item_id'] ?? '')===(string)$item->id)>{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></div>
                        <div class="col-md-5"><label class="form-label small">Echipament QR</label><select name="lines[{{ $index }}][tracked_asset_id]" class="form-select transfer-line-asset" aria-label="Echipament QR"><option value="">Fara echipament unic</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected((string)($line['tracked_asset_id'] ?? '')===(string)$asset->id)>{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }} / {{ $asset->currentLocation?->code }}</option>@endforeach</select></div>
                        <div class="col-md-1"><label class="form-label small">Cant.</label><input name="lines[{{ $index }}][quantity]" type="number" min="0.001" step="0.001" value="{{ $line['quantity'] ?? 1 }}" class="form-control transfer-line-quantity" aria-label="Cantitate" required></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-transfer-line" aria-label="Sterge pozitia"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer bg-white text-end"><button class="btn btn-success">{{ $editing ? 'Salveaza modificarile' : 'Creeaza si solicita aprobari' }}</button></div>
    </form>
</div>

<template id="transfer-line-template">
    <div class="row g-2 align-items-end transfer-line border rounded-3 p-2 mb-2">
        <div class="col-md-5"><label class="form-label small">Material / articol</label><select data-name="catalog_item_id" class="form-select transfer-line-item" aria-label="Material sau articol"><option value="">Alege doar pentru cantitati</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label small">Echipament QR</label><select data-name="tracked_asset_id" class="form-select transfer-line-asset" aria-label="Echipament QR"><option value="">Fara echipament unic</option>@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} - {{ $asset->catalogItem?->name }} / {{ $asset->currentLocation?->code }}</option>@endforeach</select></div>
        <div class="col-md-1"><label class="form-label small">Cant.</label><input data-name="quantity" type="number" min="0.001" step="0.001" value="1" class="form-control transfer-line-quantity" aria-label="Cantitate" required></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-transfer-line" aria-label="Sterge pozitia"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const list = document.getElementById('transfer-lines');
    const template = document.getElementById('transfer-line-template');
    const syncRow = row => {
        const item = row.querySelector('.transfer-line-item');
        const asset = row.querySelector('.transfer-line-asset');
        const quantity = row.querySelector('.transfer-line-quantity');
        if (asset.value) {
            item.value = '';
            quantity.value = 1;
            quantity.readOnly = true;
        } else {
            quantity.readOnly = false;
        }
    };
    const syncRemoveButtons = () => {
        const rows = [...list.querySelectorAll('.transfer-line')];
        rows.forEach(row => row.querySelector('.remove-transfer-line').disabled = rows.length === 1);
    };
    const renumber = () => list.querySelectorAll('.transfer-line').forEach((row, index) => row.querySelectorAll('[name],[data-name]').forEach(input => {
        const key = input.dataset.name || input.name.match(/\[([^\]]+)\]$/)?.[1];
        if (key) input.name = `lines[${index}][${key}]`;
    }));
    document.getElementById('add-transfer-line').addEventListener('click', () => { list.append(template.content.cloneNode(true)); renumber(); syncRemoveButtons(); });
    list.addEventListener('change', event => {
        const row = event.target.closest('.transfer-line');
        if (! row) return;
        if (event.target.matches('.transfer-line-item') && event.target.value) row.querySelector('.transfer-line-asset').value = '';
        if (event.target.matches('.transfer-line-asset') && event.target.value) row.querySelector('.transfer-line-item').value = '';
        syncRow(row);
    });
    list.addEventListener('click', event => { if (event.target.closest('.remove-transfer-line') && list.querySelectorAll('.transfer-line').length > 1) { event.target.closest('.transfer-line').remove(); renumber(); syncRemoveButtons(); } });
    list.querySelectorAll('.transfer-line').forEach(syncRow);
    syncRemoveButtons();
});
</script>
@endpush
