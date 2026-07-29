@extends('layouts.app')

@section('title', 'Custodia mea')

@section('content')
@php
    $operationLabels = ['issue' => 'Alocare din locație', 'handoff' => 'Predare între persoane', 'return' => 'Retur la locație'];
    $conditionLabels = ['good' => 'Bun', 'used' => 'Uzură normală', 'damaged' => 'Deteriorat', 'needs_service' => 'Necesită service'];
    $driverPrivacy = auth()->user()->usesDriverWorkspace();
    $personLabel = static fn ($person) => ! $person
        ? '—'
        : ((int) $person->id === (int) auth()->id() ? 'Tu' : ($driverPrivacy ? 'Coleg' : $person->name));
    $approvalText = static function ($transfer): string {
        if ($transfer->status === 'accepted') {
            return 'Confirmări finalizate';
        }
        if ($transfer->status === 'rejected') {
            return 'Operațiune refuzată';
        }
        if ($transfer->status === 'expired') {
            return 'Termen de confirmare expirat';
        }
        $from = $transfer->from_approved_at ? 'predare confirmată' : 'așteaptă persoana care predă';
        $to = $transfer->to_approved_at
            ? ($transfer->operation_type === 'return' ? 'locație confirmată' : 'primire confirmată')
            : ($transfer->operation_type === 'return' ? 'așteaptă responsabilul locației' : 'așteaptă persoana care primește');

        return ucfirst($from).' · '.$to;
    };
    $holdingEntries = $ownAssets
        ->map(fn ($asset) => ['type' => 'equipment', 'record' => $asset])
        ->concat($ownMaterialCustodies->map(fn ($holding) => ['type' => 'material', 'record' => $holding]))
        ->values();
@endphp
<div class="custody-shell">
    <section class="custody-hero">
        <div>
            <span class="dashboard-pill"><i class="fa-solid fa-hand-holding-hand"></i> Evidență personală</span>
            <h1>Custodia mea</h1>
            <p>Vezi ce ai în responsabilitate, confirmă predările și inițiază un retur fără să pierzi istoricul.</p>
        </div>
        @if($canInitiateCustody)
            <a href="#custody-new-operation" class="btn btn-primary rounded-3">
                <i class="fa-solid fa-plus me-1"></i> Operațiune nouă
            </a>
        @endif
    </section>

    @unless($expandedCustodyAvailable)
        <div class="alert alert-warning">Actualizarea bazei de date este în curs. Operațiunile noi vor deveni disponibile imediat după finalizarea migrării.</div>
    @endunless

    <div class="custody-stats">
        <a href="#custody-decisions" class="custody-stat custody-stat-warning">
            <span><i class="fa-solid fa-circle-check"></i></span>
            <strong>{{ $pendingDecisions->count() }}</strong>
            <small>decizii pentru mine</small>
        </a>
        <a href="#custody-holdings" class="custody-stat">
            <span><i class="fa-solid fa-screwdriver-wrench"></i></span>
            <strong>{{ $ownAssets->count() }}</strong>
            <small>echipamente la mine</small>
        </a>
        <a href="#custody-holdings" class="custody-stat">
            <span><i class="fa-solid fa-box-open"></i></span>
            <strong>{{ $ownMaterialCustodies->count() }}</strong>
            <small>poziții de materiale</small>
        </a>
    </div>

    <section id="custody-decisions" class="custody-section">
        <div class="custody-section-heading">
            <div>
                <span class="custody-eyebrow">Prioritar</span>
                <h2>Necesită decizia mea</h2>
            </div>
            @if($pendingDecisions->isNotEmpty())<span class="badge rounded-pill text-bg-warning">{{ $pendingDecisions->count() }} în așteptare</span>@endif
        </div>

        <div class="custody-decision-grid">
            @forelse($pendingDecisions as $transfer)
                <article class="custody-decision-card">
                    <div class="custody-card-topline">
                        <span class="custody-type"><i class="fa-solid {{ $transfer->isMaterial() ? 'fa-box' : 'fa-screwdriver-wrench' }}"></i>{{ $operationLabels[$transfer->operation_type] ?? 'Predare' }}</span>
                        <span class="resource-code">{{ $transfer->qr_token }}</span>
                    </div>
                    <h3>{{ $transfer->itemLabel() }}</h3>
                    @if($transfer->isMaterial())
                        <div class="custody-quantity">{{ rtrim(rtrim(number_format((float) $transfer->quantity, 3, ',', '.'), '0'), ',') }} {{ $transfer->unit }}</div>
                    @endif
                    <div class="custody-route">
                        <span>{{ $transfer->fromUser ? $personLabel($transfer->fromUser) : $transfer->location?->name }}</span>
                        <i class="fa-solid fa-arrow-right"></i>
                        <span>{{ $transfer->operation_type === 'return' ? $transfer->location?->name : $personLabel($transfer->toUser) }}</span>
                    </div>
                    <p class="custody-approval-state">{{ $approvalText($transfer) }}</p>
                    @if($transfer->notes)<p class="custody-note"><i class="fa-regular fa-comment-dots"></i>{{ $transfer->notes }}</p>@endif

                    <form method="post" action="{{ route('custody-transfers.update', $transfer) }}" class="custody-approve-form">
                        @csrf
                        @method('put')
                        @if($transfer->operation_type === 'return' && ! $transfer->isMaterial())
                            <label class="form-label small fw-semibold mb-1">Stare la retur</label>
                            <select name="return_condition" class="form-select form-select-sm mb-2">
                                @foreach($conditionLabels as $value => $label)<option value="{{ $value }}" @selected($transfer->return_condition === $value)>{{ $label }}</option>@endforeach
                            </select>
                        @endif
                        <input name="response_notes" class="form-control form-control-sm mb-2" placeholder="Observație opțională">
                        <button name="decision" value="approved" class="btn btn-success w-100 rounded-3">
                            <i class="fa-solid fa-check me-1"></i> Confirmă
                        </button>
                    </form>
                    <details class="custody-reject">
                        <summary>Nu pot accepta</summary>
                        <form method="post" action="{{ route('custody-transfers.update', $transfer) }}" class="mt-2">
                            @csrf
                            @method('put')
                            <textarea name="response_notes" class="form-control form-control-sm mb-2" rows="2" required placeholder="De ce refuzi această operațiune?"></textarea>
                            <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger w-100">Refuză și trimite observația</button>
                        </form>
                    </details>
                </article>
            @empty
                <div class="custody-empty">
                    <i class="fa-regular fa-circle-check"></i>
                    <strong>Nu ai nimic de confirmat acum.</strong>
                    <span>Operațiunile noi vor apărea aici și în notificări.</span>
                </div>
            @endforelse
        </div>
    </section>

    <section id="custody-holdings" class="custody-section">
        <div class="custody-section-heading">
            <div>
                <span class="custody-eyebrow">Situația curentă</span>
                <h2>În responsabilitatea mea</h2>
            </div>
        </div>
        <div class="custody-holdings-grid">
            @foreach($holdingEntries->take(8) as $entry)
                @php($holding = $entry['record'])
                <article class="custody-holding-card">
                    <span class="custody-item-icon"><i class="fa-solid {{ $entry['type'] === 'equipment' ? 'fa-screwdriver-wrench' : 'fa-box' }}"></i></span>
                    <div class="min-w-0">
                        <strong>{{ $holding->catalogItem?->name }}</strong>
                        <span>{{ $entry['type'] === 'equipment' ? $holding->asset_code.' · '.($holding->currentLocation?->name ?? 'Fără locație') : $holding->location?->name }}</span>
                    </div>
                    @if($entry['type'] === 'equipment')
                        <span class="badge text-bg-light">{{ $conditionLabels[$holding->condition] ?? $holding->condition }}</span>
                    @else
                        <span class="custody-holding-quantity">{{ rtrim(rtrim(number_format((float) $holding->quantity, 3, ',', '.'), '0'), ',') }} {{ $holding->unit }}</span>
                    @endif
                </article>
            @endforeach
            @if($holdingEntries->isEmpty())
                <div class="custody-empty custody-empty-compact"><strong>Nu ai bunuri sau materiale în custodie.</strong></div>
            @endif
        </div>
        @if($holdingEntries->count() > 8)
            <details class="custody-more">
                <summary>Vezi încă {{ $holdingEntries->count() - 8 }} poziții</summary>
                <div class="custody-holdings-grid mt-2">
                    @foreach($holdingEntries->skip(8) as $entry)
                        @php($holding = $entry['record'])
                        <article class="custody-holding-card">
                            <span class="custody-item-icon"><i class="fa-solid {{ $entry['type'] === 'equipment' ? 'fa-screwdriver-wrench' : 'fa-box' }}"></i></span>
                            <div class="min-w-0">
                                <strong>{{ $holding->catalogItem?->name }}</strong>
                                <span>{{ $entry['type'] === 'equipment' ? $holding->asset_code.' · '.($holding->currentLocation?->name ?? 'Fără locație') : $holding->location?->name }}</span>
                            </div>
                            @if($entry['type'] === 'equipment')
                                <span class="badge text-bg-light">{{ $conditionLabels[$holding->condition] ?? $holding->condition }}</span>
                            @else
                                <span class="custody-holding-quantity">{{ rtrim(rtrim(number_format((float) $holding->quantity, 3, ',', '.'), '0'), ',') }} {{ $holding->unit }}</span>
                            @endif
                        </article>
                    @endforeach
                </div>
            </details>
        @endif
        <p class="custody-stock-note"><i class="fa-solid fa-circle-info"></i> Custodia arată responsabilul materialului. Cantitatea rămâne în stocul locației până când este înregistrat un consum sau un transfer de stoc.</p>
    </section>

    @if($expandedCustodyAvailable && $canInitiateCustody)
    <section id="custody-new-operation" class="custody-section">
        <div class="custody-section-heading">
            <div>
                <span class="custody-eyebrow">Flux ghidat</span>
                <h2>Operațiune nouă</h2>
            </div>
        </div>

        <div class="accordion custody-accordion" id="custodyOperationAccordion">
            @if($canIssueCustody)
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#custodyIssue">
                        <span class="custody-operation-icon"><i class="fa-solid fa-user-plus"></i></span>
                        <span><strong>Dă în custodie</strong><small>Dintr-o locație către o persoană</small></span>
                    </button>
                </h3>
                <div id="custodyIssue" class="accordion-collapse collapse" data-bs-parent="#custodyOperationAccordion">
                    <div class="accordion-body">
                        <form method="post" action="{{ route('custody-transfers.store') }}" class="custody-operation-form" data-custody-form>
                            @csrf
                            <input type="hidden" name="operation_type" value="issue">
                            <input type="hidden" name="location_id" data-material-location>
                            <div>
                                <label class="form-label">Tip</label>
                                <select name="item_type" class="form-select" data-item-type>
                                    <option value="equipment">Echipament individual</option>
                                    <option value="material">Material pe cantitate</option>
                                </select>
                            </div>
                            <div data-equipment-field>
                                <label class="form-label">Echipament disponibil</label>
                                <select name="tracked_asset_id" class="form-select">
                                    <option value="">Alege echipamentul</option>
                                    @foreach($availableAssets as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} · {{ $asset->catalogItem?->name }} · {{ $asset->currentLocation?->name }}</option>@endforeach
                                </select>
                            </div>
                            <div data-material-field hidden>
                                <label class="form-label">Material disponibil</label>
                                <select name="catalog_item_id" class="form-select" data-material-option>
                                    <option value="">Alege materialul</option>
                                    @foreach($issuableMaterials as $stock)
                                        <option value="{{ $stock->catalog_item_id }}" data-location="{{ $stock->location_id }}">
                                            {{ $stock->catalogItem?->name }} · {{ $stock->location?->name }} · disponibil {{ rtrim(rtrim(number_format((float) $stock->available_for_custody, 3, ',', '.'), '0'), ',') }} {{ $stock->catalogItem?->unit }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div data-material-field hidden>
                                <label class="form-label">Cantitate</label>
                                <input name="quantity" type="number" min="0.001" step="0.001" class="form-control" inputmode="decimal">
                            </div>
                            <div>
                                <label class="form-label">Persoana care primește</label>
                                <select name="to_user_id" class="form-select">
                                    <option value="">Alege persoana</option>
                                    @foreach($recipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }} · {{ $recipient->login_code }}</option>@endforeach
                                </select>
                            </div>
                            <div class="custody-form-wide">
                                <label class="form-label">Observații</label>
                                <input name="notes" class="form-control" placeholder="Stare, loc de utilizare sau alte detalii">
                            </div>
                            <button class="btn btn-primary custody-form-wide">Trimite spre confirmare</button>
                        </form>
                    </div>
                </div>
            </div>
            @endif

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#custodyHandoff">
                        <span class="custody-operation-icon"><i class="fa-solid fa-people-arrows"></i></span>
                        <span><strong>Predă unei persoane</strong><small>Ambele persoane confirmă</small></span>
                    </button>
                </h3>
                <div id="custodyHandoff" class="accordion-collapse collapse" data-bs-parent="#custodyOperationAccordion">
                    <div class="accordion-body">
                        <form method="post" action="{{ route('custody-transfers.store') }}" class="custody-operation-form" data-custody-form>
                            @csrf
                            <input type="hidden" name="operation_type" value="handoff">
                            <div>
                                <label class="form-label">Tip</label>
                                <select name="item_type" class="form-select" data-item-type>
                                    <option value="equipment">Echipament individual</option>
                                    <option value="material">Material pe cantitate</option>
                                </select>
                            </div>
                            <div data-equipment-field>
                                <label class="form-label">Echipament</label>
                                <select name="tracked_asset_id" class="form-select">
                                    <option value="">Alege echipamentul</option>
                                    @foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} · {{ $asset->catalogItem?->name }} @if($asset->currentCustodian)· {{ $personLabel($asset->currentCustodian) }}@endif</option>@endforeach
                                </select>
                            </div>
                            <div data-material-field hidden>
                                <label class="form-label">Material din custodie</label>
                                <select name="material_custody_id" class="form-select">
                                    <option value="">Alege materialul</option>
                                    @foreach($materialCustodies as $holding)<option value="{{ $holding->id }}">{{ $holding->catalogItem?->name }} · {{ $holding->location?->name }} · {{ rtrim(rtrim(number_format((float) $holding->quantity, 3, ',', '.'), '0'), ',') }} {{ $holding->unit }} @if((int) $holding->user_id !== (int) auth()->id())· {{ $holding->user?->name }}@endif</option>@endforeach
                                </select>
                            </div>
                            <div data-material-field hidden>
                                <label class="form-label">Cantitate</label>
                                <input name="quantity" type="number" min="0.001" step="0.001" class="form-control" inputmode="decimal">
                            </div>
                            <div>
                                <label class="form-label">Persoana care primește</label>
                                @if($showRecipientCodes)
                                    <input name="to_user_code" class="form-control text-uppercase" placeholder="Cod utilizator">
                                    <div class="form-text">Introdu codul colegului; lista celorlalți șoferi nu este afișată.</div>
                                @else
                                    <select name="to_user_id" class="form-select"><option value="">Alege persoana</option>@foreach($recipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }} · {{ $recipient->login_code }}</option>@endforeach</select>
                                @endif
                            </div>
                            <div class="custody-form-wide">
                                <label class="form-label">Observații</label>
                                <input name="notes" class="form-control" placeholder="Stare, loc de utilizare sau alte detalii">
                            </div>
                            <button class="btn btn-primary custody-form-wide">Trimite spre confirmare</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#custodyReturn">
                        <span class="custody-operation-icon"><i class="fa-solid fa-rotate-left"></i></span>
                        <span><strong>Returnează la locație</strong><small>Responsabilul locației confirmă primirea</small></span>
                    </button>
                </h3>
                <div id="custodyReturn" class="accordion-collapse collapse" data-bs-parent="#custodyOperationAccordion">
                    <div class="accordion-body">
                        <form method="post" action="{{ route('custody-transfers.store') }}" class="custody-operation-form" data-custody-form>
                            @csrf
                            <input type="hidden" name="operation_type" value="return">
                            <div>
                                <label class="form-label">Tip</label>
                                <select name="item_type" class="form-select" data-item-type>
                                    <option value="equipment">Echipament individual</option>
                                    <option value="material">Material pe cantitate</option>
                                </select>
                            </div>
                            <div data-equipment-field>
                                <label class="form-label">Echipament</label>
                                <select name="tracked_asset_id" class="form-select"><option value="">Alege echipamentul</option>@foreach($assets as $asset)<option value="{{ $asset->id }}">{{ $asset->asset_code }} · {{ $asset->catalogItem?->name }} @if($asset->currentCustodian)· {{ $personLabel($asset->currentCustodian) }}@endif</option>@endforeach</select>
                            </div>
                            <div data-equipment-field>
                                <label class="form-label">Locația de retur</label>
                                <select name="location_id" class="form-select"><option value="">Alege locația</option>@foreach($returnLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select>
                            </div>
                            <div data-equipment-field>
                                <label class="form-label">Stare declarată</label>
                                <select name="return_condition" class="form-select">@foreach($conditionLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                            </div>
                            <div data-material-field hidden>
                                <label class="form-label">Material din custodie</label>
                                <select name="material_custody_id" class="form-select"><option value="">Alege materialul</option>@foreach($materialCustodies as $holding)<option value="{{ $holding->id }}">{{ $holding->catalogItem?->name }} · {{ $holding->location?->name }} · {{ rtrim(rtrim(number_format((float) $holding->quantity, 3, ',', '.'), '0'), ',') }} {{ $holding->unit }} @if((int) $holding->user_id !== (int) auth()->id())· {{ $holding->user?->name }}@endif</option>@endforeach</select>
                            </div>
                            <div data-material-field hidden>
                                <label class="form-label">Cantitate</label>
                                <input name="quantity" type="number" min="0.001" step="0.001" class="form-control" inputmode="decimal">
                            </div>
                            <div class="custody-form-wide">
                                <label class="form-label">Observații</label>
                                <input name="notes" class="form-control" placeholder="Ce trebuie verificat la primire?">
                            </div>
                            <button class="btn btn-primary custody-form-wide">Solicită returul</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    <section class="custody-section">
        <div class="custody-section-heading custody-history-heading">
            <div>
                <span class="custody-eyebrow">Trasabilitate</span>
                <h2>Istoric operațiuni</h2>
            </div>
            <form class="custody-filters">
                <input name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Cod sau articol">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Toate stările</option>
                    @foreach(['pending' => 'În așteptare', 'accepted' => 'Acceptat', 'rejected' => 'Refuzat', 'expired' => 'Expirat'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary">Filtrează</button>
            </form>
        </div>
        <div class="custody-history-list">
            @forelse($custodyTransfers as $transfer)
                <article class="custody-history-card">
                    <div class="custody-history-main">
                        <div class="custody-card-topline">
                            <span class="custody-type">{{ $operationLabels[$transfer->operation_type] ?? 'Predare între persoane' }}</span>
                            <x-status :status="$transfer->status" />
                        </div>
                        <strong>{{ $transfer->itemLabel() }}</strong>
                        <span>{{ $transfer->qr_token }} @if($transfer->isMaterial())· {{ rtrim(rtrim(number_format((float) $transfer->quantity, 3, ',', '.'), '0'), ',') }} {{ $transfer->unit }}@endif</span>
                    </div>
                    <div class="custody-history-route">
                        <span>{{ $transfer->fromUser ? $personLabel($transfer->fromUser) : $transfer->location?->name }}</span>
                        <i class="fa-solid fa-arrow-right"></i>
                        <span>{{ $transfer->operation_type === 'return' ? $transfer->location?->name : $personLabel($transfer->toUser) }}</span>
                    </div>
                    <div class="custody-history-meta">
                        <span>{{ $approvalText($transfer) }}</span>
                        <time>{{ ($transfer->accepted_at ?? $transfer->created_at)?->format('d.m.Y H:i') }}</time>
                        @if($transfer->response_notes)<span class="text-danger">{{ $transfer->response_notes }}</span>@endif
                    </div>
                </article>
            @empty
                <div class="custody-empty custody-empty-compact"><strong>Nu există operațiuni pentru filtrele alese.</strong></div>
            @endforelse
        </div>
        @if($custodyTransfers->hasPages())<div class="resource-pagination mt-3">{{ $custodyTransfers->links() }}</div>@endif
    </section>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-custody-form]').forEach((form) => {
    const type = form.querySelector('[data-item-type]');
    const sync = () => {
        const material = type.value === 'material';
        form.querySelectorAll('[data-equipment-field]').forEach((field) => field.hidden = material);
        form.querySelectorAll('[data-material-field]').forEach((field) => field.hidden = !material);
        form.querySelectorAll('[data-equipment-field] select, [data-equipment-field] input').forEach((input) => input.disabled = material);
        form.querySelectorAll('[data-material-field] select, [data-material-field] input').forEach((input) => input.disabled = !material);
    };
    type.addEventListener('change', sync);
    sync();

    const materialOption = form.querySelector('[data-material-option]');
    const materialLocation = form.querySelector('[data-material-location]');
    if (materialOption && materialLocation) {
        const syncLocation = () => materialLocation.value = materialOption.selectedOptions[0]?.dataset.location || '';
        materialOption.addEventListener('change', syncLocation);
        syncLocation();
    }
});
</script>
@endpush
