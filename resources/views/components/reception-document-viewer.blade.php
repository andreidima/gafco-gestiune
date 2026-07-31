@props([
    'documents',
    'id',
    'variant' => 'modal',
    'launcher' => false,
])

@php
    $previewableDocuments = $documents->filter(fn ($document) => $document->isPreviewable())->values();
    $initialDocument = $previewableDocuments->first();
@endphp

<div
    @class([
        'reception-document-viewer-host',
        'reception-document-viewer-host--workspace' => $variant === 'workspace',
        'reception-document-viewer-host--modal' => $variant === 'modal',
    ])
>
    @if($launcher && $initialDocument)
        <button
            type="button"
            class="btn btn-primary reception-document-viewer-launcher"
            data-document-preview-trigger
            data-document-viewer-target="{{ $id }}"
            data-document-id="{{ $initialDocument->id }}"
            data-document-preview-url="{{ route('reception-documents.preview', $initialDocument) }}"
            data-document-download-url="{{ route('reception-documents.download', $initialDocument) }}"
            data-document-mime="{{ $initialDocument->mime_type }}"
            data-document-title="{{ $initialDocument->label() }}"
            data-document-filename="{{ $initialDocument->original_name }}"
            aria-controls="{{ $id }}"
        >
            <i class="fa-solid fa-file-image me-2"></i>
            Documente
            <span class="badge text-bg-light ms-2">{{ $documents->count() }}</span>
        </button>
    @endif

    <aside
        id="{{ $id }}"
        @class([
            'reception-document-viewer',
            'reception-document-viewer--workspace' => $variant === 'workspace',
            'reception-document-viewer--modal' => $variant === 'modal',
        ])
        data-document-viewer
        data-document-viewer-variant="{{ $variant }}"
        @if($initialDocument) data-document-initial-id="{{ $initialDocument->id }}" @endif
        aria-hidden="{{ $variant === 'modal' ? 'true' : 'false' }}"
        aria-label="Previzualizare documente"
    >
        <div class="reception-document-viewer-header">
            <div class="min-w-0">
                <div class="resource-form-section-title mb-1">Document sursă</div>
                <strong class="d-block text-truncate" data-document-viewer-title>
                    {{ $initialDocument?->label() ?? 'Niciun document previzualizabil' }}
                </strong>
                <span class="small text-secondary d-block text-truncate" data-document-viewer-filename>
                    {{ $initialDocument?->original_name }}
                </span>
            </div>
            <div class="reception-document-viewer-actions">
                <a
                    href="{{ $initialDocument ? route('reception-documents.download', $initialDocument) : '#' }}"
                    class="btn btn-outline-secondary btn-sm"
                    data-document-viewer-download
                    aria-label="Descarcă documentul"
                    title="Descarcă documentul"
                >
                    <i class="fa-solid fa-download"></i>
                </a>
                @if($variant === 'workspace')
                    <button
                        type="button"
                        class="btn btn-outline-secondary btn-sm reception-document-viewer-expand"
                        data-document-viewer-expand
                        aria-pressed="false"
                        aria-label="Extinde vizualizarea"
                        title="Extinde vizualizarea"
                    >
                        <i class="fa-solid fa-expand" data-document-viewer-expand-icon></i>
                    </button>
                @endif
                <button
                    type="button"
                    class="btn btn-outline-secondary btn-sm"
                    data-document-viewer-close
                    aria-label="Închide previzualizarea"
                    title="Închide previzualizarea"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>

        <div class="reception-document-viewer-tabs" aria-label="Alege documentul">
            @foreach($documents as $document)
                @if($document->isPreviewable())
                    <button
                        type="button"
                        class="reception-document-viewer-tab"
                        data-document-preview-trigger
                        data-document-viewer-target="{{ $id }}"
                        data-document-id="{{ $document->id }}"
                        data-document-preview-url="{{ route('reception-documents.preview', $document) }}"
                        data-document-download-url="{{ route('reception-documents.download', $document) }}"
                        data-document-mime="{{ $document->mime_type }}"
                        data-document-title="{{ $document->label() }}"
                        data-document-filename="{{ $document->original_name }}"
                        aria-controls="{{ $id }}"
                        aria-pressed="false"
                    >
                        <i class="fa-solid {{ str_contains($document->mime_type ?? '', 'pdf') ? 'fa-file-pdf' : 'fa-file-image' }}"></i>
                        <span>{{ $document->label() }}</span>
                    </button>
                @else
                    <a
                        href="{{ route('reception-documents.download', $document) }}"
                        class="reception-document-viewer-tab is-download-only"
                        title="Formatul poate fi doar descărcat"
                    >
                        <i class="fa-solid fa-download"></i>
                        <span>{{ $document->label() }}</span>
                    </a>
                @endif
            @endforeach
        </div>

        <div class="reception-document-image-tools d-none" data-document-image-tools>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-document-image-action="zoom-out" aria-label="Micșorează">
                <i class="fa-solid fa-magnifying-glass-minus"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-document-image-action="reset">
                <span data-document-zoom-label>100%</span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-document-image-action="zoom-in" aria-label="Mărește">
                <i class="fa-solid fa-magnifying-glass-plus"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-document-image-action="rotate" aria-label="Rotește">
                <i class="fa-solid fa-rotate-right"></i>
            </button>
        </div>

        <div class="reception-document-viewer-stage" data-document-viewer-stage>
            <div class="reception-document-viewer-state" data-document-viewer-loading>
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Se încarcă documentul…</span>
            </div>
            <div class="reception-document-viewer-state d-none" data-document-viewer-empty>
                <i class="fa-regular fa-file-image"></i>
                <span>Alege un document pentru previzualizare.</span>
            </div>
            <div class="reception-document-viewer-state d-none" data-document-viewer-error>
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Documentul nu a putut fi previzualizat. Îl poți descărca folosind butonul de mai sus.</span>
            </div>
            <div class="reception-document-image-canvas d-none" data-document-image-canvas>
                <img alt="" data-document-viewer-image>
            </div>
            <iframe
                class="reception-document-viewer-frame d-none"
                data-document-viewer-frame
                title="Conținutul documentului"
            ></iframe>
        </div>
    </aside>
</div>
