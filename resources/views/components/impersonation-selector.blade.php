<div
    class="modal fade"
    id="impersonationModal"
    tabindex="-1"
    aria-labelledby="impersonationModalLabel"
    aria-hidden="true"
    data-impersonation-selector
    data-users-url="{{ route('impersonation.users') }}"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content impersonation-modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="impersonationModalLabel">
                        <i class="fa-solid fa-user-secret me-2"></i>Schimbă utilizatorul
                    </h2>
                    <p class="mb-0 mt-1 text-muted small">
                        Vei vedea aplicația și vei lucra cu drepturile utilizatorului ales.
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Închide"></button>
            </div>
            <div class="modal-body">
                <div class="impersonation-search-panel">
                    <label class="visually-hidden" for="impersonationSearch">Caută utilizatorul</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input
                            id="impersonationSearch"
                            type="search"
                            class="form-control"
                            placeholder="Nume sau cod de conectare"
                            autocomplete="off"
                            data-impersonation-search
                        >
                    </div>
                    <label class="visually-hidden" for="impersonationRole">Filtrează după rol</label>
                    <select id="impersonationRole" class="form-select" data-impersonation-role>
                        <option value="">Toate rolurile</option>
                        @foreach(config('roles.labels', []) as $roleName => $roleLabel)
                            @unless(in_array($roleName, ['admin', 'super-admin'], true))
                                <option value="{{ $roleName }}">{{ $roleLabel }}</option>
                            @endunless
                        @endforeach
                    </select>
                </div>

                <div class="impersonation-results" data-impersonation-results>
                    <div class="impersonation-loading">
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        Se încarcă utilizatorii…
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted">
                    Conturile administrative și cele inactive nu pot fi selectate.
                </span>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Închide</button>
            </div>
        </div>
    </div>
</div>
