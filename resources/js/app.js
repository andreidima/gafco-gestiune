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

    const allocationForm = document.querySelector('[data-consumption-allocation]');
    if (allocationForm) {
        const locationInput = allocationForm.querySelector('[name="location_id"]');
        const itemInput = allocationForm.querySelector('[name="catalog_item_id"]');
        const quantityInput = allocationForm.querySelector('[name="quantity"]');
        const state = allocationForm.querySelector('[data-allocation-state]');
        const tableWrap = allocationForm.querySelector('[data-allocation-table-wrap]');
        const rows = allocationForm.querySelector('[data-allocation-rows]');
        const totalBadge = allocationForm.querySelector('[data-allocation-total]');
        let timer;
        let activeRequest;
        let oldAllocations;
        try {
            oldAllocations = JSON.parse(allocationForm.dataset.oldAllocations || '[]');
        } catch {
            oldAllocations = [];
        }

        const formatQuantity = (value) => Number(value).toLocaleString('ro-RO', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 3,
        });

        const updateTotal = () => {
            const requested = Number(quantityInput.value || 0);
            const allocated = Array.from(rows.querySelectorAll('[data-allocation-quantity]'))
                .reduce((total, input) => total + Number(input.value || 0), 0);
            const matches = Math.abs(requested - allocated) <= 0.0005 && requested > 0;
            totalBadge.className = `badge ${matches ? 'text-bg-success' : 'text-bg-warning'}`;
            totalBadge.textContent = `${formatQuantity(allocated)} din ${formatQuantity(requested)} alocate`;
        };

        const renderProposal = (allocations) => {
            rows.replaceChildren();
            allocations.forEach((allocation, index) => {
                const row = document.createElement('tr');

                const lot = document.createElement('td');
                const lotName = document.createElement('strong');
                lotName.textContent = allocation.label;
                lot.append(lotName);

                const supplier = document.createElement('td');
                supplier.textContent = allocation.supplier || '—';

                const received = document.createElement('td');
                received.textContent = allocation.received_at || '—';

                const expires = document.createElement('td');
                expires.textContent = allocation.expires_at || '—';

                const available = document.createElement('td');
                available.className = 'text-end text-nowrap';
                available.textContent = formatQuantity(allocation.available);

                const amount = document.createElement('td');
                amount.className = 'text-end';
                const lotId = document.createElement('input');
                lotId.type = 'hidden';
                lotId.name = `allocations[${index}][inventory_lot_id]`;
                lotId.value = allocation.inventory_lot_id;
                const quantity = document.createElement('input');
                quantity.type = 'number';
                quantity.name = `allocations[${index}][quantity]`;
                quantity.className = 'form-control form-control-sm text-end consumption-allocation-input';
                quantity.step = '0.001';
                quantity.min = '0';
                quantity.max = allocation.available;
                quantity.dataset.allocationQuantity = '';
                const previous = oldAllocations.find((entry) => Number(entry.inventory_lot_id) === Number(allocation.inventory_lot_id));
                quantity.value = previous?.quantity ?? allocation.quantity;
                quantity.addEventListener('input', updateTotal);
                amount.append(lotId, quantity);

                row.append(lot, supplier, received, expires, available, amount);
                rows.append(row);
            });
            oldAllocations = [];
            state.classList.add('d-none');
            tableWrap.classList.remove('d-none');
            updateTotal();
        };

        const showState = (message, tone = 'secondary') => {
            rows.replaceChildren();
            tableWrap.classList.add('d-none');
            state.className = `consumption-allocation-state mt-3 text-${tone}`;
            state.textContent = message;
            totalBadge.className = 'badge text-bg-light border';
            totalBadge.textContent = 'Fără propunere';
        };

        const loadProposal = async () => {
            activeRequest?.abort();
            if (!locationInput.value || !itemInput.value || Number(quantityInput.value) <= 0) {
                showState('Alege locația, materialul și o cantitate mai mare decât zero.');
                return;
            }

            activeRequest = new AbortController();
            showState('Se calculează propunerea FIFO/FEFO…');
            const url = new URL(allocationForm.dataset.allocationUrl, window.location.origin);
            url.searchParams.set('location_id', locationInput.value);
            url.searchParams.set('catalog_item_id', itemInput.value);
            url.searchParams.set('quantity', quantityInput.value);

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
                });
                const payload = await response.json();
                if (!response.ok) {
                    const message = Object.values(payload.errors ?? {}).flat()[0]
                        ?? 'Propunerea nu a putut fi calculată.';
                    throw new Error(message);
                }
                renderProposal(payload.allocations ?? []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    showState(error.message, 'danger');
                }
            }
        };

        const scheduleProposal = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(loadProposal, 300);
        };
        locationInput.addEventListener('change', scheduleProposal);
        itemInput.addEventListener('change', scheduleProposal);
        quantityInput.addEventListener('input', scheduleProposal);
        if (locationInput.value && itemInput.value && Number(quantityInput.value) > 0) {
            loadProposal();
        }
    }
});
