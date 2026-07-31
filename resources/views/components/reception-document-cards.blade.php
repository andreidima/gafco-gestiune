@props([
    'documents',
    'viewerId',
])

<div class="reception-document-grid">
    @foreach($documents as $document)
        <article class="reception-document-card">
            @if($document->isPreviewable())
                <button
                    type="button"
                    class="reception-document-preview-trigger"
                    data-document-preview-trigger
                    data-document-viewer-target="{{ $viewerId }}"
                    data-document-id="{{ $document->id }}"
                    data-document-preview-url="{{ route('reception-documents.preview', $document) }}"
                    data-document-download-url="{{ route('reception-documents.download', $document) }}"
                    data-document-mime="{{ $document->mime_type }}"
                    data-document-title="{{ $document->label() }}"
                    data-document-filename="{{ $document->original_name }}"
                    aria-controls="{{ $viewerId }}"
                    aria-label="Previzualizează {{ $document->label() }}"
                >
                    <span class="reception-document-icon">
                        <i class="fa-solid {{ str_contains($document->mime_type ?? '', 'pdf') ? 'fa-file-pdf' : 'fa-file-image' }}"></i>
                    </span>
                    <span class="reception-document-copy min-w-0">
                        <strong>{{ $document->label() }}</strong>
                        <span>{{ $document->original_name }}</span>
                        <small>{{ number_format($document->size_bytes / 1024, 0, ',', '.') }} KB · Deschide previzualizarea</small>
                    </span>
                </button>
            @else
                <div class="reception-document-preview-trigger is-unavailable">
                    <span class="reception-document-icon">
                        <i class="fa-solid fa-file"></i>
                    </span>
                    <span class="reception-document-copy min-w-0">
                        <strong>{{ $document->label() }}</strong>
                        <span>{{ $document->original_name }}</span>
                        <small>{{ number_format($document->size_bytes / 1024, 0, ',', '.') }} KB · Previzualizare indisponibilă</small>
                    </span>
                </div>
            @endif

            <a
                href="{{ route('reception-documents.download', $document) }}"
                class="btn btn-outline-secondary btn-sm reception-document-download"
                aria-label="Descarcă {{ $document->label() }}"
                title="Descarcă fișierul"
            >
                <i class="fa-solid fa-download"></i>
            </a>
        </article>
    @endforeach
</div>
