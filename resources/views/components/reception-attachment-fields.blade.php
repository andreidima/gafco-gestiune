@props([
    'documentTypes',
    'required' => false,
    'title' => 'Documente și fotografii',
])

@php
    $oldAttachments = old('attachments', $required ? [['type' => 'goods_photo', 'custom_label' => '']] : []);
@endphp

<section class="resource-form-section" data-attachment-builder data-required="{{ $required ? '1' : '0' }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="resource-form-section-title mb-1">{{ $title }}</div>
            <div class="resource-secondary">PDF sau fotografie, maximum 12 MB pe fișier. Documentele sunt private.</div>
        </div>
    </div>

    <div class="reception-attachment-list" data-attachment-list>
        @foreach($oldAttachments as $index => $attachment)
            <div class="reception-attachment-row" data-attachment-row>
                <div>
                    <label class="form-label">Fișier</label>
                    <input
                        type="file"
                        name="attachments[{{ $index }}][file]"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.webp,.heic,.heif,.pdf"
                        @required($required)
                    >
                </div>
                <div>
                    <label class="form-label">Tip</label>
                    <select name="attachments[{{ $index }}][type]" class="form-select" data-attachment-type required>
                        @foreach($documentTypes as $value => $label)
                            <option value="{{ $value }}" @selected(($attachment['type'] ?? 'goods_photo') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-custom-label-wrap @class(['d-none' => ($attachment['type'] ?? null) !== 'custom'])>
                    <label class="form-label">Denumire</label>
                    <input
                        name="attachments[{{ $index }}][custom_label]"
                        value="{{ $attachment['custom_label'] ?? '' }}"
                        class="form-control"
                        maxlength="160"
                        placeholder="Ex.: certificat de calitate"
                    >
                </div>
                <button type="button" class="btn btn-outline-danger reception-attachment-remove" data-remove-attachment title="Elimină fișierul">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        @endforeach
    </div>

    <template data-attachment-template>
        <div class="reception-attachment-row" data-attachment-row>
            <div>
                <label class="form-label">Fișier</label>
                <input
                    type="file"
                    name="attachments[__INDEX__][file]"
                    class="form-control"
                    accept=".jpg,.jpeg,.png,.webp,.heic,.heif,.pdf"
                    @required($required)
                >
            </div>
            <div>
                <label class="form-label">Tip</label>
                <select name="attachments[__INDEX__][type]" class="form-select" data-attachment-type required>
                    @foreach($documentTypes as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'goods_photo')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="d-none" data-custom-label-wrap>
                <label class="form-label">Denumire</label>
                <input
                    name="attachments[__INDEX__][custom_label]"
                    class="form-control"
                    maxlength="160"
                    placeholder="Ex.: certificat de calitate"
                >
            </div>
            <button type="button" class="btn btn-outline-danger reception-attachment-remove" data-remove-attachment title="Elimină fișierul">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </template>

    <div class="repeatable-list-add">
        <button type="button" class="btn btn-outline-primary btn-sm" data-add-attachment>
            <i class="fa-solid fa-paperclip me-1"></i>Adaugă fișier
        </button>
    </div>
</section>
