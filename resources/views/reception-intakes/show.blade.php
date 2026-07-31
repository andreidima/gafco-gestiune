@extends('layouts.app')

@section('title', $intake->number)

@section('content')
<div class="resource-shell">
    <div class="resource-page-header">
        <div class="resource-page-heading">
            <span class="resource-page-icon"><i class="fa-solid fa-file-image"></i></span>
            <div>
                <h1>{{ $intake->number }}</h1>
                <p>{{ $intake->location?->code }} — {{ $intake->location?->name }} · {{ $intake->created_at->format('d.m.Y H:i') }}</p>
            </div>
        </div>
        <div class="resource-page-actions">
            <x-back-link :fallback="route('reception-intakes.index')" class="btn-sm" />
            @if($canProcess)
                <a href="{{ route('supplier-receptions.create', ['intake_id' => $intake->id]) }}" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-check me-1"></i>Creează recepția
                </a>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="resource-form-card h-100">
                <section class="resource-form-section">
                    <div class="resource-form-section-title">Documente trimise</div>
                    <x-reception-document-cards
                        :documents="$intake->documents"
                        viewer-id="reception-intake-document-viewer"
                    />
                </section>
                @if($intake->notes)
                    <section class="resource-form-section">
                        <div class="resource-form-section-title">Observații</div>
                        <p class="mb-0">{{ $intake->notes }}</p>
                    </section>
                @endif
            </div>
        </div>
        <div class="col-lg-4">
            <div class="resource-form-card h-100">
                <section class="resource-form-section">
                    <div class="resource-form-section-title">Situație</div>
                    <dl class="reception-detail-list">
                        <div><dt>Stare</dt><dd>{{ $intake->status === 'created' ? 'Creat' : 'Închis' }}</dd></div>
                        <div><dt>Trimis de</dt><dd>{{ $intake->submitter?->name ?? '—' }}</dd></div>
                        @if($intake->processor)<div><dt>Procesat de</dt><dd>{{ $intake->processor->name }}</dd></div>@endif
                        @if($intake->reception)
                            <div>
                                <dt>Recepție</dt>
                                <dd><a href="{{ route('supplier-receptions.show', $intake->reception) }}">{{ $intake->reception->number }}</a></dd>
                            </div>
                        @endif
                    </dl>
                </section>

                @if($canProcess)
                    <section class="resource-form-section border-0">
                        <div class="resource-form-section-title">Închide fără recepție</div>
                        <form method="post" action="{{ route('reception-intakes.cancel', $intake) }}">
                            @csrf
                            <label class="form-label">Motiv</label>
                            <textarea name="reason" class="form-control mb-2" rows="3" required maxlength="2000"></textarea>
                            <button class="btn btn-outline-danger w-100">Închide ca anulat</button>
                        </form>
                    </section>
                @endif
            </div>
        </div>
    </div>

    <x-reception-document-viewer
        :documents="$intake->documents"
        id="reception-intake-document-viewer"
    />
</div>
@endsection
