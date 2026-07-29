@extends('layouts.app')

@section('title', 'Trimite documente de recepție')

@section('content')
<x-resource-form-shell
    title="Trimite documente de recepție"
    description="Încarcă fotografiile sau documentele. Stocul nu se modifică până când responsabilul creează recepția."
    :back-route="route('reception-intakes.index')"
    icon="fa-camera"
>
    <form
        method="post"
        action="{{ route('reception-intakes.store') }}"
        class="resource-form-card"
        enctype="multipart/form-data"
    >
        @csrf
        <section class="resource-form-section">
            <div class="resource-form-section-title">Unde au ajuns materialele?</div>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label">Locație</label>
                    <select name="location_id" class="form-select" data-tom-select required autofocus>
                        <option value="">Alege locația</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) old('location_id') === (string) $location->id)>
                                {{ $location->code }} — {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Observații</label>
                    <textarea name="notes" class="form-control" rows="3" maxlength="4000" placeholder="Opțional: persoană de contact, explicații">{{ old('notes') }}</textarea>
                </div>
            </div>
        </section>

        <x-reception-attachment-fields :document-types="$documentTypes" :required="true" />

        <div class="resource-form-actions-bar">
            <a href="{{ route('reception-intakes.index') }}" class="btn btn-outline-secondary">Renunță</a>
            <button class="btn btn-success">
                <i class="fa-solid fa-paper-plane me-1"></i>Trimite pentru procesare
            </button>
        </div>
    </form>
</x-resource-form-shell>
@endsection
