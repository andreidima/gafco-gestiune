@extends('layouts.app')

@section('title', 'Reguli de alertare')

@section('content')
<div class="resource-shell">
    <x-resource-page-header
        title="Reguli de alertare"
        description="Regula locației are prioritate față de regula rolului, iar regula rolului are prioritate față de regula generală."
        :count="$rules->count()"
        icon="fa-sliders"
    >
        <x-slot:actions>
            <a href="{{ route('alerts.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Alerte
            </a>
        </x-slot:actions>
    </x-resource-page-header>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white">
            <strong>Adaugă sau actualizează o excepție</strong>
        </div>
        <div class="card-body">
            <form method="post" action="{{ route('alert-rules.store') }}" class="row g-2 align-items-end" data-alert-rule-form>
                @csrf
                <div class="col-lg-3 col-md-6">
                    <label class="resource-filter-label">Tip alertă</label>
                    <select name="alert_type" class="form-select" required>
                        @foreach($definitions as $value => $definition)
                            <option value="{{ $value }}">{{ $definition['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="resource-filter-label">Nivel</label>
                    <select name="scope_type" class="form-select" required data-alert-scope>
                        <option value="role">Rol</option>
                        <option value="location">Locație</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6" data-alert-role>
                    <label class="resource-filter-label">Rol</label>
                    <select name="role_name" class="form-select">
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2 col-md-6 d-none" data-alert-location>
                    <label class="resource-filter-label">Locație</label>
                    <select name="location_id" class="form-select" data-tom-select>
                        @foreach($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }} — {{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-1 col-md-3">
                    <label class="resource-filter-label">Activă</label>
                    <select name="enabled" class="form-select">
                        <option value="1">Da</option>
                        <option value="0">Nu</option>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3">
                    <label class="resource-filter-label">Zile</label>
                    <input type="number" name="threshold_days" min="0" max="365" value="30" class="form-control" required>
                </div>
                <div class="col-lg-1 col-md-6">
                    <button class="btn btn-primary w-100"><i class="fa-solid fa-check"></i></button>
                </div>
            </form>
            <p class="small text-secondary mb-0 mt-2">
                Pentru loturi, numărul reprezintă câte zile înainte de expirare apare alerta. Pentru documente, reprezintă câte zile pot rămâne neprocesate.
            </p>
        </div>
    </div>

    <div class="resource-table-card">
        <div class="table-responsive">
            <table class="table resource-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Tip alertă</th>
                        <th>Nivel</th>
                        <th>Activă</th>
                        <th>Prag</th>
                        <th>Ultima modificare</th>
                        <th class="text-end"><span class="visually-hidden">Acțiuni</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rules as $rule)
                    @php
                        $scopeLabel = match($rule->scope_type) {
                            'system' => 'Regulă generală',
                            'role' => 'Rol: '.($roles[$rule->role_name] ?? $rule->role_name),
                            'location' => 'Locație: '.($rule->location?->code ?? 'indisponibilă'),
                            default => $rule->scope_key,
                        };
                    @endphp
                    <tr>
                        <td>
                            <strong class="d-block">{{ $definitions[$rule->alert_type]['label'] ?? $rule->alert_type }}</strong>
                            <span class="resource-secondary d-block">{{ $definitions[$rule->alert_type]['description'] ?? '' }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $rule->scope_type === 'system' ? 'text-bg-primary' : 'text-bg-light border' }}">{{ $scopeLabel }}</span>
                        </td>
                        <td colspan="2">
                            <form method="post" action="{{ route('alert-rules.store') }}" class="d-flex gap-2 align-items-center">
                                @csrf
                                <input type="hidden" name="alert_type" value="{{ $rule->alert_type }}">
                                <input type="hidden" name="scope_type" value="{{ $rule->scope_type }}">
                                <input type="hidden" name="role_name" value="{{ $rule->role_name }}">
                                <input type="hidden" name="location_id" value="{{ $rule->location_id }}">
                                <select name="enabled" class="form-select form-select-sm" style="max-width: 90px">
                                    <option value="1" @selected($rule->enabled)>Da</option>
                                    <option value="0" @selected(! $rule->enabled)>Nu</option>
                                </select>
                                <div class="input-group input-group-sm" style="max-width: 120px">
                                    <input type="number" name="threshold_days" min="0" max="365" value="{{ $rule->threshold_days }}" class="form-control" required>
                                    <span class="input-group-text">zile</span>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" title="Salvează regula">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <span class="d-block">{{ $rule->updated_at->format('d.m.Y H:i') }}</span>
                            <span class="resource-secondary d-block">{{ $rule->changedBy?->name ?? 'Sistem' }}</span>
                        </td>
                        <td class="text-end">
                            @if($rule->scope_type !== 'system')
                                <form method="post" action="{{ route('alert-rules.destroy', $rule) }}" onsubmit="return confirm('Elimini această excepție?')">
                                    @csrf
                                    @method('delete')
                                    <button class="btn btn-sm btn-outline-danger" title="Elimină excepția">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-alert-rule-form]').forEach((form) => {
    const scope = form.querySelector('[data-alert-scope]');
    const role = form.querySelector('[data-alert-role]');
    const location = form.querySelector('[data-alert-location]');
    const refresh = () => {
        const locationSelected = scope.value === 'location';
        role.classList.toggle('d-none', locationSelected);
        location.classList.toggle('d-none', !locationSelected);
    };
    scope.addEventListener('change', refresh);
    refresh();
});
</script>
@endpush
