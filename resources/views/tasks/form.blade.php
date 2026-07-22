@extends('layouts.app')

@php($editing = (bool) $task)
@section('title', $editing ? 'Modifica sarcina' : 'Sarcina noua')

@section('content')
<x-resource-form-shell
    :title="$editing ? 'Modifica sarcina' : 'Sarcina noua'"
    description="Defineste traseul, prioritatea si termenul managerului. Soferul isi poate comunica separat propria estimare."
    :back-route="$editing ? route('tasks.show', $task) : route('tasks.index')"
    icon="fa-list-check"
>
    <form method="post" action="{{ $editing ? route('tasks.update', $task) : route('tasks.store') }}" class="resource-form-card">
        @csrf
        @if($editing) @method('put') @endif
        <div class="resource-form-section">
            <div class="resource-form-section-title">Sarcina</div>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Titlu</label><input name="title" value="{{ old('title', $task?->title) }}" class="form-control" required autofocus></div>
                <div class="col-md-4"><label class="form-label">Categorie</label><select name="category" class="form-select" required>@foreach(['general'=>'Generala','transport'=>'Transport','documente'=>'Documente','aprovizionare'=>'Aprovizionare','altele'=>'Altele'] as $value=>$label)<option value="{{ $value }}" @selected(old('category', $task?->category ?? 'general') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label">Observatii initiale</label><textarea name="notes" class="form-control" rows="4">{{ old('notes', $task?->notes) }}</textarea></div>
            </div>
        </div>
        <div class="resource-form-section">
            <div class="resource-form-section-title">Traseu si planificare</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Din locatie, optional</label><select name="source_location_id" class="form-select" data-tom-select><option value="">Nespecificat</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('source_location_id', $task?->source_location_id) === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Catre locatie, optional</label><select name="destination_location_id" class="form-select" data-tom-select><option value="">Nespecificat</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) old('destination_location_id', $task?->destination_location_id) === (string) $location->id)>{{ $location->code }} - {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-4"><label class="form-label">Deadline manager</label><input name="manager_deadline" value="{{ old('manager_deadline', $task?->manager_deadline?->format('Y-m-d\TH:i')) }}" type="datetime-local" class="form-control"><div class="form-text">Ramane termenul oficial, chiar daca soferul propune alta estimare.</div></div>
                <div class="col-md-4"><label class="form-label">Prioritate</label><select name="priority" class="form-select" required>@foreach(['low'=>'Scazuta','normal'=>'Normala','high'=>'Ridicata','urgent'=>'Urgenta'] as $value=>$label)<option value="{{ $value }}" @selected(old('priority', $task?->priority ?? 'normal') === $value)>{{ $label }}</option>@endforeach</select></div>
                @if($editing)
                    <div class="col-md-4"><label class="form-label">Sofer curent</label><div class="form-control bg-light">{{ $task->currentAssignment?->driver?->name ?? 'Nealocat' }}</div><div class="form-text">Alocarea si realocarea se gestioneaza din pagina sarcinii.</div></div>
                @else
                    <div class="col-md-4"><label class="form-label">Sofer, optional</label><select name="driver_id" class="form-select" data-tom-select><option value="">Aloca ulterior</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}" @selected((string) old('driver_id') === (string) $driver->id)>{{ $driver->name }}</option>@endforeach</select></div>
                @endif
            </div>
        </div>
        <div class="resource-form-actions-bar">
            <a href="{{ $editing ? route('tasks.show', $task) : route('tasks.index') }}" class="btn btn-outline-secondary">Renunta</a>
            <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salveaza modificarile' : 'Creeaza sarcina' }}</button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
