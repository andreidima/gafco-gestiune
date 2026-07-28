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

        const preferencePayload = (overrides = {}) => ({
            filters: overrides.filters ?? {
                search: search?.value ?? '',
                location_id: inventoryForm.querySelector('[name="location_id"]')?.value || null,
                hide_zero: inventoryForm.querySelector('[name="hide_zero"][type="checkbox"]')?.checked ?? false,
            },
            columns: Array.from(inventoryForm.querySelectorAll('[data-inventory-column]:checked')).map((input) => input.value),
            density: inventoryForm.querySelector('[data-inventory-density]')?.value ?? 'compact',
        });

        const savePreferences = async (overrides = {}) => {
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
                body: JSON.stringify(preferencePayload(overrides)),
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
            element.addEventListener('change', saveAndSubmit);
        });
        search?.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(saveAndSubmit, 350);
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
        inventoryForm.querySelector('[data-inventory-reset]')?.addEventListener('click', async (event) => {
            event.preventDefault();
            const resetUrl = event.currentTarget.href;
            try {
                await savePreferences({
                    filters: { search: '', location_id: null, hide_zero: false },
                });
            } catch (error) {
                status.textContent = error.message;
            }
            window.location.assign(resetUrl);
        });
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
