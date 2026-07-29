import '../scss/app.scss';
import 'bootstrap';
import TomSelect from 'tom-select';

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

    document.querySelectorAll('[data-tom-select]').forEach((element) => {
        const settings = {
            allowEmptyOption: true,
            create: false,
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

        const select = new TomSelect(element, settings);
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
    });

    document.querySelectorAll('[data-auto-submit-filters]').forEach((form) => {
        let searchTimer;

        form.querySelectorAll('input[name="search"], input[name$="_search"]').forEach((search) => {
            search.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => form.requestSubmit(), 400);
            });
        });

        form.querySelectorAll('select[name], input[type="checkbox"][name], input[type="radio"][name], input[type="date"][name]').forEach((field) => {
            field.addEventListener('change', () => form.requestSubmit());
        });
    });

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

    document.querySelectorAll('[data-href]').forEach((row) => {
        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea, label')) {
                return;
            }
            window.location.assign(row.dataset.href);
        });
    });

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

        const bindRow = (row) => {
            row.querySelector('[data-attachment-type]')?.addEventListener('change', () => syncCustomLabel(row));
            row.querySelector('[data-remove-attachment]')?.addEventListener('click', () => {
                const rows = list.querySelectorAll('[data-attachment-row]');
                if (required && rows.length === 1) {
                    row.querySelector('input[type="file"]')?.click();
                    return;
                }
                row.remove();
            });
            syncCustomLabel(row);
        };

        list.querySelectorAll('[data-attachment-row]').forEach(bindRow);
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
                row.querySelector('input[type="file"]')?.focus();
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
                row.querySelector('select')?.focus();
            }
        });
        renumber();
    });

});
