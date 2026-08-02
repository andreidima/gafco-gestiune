import '../scss/app.scss';
import 'bootstrap';
import TomSelect from 'tom-select';

const normalizeInternalCodeInput = (input) => {
    const start = input.selectionStart;
    const end = input.selectionEnd;
    input.value = input.value.toLocaleUpperCase('ro-RO');
    if (start !== null && end !== null) {
        input.setSelectionRange(start, end);
    }
};

document.addEventListener('input', (event) => {
    if (event.target.matches?.('[data-internal-code]')) {
        normalizeInternalCodeInput(event.target);
    }
});

const initializeQuantityStepper = (input) => {
    if (input.dataset.quantityStepperReady) return;
    input.dataset.quantityStepperReady = 'true';

    const wrapper = document.createElement('div');
    wrapper.className = 'quantity-stepper';
    const decrement = document.createElement('button');
    const increment = document.createElement('button');
    decrement.type = increment.type = 'button';
    decrement.className = increment.className = 'btn btn-outline-secondary quantity-stepper-button';
    decrement.textContent = '−1';
    increment.textContent = '+1';
    decrement.setAttribute('aria-label', 'Scade cantitatea cu 1');
    increment.setAttribute('aria-label', 'Crește cantitatea cu 1');
    input.before(wrapper);
    wrapper.append(decrement, input, increment);

    const bounds = () => ({
        min: input.min === '' ? null : Number(input.min),
        max: input.max === '' ? null : Number(input.max),
    });
    const sync = () => {
        const value = Number(input.value);
        const { min, max } = bounds();
        const unavailable = input.disabled || input.readOnly || !Number.isFinite(value);
        decrement.disabled = unavailable || (min !== null && value - 1 < min);
        increment.disabled = unavailable || (max !== null && value + 1 > max);
    };
    const adjust = (amount) => {
        const value = input.value === '' ? 0 : Number(input.value);
        if (!Number.isFinite(value)) return;
        const next = Number((value + amount).toFixed(3));
        const { min, max } = bounds();
        if ((min !== null && next < min) || (max !== null && next > max)) return;
        input.value = String(next);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        sync();
    };
    decrement.addEventListener('click', () => adjust(-1));
    increment.addEventListener('click', () => adjust(1));
    input.addEventListener('input', sync);
    input.addEventListener('change', sync);
    sync();
};

const initializeQuantitySteppers = (root = document) => {
    if (root.matches?.('[data-quantity-stepper]')) initializeQuantityStepper(root);
    root.querySelectorAll?.('[data-quantity-stepper]').forEach(initializeQuantityStepper);
};

document.addEventListener('DOMContentLoaded', () => initializeQuantitySteppers());
new MutationObserver((mutations) => mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
    if (node.nodeType === Node.ELEMENT_NODE) initializeQuantitySteppers(node);
}))).observe(document.documentElement, { childList: true, subtree: true });

const searchableSelectSelector = 'select[data-tom-select]';

const searchableSelectSettings = (element) => {
    const settings = {
        allowEmptyOption: true,
        create: false,
        diacritics: true,
        searchField: ['text', 'search'],
        searchConjunction: 'and',
        render: {
            no_results: () => '<div class="no-results">Niciun rezultat</div>',
        },
    };

    if (element.multiple) {
        settings.plugins = {
            remove_button: {
                title: 'Elimină',
            },
        };
        settings.closeAfterSelect = false;
        settings.hideSelected = true;
    }

    return settings;
};

const initializeSearchableSelect = (element) => {
    if (! element?.matches?.(searchableSelectSelector)) {
        return null;
    }
    if (element.tomselect) {
        return element.tomselect;
    }

    const select = new TomSelect(element, searchableSelectSettings(element));
    if (element.matches('[data-manager-selector]')) {
        const status = element.closest('.resource-form-section')?.querySelector('[data-manager-selection-status] span');
        const updateManagerCount = () => {
            const count = select.items.length;
            if (status) {
                status.textContent = `${count} ${count === 1 ? 'responsabil selectat' : 'responsabili selectați'}`;
            }
        };
        select.on('change', updateManagerCount);
        updateManagerCount();
    }

    return select;
};

const initializeSearchableSelects = (root = document) => {
    if (root.matches?.(searchableSelectSelector)) {
        initializeSearchableSelect(root);
    }
    root.querySelectorAll?.(searchableSelectSelector).forEach(initializeSearchableSelect);
};

const syncSearchableSelect = (element) => {
    const select = initializeSearchableSelect(element);
    select?.sync();

    return select;
};

const replaceSearchableSelectOptions = (element) => {
    const selectedValue = element?.value ?? '';
    const select = initializeSearchableSelect(element);
    if (select) {
        select.clear(true);
        select.clearOptions();
        element.value = selectedValue;
        select.sync();
    }

    return select;
};

const setSearchableSelectValue = (element, value, silent = false) => {
    const select = initializeSearchableSelect(element);
    if (select) {
        select.setValue(value == null ? '' : String(value), silent);
    } else if (element) {
        element.value = value == null ? '' : String(value);
    }
};

const focusSearchableSelect = (element) => {
    const select = initializeSearchableSelect(element);
    if (select) {
        select.focus();
    } else {
        element?.focus();
    }
};

window.GafcoSearchableSelect = {
    initialize: initializeSearchableSelects,
    sync: syncSearchableSelect,
    replaceOptions: replaceSearchableSelectOptions,
    setValue: setSearchableSelectValue,
    focus: focusSearchableSelect,
};

const liveFilterSearchSelector = 'input[name="search"], input[name$="_search"]';
const liveFilterDelay = 500;
const liveFilterMinLength = 2;
const liveFilterStates = new WeakMap();

const liveFilterState = (form) => {
    if (! liveFilterStates.has(form)) {
        liveFilterStates.set(form, {
            controller: null,
            timer: null,
        });
    }

    return liveFilterStates.get(form);
};

const liveFilterTarget = (form) => {
    const selector = form.dataset.liveFilterTarget;

    return selector ? document.querySelector(selector) : null;
};

const liveFilterUrl = (form) => {
    const url = new URL(form.action || window.location.href, window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    if (! url.hash) {
        url.hash = window.location.hash;
    }

    return url;
};

const setLiveFilterStatus = (form, message = '') => {
    const status = form.querySelector('[data-live-filter-status]');
    if (status) {
        status.textContent = message;
    }
};

const revealAddedRow = (row, focusElement = null) => {
    if (! row) {
        return;
    }

    row.scrollIntoView({
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        block: 'start',
    });
    window.requestAnimationFrame(() => {
        if (focusElement?.matches?.(searchableSelectSelector)) {
            focusSearchableSelect(focusElement);
        } else {
            focusElement?.focus?.();
        }
    });
};

window.GafcoRepeatableList = {
    reveal: revealAddedRow,
};

const documentViewerStates = new WeakMap();
const documentViewerMobile = window.matchMedia('(max-width: 991.98px)');

const documentViewerState = (viewer) => {
    if (! documentViewerStates.has(viewer)) {
        documentViewerStates.set(viewer, {
            documentId: null,
            lastTrigger: null,
            rotation: 0,
            zoom: 1,
        });
    }

    return documentViewerStates.get(viewer);
};

const setDocumentViewerStatus = (viewer, status) => {
    const loading = viewer.querySelector('[data-document-viewer-loading]');
    const empty = viewer.querySelector('[data-document-viewer-empty]');
    const error = viewer.querySelector('[data-document-viewer-error]');
    const canvas = viewer.querySelector('[data-document-image-canvas]');
    const frame = viewer.querySelector('[data-document-viewer-frame]');
    const tools = viewer.querySelector('[data-document-image-tools]');

    loading?.classList.toggle('d-none', status !== 'loading');
    empty?.classList.toggle('d-none', status !== 'empty');
    error?.classList.toggle('d-none', status !== 'error');
    canvas?.classList.toggle('d-none', status !== 'image');
    frame?.classList.toggle('d-none', status !== 'pdf');
    tools?.classList.toggle('d-none', status !== 'image');
};

const updateDocumentImageTransform = (viewer) => {
    const state = documentViewerState(viewer);
    const image = viewer.querySelector('[data-document-viewer-image]');
    const label = viewer.querySelector('[data-document-zoom-label]');
    if (image) {
        image.style.transform = `scale(${state.zoom}) rotate(${state.rotation}deg)`;
    }
    if (label) {
        label.textContent = `${Math.round(state.zoom * 100)}%`;
    }
};

const resetDocumentImageTransform = (viewer) => {
    const state = documentViewerState(viewer);
    state.rotation = 0;
    state.zoom = 1;
    updateDocumentImageTransform(viewer);
};

const selectDocumentInViewer = (viewer, trigger) => {
    if (! trigger?.dataset.documentPreviewUrl) {
        setDocumentViewerStatus(viewer, 'empty');
        return;
    }

    const state = documentViewerState(viewer);
    const title = viewer.querySelector('[data-document-viewer-title]');
    const filename = viewer.querySelector('[data-document-viewer-filename]');
    const download = viewer.querySelector('[data-document-viewer-download]');
    const image = viewer.querySelector('[data-document-viewer-image]');
    const frame = viewer.querySelector('[data-document-viewer-frame]');
    const mimeType = (trigger.dataset.documentMime ?? '').toLowerCase();
    const previewUrl = trigger.dataset.documentPreviewUrl;

    state.documentId = trigger.dataset.documentId ?? null;
    if (title) {
        title.textContent = trigger.dataset.documentTitle ?? 'Document';
    }
    if (filename) {
        filename.textContent = trigger.dataset.documentFilename ?? '';
    }
    if (download) {
        download.href = trigger.dataset.documentDownloadUrl ?? '#';
    }

    viewer.querySelectorAll('[data-document-preview-trigger]').forEach((tab) => {
        const selected = tab.dataset.documentId === state.documentId;
        tab.classList.toggle('is-active', selected);
        tab.setAttribute('aria-pressed', selected ? 'true' : 'false');
    });

    resetDocumentImageTransform(viewer);
    setDocumentViewerStatus(viewer, 'loading');
    if (image) {
        image.onload = null;
        image.onerror = null;
        image.removeAttribute('src');
        image.alt = trigger.dataset.documentTitle ?? 'Document';
    }
    if (frame) {
        frame.onload = null;
        frame.removeAttribute('src');
    }

    if (mimeType === 'application/pdf') {
        if (! frame) {
            setDocumentViewerStatus(viewer, 'error');
            return;
        }
        frame.onload = () => setDocumentViewerStatus(viewer, 'pdf');
        frame.src = `${previewUrl}#view=FitH&navpanes=0`;
        return;
    }

    if (! image) {
        setDocumentViewerStatus(viewer, 'error');
        return;
    }
    image.onload = () => setDocumentViewerStatus(viewer, 'image');
    image.onerror = () => setDocumentViewerStatus(viewer, 'error');
    image.src = previewUrl;
};

const documentViewerIsOverlay = (viewer) => viewer.classList.contains('reception-document-viewer--modal')
    || documentViewerMobile.matches
    || viewer.classList.contains('is-expanded');

const syncDocumentViewerBodyLock = () => {
    const overlayOpen = Array.from(document.querySelectorAll('[data-document-viewer]')).some((viewer) => (
        viewer.classList.contains('is-expanded')
        || (viewer.classList.contains('is-open') && documentViewerIsOverlay(viewer))
    ));
    document.body.classList.toggle('document-viewer-open', overlayOpen);
};

const openDocumentViewer = (viewer, trigger, focusViewer = true) => {
    const state = documentViewerState(viewer);
    const workspace = viewer.closest('[data-document-viewer-workspace]');
    workspace?.classList.remove('is-viewer-collapsed');
    if (trigger && ! trigger.closest('[data-document-viewer]')) {
        state.lastTrigger = trigger;
    }

    if (viewer.classList.contains('reception-document-viewer--workspace') && ! documentViewerMobile.matches) {
        viewer.setAttribute('aria-hidden', 'false');
    } else {
        viewer.classList.add('is-open');
        viewer.setAttribute('aria-hidden', 'false');
    }

    selectDocumentInViewer(viewer, trigger);
    syncDocumentViewerBodyLock();
    if (focusViewer && documentViewerIsOverlay(viewer)) {
        window.requestAnimationFrame(() => viewer.querySelector('[data-document-viewer-close]')?.focus());
    }
};

const setDocumentViewerExpanded = (viewer, expanded) => {
    viewer.classList.toggle('is-expanded', expanded);
    const button = viewer.querySelector('[data-document-viewer-expand]');
    const icon = viewer.querySelector('[data-document-viewer-expand-icon]');
    button?.setAttribute('aria-pressed', expanded ? 'true' : 'false');
    button?.setAttribute('aria-label', expanded ? 'Revino la vizualizarea restrânsă' : 'Extinde vizualizarea');
    if (button) {
        button.title = expanded ? 'Restrânge vizualizarea' : 'Extinde vizualizarea';
    }
    icon?.classList.toggle('fa-expand', ! expanded);
    icon?.classList.toggle('fa-compress', expanded);
    syncDocumentViewerBodyLock();
};

const closeDocumentViewer = (viewer) => {
    const state = documentViewerState(viewer);
    const workspace = viewer.closest('[data-document-viewer-workspace]');
    setDocumentViewerExpanded(viewer, false);

    if (viewer.classList.contains('reception-document-viewer--workspace') && ! documentViewerMobile.matches) {
        workspace?.classList.add('is-viewer-collapsed');
    } else {
        viewer.classList.remove('is-open');
    }
    viewer.setAttribute('aria-hidden', 'true');
    syncDocumentViewerBodyLock();

    const fallback = workspace?.querySelector('.reception-document-viewer-launcher');
    window.requestAnimationFrame(() => (state.lastTrigger ?? fallback)?.focus?.());
};

const setLiveFilterLoading = (form, loading) => {
    form.setAttribute('aria-busy', loading ? 'true' : 'false');
    const target = liveFilterTarget(form);
    if (target) {
        target.setAttribute('aria-busy', loading ? 'true' : 'false');
    }
};

const replaceLiveFilterSummaries = (nextDocument) => {
    const currentSummaries = document.querySelectorAll('[data-live-filter-summary]');
    const nextSummaries = nextDocument.querySelectorAll('[data-live-filter-summary]');

    currentSummaries.forEach((summary, index) => {
        const replacement = nextSummaries[index];
        if (replacement) {
            summary.replaceWith(document.importNode(replacement, true));
        }
    });
};

const replaceLiveFilterResults = (form, nextDocument) => {
    const selector = form.dataset.liveFilterTarget;
    const currentTarget = selector ? document.querySelector(selector) : null;
    const nextTarget = selector ? nextDocument.querySelector(selector) : null;

    if (! currentTarget || ! nextTarget) {
        return false;
    }

    currentTarget.querySelectorAll('select').forEach((select) => select.tomselect?.destroy());
    const replacement = document.importNode(nextTarget, true);
    currentTarget.replaceWith(replacement);
    replaceLiveFilterSummaries(nextDocument);
    initializeSearchableSelects(replacement);

    return true;
};

const submitLiveFilters = async (form, requestedUrl = null, historyMode = 'replace') => {
    const state = liveFilterState(form);
    const url = requestedUrl ? new URL(requestedUrl, window.location.href) : liveFilterUrl(form);

    state.controller?.abort();
    state.controller = new AbortController();
    const controller = state.controller;
    setLiveFilterLoading(form, true);
    setLiveFilterStatus(form, 'Se actualizează rezultatele.');

    try {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (! response.ok) {
            throw new Error('Filtrarea nu a putut fi actualizată.');
        }

        const nextDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
        if (! replaceLiveFilterResults(form, nextDocument)) {
            window.location.assign(response.url || url.href);

            return;
        }

        document.title = nextDocument.title || document.title;
        window.history[historyMode === 'push' ? 'pushState' : 'replaceState'](
            { gafcoLiveFilters: true },
            '',
            url.href,
        );
        setLiveFilterStatus(form, 'Rezultatele au fost actualizate.');
    } catch (error) {
        if (error.name !== 'AbortError') {
            window.location.assign(url.href);
        }
    } finally {
        if (state.controller === controller) {
            state.controller = null;
            setLiveFilterLoading(form, false);
        }
    }
};

const initializeLiveFilterForm = (form) => {
    if (! liveFilterTarget(form)) {
        return;
    }

    const state = liveFilterState(form);
    const status = document.createElement('span');
    status.className = 'visually-hidden';
    status.dataset.liveFilterStatus = '';
    status.setAttribute('aria-live', 'polite');
    form.append(status);

    form.querySelectorAll(liveFilterSearchSelector).forEach((search) => {
        search.title = 'Căutarea automată pornește după minimum 2 caractere.';
        search.addEventListener('input', () => {
            window.clearTimeout(state.timer);
            state.controller?.abort();
            const length = search.value.trim().length;

            if (length === 1) {
                setLiveFilterLoading(form, false);
                setLiveFilterStatus(form, 'Mai scrie un caracter pentru a porni căutarea automată.');

                return;
            }

            state.timer = window.setTimeout(() => submitLiveFilters(form), liveFilterDelay);
        });
    });

    form.querySelectorAll('select[name], input[type="checkbox"][name], input[type="radio"][name], input[type="date"][name]').forEach((field) => {
        field.addEventListener('change', () => form.requestSubmit());
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(state.timer);
        submitLiveFilters(form);
    });
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-smart-back]');

    if (! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || ! document.referrer) {
        return;
    }

    const previous = new URL(document.referrer);

    if (previous.origin !== window.location.origin || previous.href === window.location.href) {
        return;
    }

    event.preventDefault();
    window.history.back();
});

document.addEventListener('click', (event) => {
    const row = event.target.closest('[data-href]');
    if (! row || event.target.closest('a, button, input, select, textarea, label')) {
        return;
    }

    window.location.assign(row.dataset.href);
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('[data-live-filter-results] .pagination a');
    if (! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const target = link.closest('[data-live-filter-results]');
    const form = Array.from(document.querySelectorAll('[data-auto-submit-filters]'))
        .find((candidate) => liveFilterTarget(candidate) === target);
    if (! form || new URL(link.href).origin !== window.location.origin) {
        return;
    }

    event.preventDefault();
    submitLiveFilters(form, link.href, 'push');
});

window.addEventListener('popstate', () => {
    if (document.querySelector('[data-auto-submit-filters][data-live-filter-target]')) {
        window.location.reload();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-flash-message]').forEach((message) => {
        const timeout = Math.max(1000, Number(message.dataset.flashTimeout) || 4500);
        window.setTimeout(() => {
            const closeButton = message.querySelector('[data-bs-dismiss="alert"]');
            if (closeButton) {
                closeButton.click();
            } else {
                message.remove();
            }
        }, timeout);
    });

    const documentViewers = Array.from(document.querySelectorAll('[data-document-viewer]'));
    documentViewers.forEach((viewer) => {
        const initialId = viewer.dataset.documentInitialId;
        const initialTrigger = Array.from(viewer.querySelectorAll('[data-document-preview-trigger]'))
            .find((trigger) => trigger.dataset.documentId === initialId);

        if (viewer.classList.contains('reception-document-viewer--workspace') && ! documentViewerMobile.matches) {
            openDocumentViewer(viewer, initialTrigger, false);
        } else {
            viewer.setAttribute('aria-hidden', 'true');
            setDocumentViewerStatus(viewer, initialTrigger ? 'loading' : 'empty');
        }
    });

    document.addEventListener('click', (event) => {
        const previewTrigger = event.target.closest('[data-document-preview-trigger]');
        if (previewTrigger) {
            const viewer = document.getElementById(previewTrigger.dataset.documentViewerTarget);
            if (viewer) {
                openDocumentViewer(viewer, previewTrigger);
            }
            return;
        }

        const closeButton = event.target.closest('[data-document-viewer-close]');
        if (closeButton) {
            const viewer = closeButton.closest('[data-document-viewer]');
            if (viewer) {
                closeDocumentViewer(viewer);
            }
            return;
        }

        const expandButton = event.target.closest('[data-document-viewer-expand]');
        if (expandButton) {
            const viewer = expandButton.closest('[data-document-viewer]');
            if (viewer) {
                setDocumentViewerExpanded(viewer, ! viewer.classList.contains('is-expanded'));
            }
            return;
        }

        const imageAction = event.target.closest('[data-document-image-action]');
        if (! imageAction) {
            return;
        }
        const viewer = imageAction.closest('[data-document-viewer]');
        if (! viewer) {
            return;
        }
        const state = documentViewerState(viewer);
        if (imageAction.dataset.documentImageAction === 'zoom-in') {
            state.zoom = Math.min(3, Number((state.zoom + 0.25).toFixed(2)));
        } else if (imageAction.dataset.documentImageAction === 'zoom-out') {
            state.zoom = Math.max(0.5, Number((state.zoom - 0.25).toFixed(2)));
        } else if (imageAction.dataset.documentImageAction === 'rotate') {
            state.rotation = (state.rotation + 90) % 360;
        } else {
            state.zoom = 1;
            state.rotation = 0;
        }
        updateDocumentImageTransform(viewer);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }
        const viewer = document.querySelector('[data-document-viewer].is-expanded')
            ?? document.querySelector('[data-document-viewer].is-open');
        if (! viewer) {
            return;
        }
        if (viewer.classList.contains('is-expanded')) {
            setDocumentViewerExpanded(viewer, false);
        } else {
            closeDocumentViewer(viewer);
        }
    });

    documentViewerMobile.addEventListener('change', (event) => {
        document.querySelectorAll('.reception-document-viewer--workspace').forEach((viewer) => {
            setDocumentViewerExpanded(viewer, false);
            viewer.classList.remove('is-open');
            const workspace = viewer.closest('[data-document-viewer-workspace]');
            viewer.setAttribute(
                'aria-hidden',
                event.matches || workspace?.classList.contains('is-viewer-collapsed') ? 'true' : 'false',
            );
            if (! event.matches && ! documentViewerState(viewer).documentId) {
                const initialId = viewer.dataset.documentInitialId;
                const initialTrigger = Array.from(viewer.querySelectorAll('[data-document-preview-trigger]'))
                    .find((trigger) => trigger.dataset.documentId === initialId);
                selectDocumentInViewer(viewer, initialTrigger);
            }
        });
        syncDocumentViewerBodyLock();
    });

    initializeSearchableSelects();
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    initializeSearchableSelects(node);
                }
            });
        });
    }).observe(document.body, { childList: true, subtree: true });

    document.querySelectorAll('[data-auto-submit-filters]').forEach(initializeLiveFilterForm);

    document.querySelectorAll('[data-live-view]').forEach((liveView) => {
        const intervalSeconds = Math.max(60, Number(liveView.dataset.liveViewInterval) || 300);
        const storageKey = `gafco.live-view.${liveView.dataset.liveViewKey}`;
        const status = liveView.querySelector('[data-live-view-status]');
        const toggle = liveView.querySelector('[data-live-view-toggle]');
        const toggleIcon = toggle?.querySelector('i');
        const toggleLabel = toggle?.querySelector('.visually-hidden');
        const refresh = liveView.querySelector('[data-live-view-refresh]');
        let enabled = true;
        let deadline = Date.now() + (intervalSeconds * 1000);

        try {
            enabled = window.localStorage.getItem(storageKey) !== 'paused';
        } catch {
            enabled = true;
        }

        const setStoredState = () => {
            try {
                window.localStorage.setItem(storageKey, enabled ? 'active' : 'paused');
            } catch {
                // Prefer a working live view even when browser storage is unavailable.
            }
        };

        const syncToggle = () => {
            liveView.classList.toggle('live-view-paused', !enabled);
            toggle?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
            toggle?.setAttribute('title', enabled ? 'Oprește actualizarea automată' : 'Pornește actualizarea automată');
            toggleIcon?.classList.toggle('fa-pause', enabled);
            toggleIcon?.classList.toggle('fa-play', !enabled);
            if (toggleLabel) {
                toggleLabel.textContent = enabled ? 'Oprește actualizarea automată' : 'Pornește actualizarea automată';
            }
            if (!enabled && status) {
                status.textContent = 'Actualizare oprită';
            }
        };

        const postponeRefresh = (message) => {
            deadline = Date.now() + 30000;
            if (status) {
                status.textContent = message;
            }
        };

        const refreshPage = () => {
            const activeElement = document.activeElement;
            if (document.hidden) {
                postponeRefresh('Actualizare amânată · pagina nu este activă');
                return;
            }
            if (activeElement?.matches('input, textarea, select, [contenteditable="true"]')) {
                postponeRefresh('Actualizare amânată cât timp editezi');
                return;
            }
            window.location.reload();
        };

        const tick = () => {
            if (!enabled) {
                return;
            }
            const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            if (remaining <= 0) {
                refreshPage();
                return;
            }
            const minutes = Math.floor(remaining / 60);
            const seconds = String(remaining % 60).padStart(2, '0');
            if (status) {
                status.textContent = `Actualizare în ${minutes}:${seconds}`;
            }
        };

        toggle?.addEventListener('click', () => {
            enabled = !enabled;
            deadline = Date.now() + (intervalSeconds * 1000);
            setStoredState();
            syncToggle();
            tick();
        });
        refresh?.addEventListener('click', refreshPage);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && enabled) {
                tick();
            }
        });

        syncToggle();
        tick();
        window.setInterval(tick, 1000);
    });

    const inventoryForm = document.querySelector('[data-inventory-filters]');
    if (inventoryForm) {
        const status = inventoryForm.querySelector('[data-inventory-save-status]');
        const preferenceUrl = inventoryForm.dataset.preferencesUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

        const preferencePayload = () => ({
            columns: Array.from(inventoryForm.querySelectorAll('[data-inventory-column]:checked')).map((input) => input.value),
            density: inventoryForm.querySelector('[data-inventory-density]')?.value ?? 'compact',
        });

        const savePreferences = async () => {
            if (!preferenceUrl || !csrfToken) {
                return;
            }

            status.textContent = 'Se salvează preferințele…';
            const response = await fetch(preferenceUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(preferencePayload()),
            });
            if (!response.ok) {
                throw new Error('Preferințele nu au putut fi salvate.');
            }
            status.textContent = 'Filtrele și coloanele sunt salvate în contul tău.';
        };

        const saveAndSubmit = async () => {
            try {
                await savePreferences();
            } catch (error) {
                status.textContent = error.message;
            }
            inventoryForm.requestSubmit();
        };

        inventoryForm.querySelectorAll('[data-inventory-column], [data-inventory-density]').forEach((element) => {
            element.addEventListener('change', async () => {
                const selectedColumns = inventoryForm.querySelectorAll('[data-inventory-column]:checked');
                if (!selectedColumns.length && element.matches('[data-inventory-column]')) {
                    element.checked = true;
                    return;
                }
                await saveAndSubmit();
            });
        });
    }

    const impersonationSelector = document.querySelector('[data-impersonation-selector]');
    if (impersonationSelector) {
        const search = impersonationSelector.querySelector('[data-impersonation-search]');
        const role = impersonationSelector.querySelector('[data-impersonation-role]');
        const results = impersonationSelector.querySelector('[data-impersonation-results]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        let searchTimer;
        let activeRequest;

        const loadingState = () => {
            results.replaceChildren();
            const loading = document.createElement('div');
            loading.className = 'impersonation-loading';
            const spinner = document.createElement('span');
            spinner.className = 'spinner-border spinner-border-sm';
            spinner.setAttribute('aria-hidden', 'true');
            loading.append(spinner, document.createTextNode(' Se încarcă utilizatorii…'));
            results.append(loading);
        };

        const messageState = (message, tone = 'muted') => {
            results.replaceChildren();
            const notice = document.createElement('div');
            notice.className = `impersonation-empty text-${tone}`;
            notice.textContent = message;
            results.append(notice);
        };

        const renderUsers = (users) => {
            results.replaceChildren();
            if (!users.length) {
                messageState('Nu a fost găsit niciun utilizator eligibil.');
                return;
            }

            users.forEach((user) => {
                const item = document.createElement('div');
                item.className = 'impersonation-user';

                const identity = document.createElement('div');
                identity.className = 'impersonation-user-identity';

                const avatar = document.createElement('span');
                avatar.className = 'impersonation-user-avatar';
                avatar.textContent = user.name.trim().charAt(0).toUpperCase() || '?';

                const copy = document.createElement('div');
                copy.className = 'min-w-0';

                const heading = document.createElement('div');
                heading.className = 'impersonation-user-name';
                heading.textContent = user.name;
                if (user.recent) {
                    const recent = document.createElement('span');
                    recent.className = 'badge rounded-pill text-bg-light border ms-2';
                    recent.textContent = 'Recent';
                    heading.append(recent);
                }

                const meta = document.createElement('div');
                meta.className = 'impersonation-user-meta';
                const roles = user.roles.length ? user.roles.join(', ') : 'Fără rol';
                meta.textContent = `${user.login_code} · ${roles}`;

                copy.append(heading, meta);
                identity.append(avatar, copy);

                const form = document.createElement('form');
                form.method = 'post';
                form.action = user.take_url;

                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = csrfToken ?? '';

                const button = document.createElement('button');
                button.className = 'btn btn-sm btn-warning';
                button.type = 'submit';
                button.innerHTML = '<i class="fa-solid fa-arrow-right-to-bracket me-1"></i>Intră';

                form.append(token, button);
                item.append(identity, form);
                results.append(item);
            });
        };

        const loadUsers = async () => {
            activeRequest?.abort();
            activeRequest = new AbortController();
            loadingState();

            const url = new URL(impersonationSelector.dataset.usersUrl, window.location.origin);
            if (search.value.trim()) {
                url.searchParams.set('search', search.value.trim());
            }
            if (role.value) {
                url.searchParams.set('role', role.value);
            }

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
                });
                if (!response.ok) {
                    throw new Error('Lista utilizatorilor nu a putut fi încărcată.');
                }
                const payload = await response.json();
                renderUsers(payload.users ?? []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    messageState(error.message, 'danger');
                }
            }
        };

        impersonationSelector.addEventListener('show.bs.modal', loadUsers);
        search?.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(loadUsers, 250);
        });
        role?.addEventListener('change', loadUsers);
    }

    document.querySelectorAll('[data-attachment-builder]').forEach((builder) => {
        const list = builder.querySelector('[data-attachment-list]');
        const template = builder.querySelector('[data-attachment-template]');
        const addButton = builder.querySelector('[data-add-attachment]');
        const required = builder.dataset.required === '1';

        const nextIndex = () => Array.from(list.querySelectorAll('[name^="attachments["]'))
            .map((input) => Number(input.name.match(/^attachments\[(\d+)]/)?.[1] ?? -1))
            .reduce((maximum, value) => Math.max(maximum, value), -1) + 1;

        const syncCustomLabel = (row) => {
            const type = row.querySelector('[data-attachment-type]');
            const wrapper = row.querySelector('[data-custom-label-wrap]');
            const input = wrapper?.querySelector('input');
            const custom = type?.value === 'custom';
            wrapper?.classList.toggle('d-none', !custom);
            if (input) {
                input.required = custom;
                if (!custom) {
                    input.value = '';
                }
            }
        };

        const syncFileName = (row) => {
            const input = row.querySelector('[data-attachment-file]');
            const label = row.querySelector('[data-attachment-file-name]');
            if (label) {
                label.textContent = input?.files?.[0]?.name || 'Niciun fișier selectat';
            }
        };

        const syncRemoveButtons = () => {
            const rows = list.querySelectorAll('[data-attachment-row]');
            const removalUnavailable = required && rows.length === 1;

            rows.forEach((row) => {
                const removeButton = row.querySelector('[data-remove-attachment]');
                if (removeButton) {
                    removeButton.hidden = removalUnavailable;
                    removeButton.disabled = removalUnavailable;
                }
            });
        };

        const bindRow = (row) => {
            row.querySelector('[data-attachment-type]')?.addEventListener('change', () => syncCustomLabel(row));
            row.querySelector('[data-attachment-file]')?.addEventListener('change', () => syncFileName(row));
            row.querySelector('[data-remove-attachment]')?.addEventListener('click', () => {
                const rows = list.querySelectorAll('[data-attachment-row]');
                if (required && rows.length === 1) {
                    return;
                }
                row.remove();
                syncRemoveButtons();
            });
            syncCustomLabel(row);
            syncFileName(row);
        };

        list.querySelectorAll('[data-attachment-row]').forEach(bindRow);
        syncRemoveButtons();
        addButton?.addEventListener('click', () => {
            if (list.querySelectorAll('[data-attachment-row]').length >= 10) {
                return;
            }
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(nextIndex())).trim();
            const row = wrapper.firstElementChild;
            if (row) {
                list.append(row);
                bindRow(row);
                syncRemoveButtons();
                revealAddedRow(row, row.querySelector('input[type="file"]'));
            }
        });
    });

    document.querySelectorAll('[data-reception-lines]').forEach((builder) => {
        const list = builder.querySelector('[data-reception-line-list]');
        const template = builder.querySelector('[data-reception-line-template]');
        const addButton = builder.querySelector('[data-add-reception-line]');

        const renumber = () => {
            list.querySelectorAll('[data-reception-line]').forEach((row, index) => {
                const number = row.querySelector('.reception-line-number');
                if (number) {
                    number.textContent = `Material ${index + 1}`;
                }
                const remove = row.querySelector('[data-remove-reception-line]');
                if (remove) {
                    remove.disabled = list.querySelectorAll('[data-reception-line]').length === 1;
                }
            });
        };

        const bindRow = (row) => {
            row.querySelector('[data-remove-reception-line]')?.addEventListener('click', () => {
                if (list.querySelectorAll('[data-reception-line]').length > 1) {
                    row.remove();
                    renumber();
                }
            });
        };

        list.querySelectorAll('[data-reception-line]').forEach(bindRow);
        addButton?.addEventListener('click', () => {
            const index = list.querySelectorAll('[data-reception-line]').length
                ? Math.max(...Array.from(list.querySelectorAll('[name^="lines["]')).map((input) => Number(input.name.match(/^lines\[(\d+)]/)?.[1] ?? -1))) + 1
                : 0;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index)).trim();
            const row = wrapper.firstElementChild;
            if (row) {
                list.append(row);
                bindRow(row);
                renumber();
                initializeSearchableSelects(row);
                revealAddedRow(row, row.querySelector('select'));
            }
        });
        renumber();
    });

});
