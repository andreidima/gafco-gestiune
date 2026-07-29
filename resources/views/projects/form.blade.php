@extends('layouts.app')

@php
    $editing = (bool) $project;
    $initialLines = old('lines');
    if (! $initialLines) {
        $initialLines = $editing
            ? $project->materialPlans->map(fn ($plan) => [
                'catalog_item_id' => $plan->catalog_item_id,
                'planned_quantity' => (float) $plan->planned_quantity,
            ])->values()->all()
            : [['catalog_item_id' => '', 'planned_quantity' => 1]];
    }
@endphp

@section('title', $editing ? 'Modifică proiect' : 'Proiect nou')

@section('content')
<div class="resource-form-shell">
    <x-resource-page-header
        :title="$editing ? 'Modifică '.$project->code : 'Proiect nou'"
        description="Definește locația și cantitățile de materiale planificate. Transferurile vor fi comparate cu acest plan."
        icon="fa-diagram-project"
    >
        <x-slot:actions><a href="{{ $editing ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Înapoi</a></x-slot:actions>
    </x-resource-page-header>

    <form method="post" action="{{ $editing ? route('projects.update', $project) : route('projects.store') }}" class="resource-form-card" data-project-form>
        @csrf
        @if($editing) @method('put') @endif

        <div class="resource-form-section">
            <div class="resource-form-section-title">Identificare și perioadă</div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Cod proiect</label><input name="code" value="{{ old('code', $project?->code) }}" class="form-control text-uppercase" maxlength="40" required></div>
                <div class="col-md-5"><label class="form-label">Denumire</label><input name="name" value="{{ old('name', $project?->name) }}" class="form-control" required></div>
                <div class="col-md-4">
                    <label class="form-label">Locație</label>
                    <select name="location_id" class="form-select" data-tom-select required>
                        <option value="">Alege locația</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) old('location_id', $project?->location_id) === (string) $location->id)>{{ $location->code }} — {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stare</label>
                    <select name="status" class="form-select">
                        @foreach(\App\Models\Project::STATUS_LABELS as $value => $label)
                            @if($editing || $value !== 'archived')
                                <option value="{{ $value }}" @selected(old('status', $project?->status ?? 'draft') === $value)>{{ $label }}</option>
                            @endif
                        @endforeach
                    </select>
                    <div class="form-text">Numai proiectele active pot fi alese în transferuri noi.</div>
                </div>
                <div class="col-md-3"><label class="form-label">Începe la</label><input name="starts_on" type="date" value="{{ old('starts_on', $project?->starts_on?->format('Y-m-d')) }}" class="form-control"></div>
                <div class="col-md-3"><label class="form-label">Se încheie la</label><input name="ends_on" type="date" value="{{ old('ends_on', $project?->ends_on?->format('Y-m-d')) }}" class="form-control"></div>
                <div class="col-12"><label class="form-label">Observații</label><textarea name="notes" class="form-control" rows="2">{{ old('notes', $project?->notes) }}</textarea></div>
            </div>
        </div>

        <div class="resource-form-section">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div><div class="resource-form-section-title mb-1">Plan de materiale</div><div class="small text-muted">Fiecare material poate apărea o singură dată. Cantitățile pot fi modificate ulterior, iar istoricul transferurilor rămâne păstrat.</div></div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-project-line><i class="fa-solid fa-plus me-1"></i>Adaugă material</button>
            </div>
            <div data-project-lines>
                @foreach($initialLines as $index => $line)
                    <div class="row g-2 align-items-end project-plan-form-line border rounded-3 p-2 mb-2">
                        <div class="col-md-8">
                            <label class="form-label small">Material</label>
                            <select name="lines[{{ $index }}][catalog_item_id]" class="form-select project-plan-material" required>
                                <option value="">Alege materialul</option>
                                @foreach($materials as $material)
                                    <option value="{{ $material->id }}" data-unit="{{ $material->unit }}" @selected((string) ($line['catalog_item_id'] ?? '') === (string) $material->id)>{{ $material->name }} · {{ $material->sku }} · {{ $material->unit }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Cantitate planificată</label>
                            <div class="input-group"><input name="lines[{{ $index }}][planned_quantity]" type="number" min="0.001" step="0.001" value="{{ $line['planned_quantity'] ?? 1 }}" class="form-control" required><span class="input-group-text" data-project-line-unit>—</span></div>
                        </div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" data-remove-project-line aria-label="Șterge materialul"><i class="fa-solid fa-trash"></i></button></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="resource-form-actions">
            <a href="{{ $editing ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-outline-secondary">Renunță</a>
            <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salvează modificările' : 'Creează proiectul' }}</button>
        </div>
    </form>
</div>

<template data-project-line-template>
    <div class="row g-2 align-items-end project-plan-form-line border rounded-3 p-2 mb-2">
        <div class="col-md-8">
            <label class="form-label small">Material</label>
            <select data-name="catalog_item_id" class="form-select project-plan-material" required>
                <option value="">Alege materialul</option>
                @foreach($materials as $material)<option value="{{ $material->id }}" data-unit="{{ $material->unit }}">{{ $material->name }} · {{ $material->sku }} · {{ $material->unit }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Cantitate planificată</label>
            <div class="input-group"><input data-name="planned_quantity" type="number" min="0.001" step="0.001" value="1" class="form-control" required><span class="input-group-text" data-project-line-unit>—</span></div>
        </div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" data-remove-project-line aria-label="Șterge materialul"><i class="fa-solid fa-trash"></i></button></div>
    </div>
</template>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-project-form]');
    if (!form) return;
    const lines = form.querySelector('[data-project-lines]');
    const template = document.querySelector('[data-project-line-template]');

    const renumber = () => {
        lines.querySelectorAll('.project-plan-form-line').forEach((row, index) => {
            row.querySelectorAll('[name],[data-name]').forEach(input => {
                const key = input.dataset.name || input.name.match(/\[([^\]]+)\]$/)?.[1];
                if (key) input.name = `lines[${index}][${key}]`;
            });
        });
    };
    const sync = () => {
        const rows = [...lines.querySelectorAll('.project-plan-form-line')];
        rows.forEach(row => {
            const select = row.querySelector('.project-plan-material');
            row.querySelector('[data-project-line-unit]').textContent = select.selectedOptions[0]?.dataset.unit || '—';
            row.querySelector('[data-remove-project-line]').disabled = rows.length === 1;
        });
    };

    form.querySelector('[data-add-project-line]').addEventListener('click', () => {
        lines.append(template.content.cloneNode(true));
        renumber();
        sync();
        lines.lastElementChild?.querySelector('select')?.focus();
    });
    lines.addEventListener('change', event => {
        if (event.target.matches('.project-plan-material')) sync();
    });
    lines.addEventListener('click', event => {
        const button = event.target.closest('[data-remove-project-line]');
        if (button && lines.querySelectorAll('.project-plan-form-line').length > 1) {
            button.closest('.project-plan-form-line').remove();
            renumber();
            sync();
        }
    });
    sync();
});
</script>
@endpush
