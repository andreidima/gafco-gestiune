import '../scss/app.scss';
import 'bootstrap';
import TomSelect from 'tom-select';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-tom-select]').forEach((element) => {
        new TomSelect(element, {
            allowEmptyOption: true,
            create: false,
        });
    });

    const inventoryForm = document.querySelector('[data-inventory-filters]');
    if (inventoryForm) {
        const search = inventoryForm.querySelector('[data-inventory-search]');
        const status = inventoryForm.querySelector('[data-inventory-save-status]');
        const preferenceUrl = inventoryForm.dataset.preferencesUrl;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        let searchTimer;

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

        inventoryForm.querySelectorAll('[data-inventory-change]').forEach((element) => {
            element.addEventListener('change', () => inventoryForm.requestSubmit());
        });
        search?.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => inventoryForm.requestSubmit(), 350);
        });
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
});
