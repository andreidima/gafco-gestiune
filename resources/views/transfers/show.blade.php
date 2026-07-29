@extends('layouts.app')

@section('title', $transfer->number)

@section('content')
@php
    $isDriverViewer = auth()->user()->usesDriverWorkspace();
    $receiptStatusLabels = ['pending' => 'In asteptare', 'received' => 'Primit', 'missing' => 'Lipsa', 'damaged' => 'Deteriorat'];
    $visibleDriverName = static function ($driver) use ($isDriverViewer): string {
        if (! $driver) {
            return 'Nealocat';
        }

        if (! $isDriverViewer) {
            return $driver->name;
        }

        return (int) $driver->id === (int) auth()->id() ? 'Tu' : 'Realocare in curs';
    };
    $approvals = $transfer->approvals->where('revision', $transfer->revision);
    $projectOverruns = $projectProgress->where('has_overrun', true);
    $taskAssignment = $transfer->task?->currentAssignment;
    if ($isDriverViewer
        && $taskAssignment?->driver_id !== auth()->id()
        && $taskAssignment?->status === 'pending'
        && $taskAssignment?->replacedAssignment?->driver_id === auth()->id()
        && in_array($taskAssignment->replacedAssignment->status, ['accepted', 'reassignment_requested'], true)) {
        $taskAssignment = $taskAssignment->replacedAssignment;
    }
@endphp
<div class="container-fluid px-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div><div class="small text-muted">{{ $transfer->purpose === 'return' ? 'Retur' : 'Transfer' }} · revizia {{ $transfer->revision }}</div><h2 class="mb-1">{{ $transfer->number }}</h2><x-status :status="$transfer->status" /> @if($transfer->archived_at)<span class="badge text-bg-secondary">Arhivat</span>@endif</div>
        <div class="d-flex flex-wrap gap-2"><a href="{{ route('transfers.index') }}" class="btn btn-outline-secondary">Inapoi</a>@can('update',$transfer)<a href="{{ route('transfers.edit',$transfer) }}" class="btn btn-outline-primary">Modifica</a>@endcan @can('create', \App\Models\Transfer::class)@if($transfer->status === 'received' && $transfer->purpose === 'transfer')<a href="{{ route('transfers.create',['return_of'=>$transfer->id]) }}" class="btn btn-primary"><i class="fa-solid fa-rotate-left me-1"></i>Initiaza retur</a>@endif @endcan</div>
    </div>

    @if($transfer->task?->isOverdue())<div class="alert alert-danger">Deadline-ul original a fost depasit.</div>@endif
    @if(! $isDriverViewer && $transfer->project && $projectOverruns->isNotEmpty())<div class="alert alert-danger"><strong>Planul proiectului {{ $transfer->project->code }} este depășit.</strong> Situația completă este disponibilă în pagina proiectului.</div>@endif
    @if($approvals->where('status','!=','approved')->isNotEmpty() && in_array($transfer->status,['in_transit','received'],true))<div class="alert alert-warning">Executia a inceput cu aprobari nefinalizate. Situatia ramane vizibila in istoric.</div>@endif

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3"><div class="card-header bg-white"><strong>Traseu si continut</strong></div><div class="card-body">
                <div class="row g-3 mb-3"><div class="col-md-4"><span class="text-muted">Din</span><div class="fw-bold">{{ $transfer->sourceLocation?->name }}</div></div><div class="col-md-4"><span class="text-muted">Catre</span><div class="fw-bold">{{ $transfer->destinationLocation?->name }}</div></div><div class="col-md-4"><span class="text-muted">Aviz</span><div class="fw-bold">{{ $transfer->document_number ?: '-' }}</div></div></div>
                <div class="table-responsive d-none d-md-block"><table class="table align-middle"><thead><tr><th>Articol</th><th>Echipament</th><th>Cantitate</th><th>Primire</th></tr></thead><tbody>@foreach($transfer->lines as $line)<tr><td>{{ $line->catalogItem?->name }}</td><td>{{ $line->trackedAsset?->asset_code ?? '-' }}</td><td>{{ number_format((float)$line->quantity,3) }} {{ $line->unit }}</td><td>{{ $receiptStatusLabels[$line->received_status] ?? $line->received_status }}</td></tr>@endforeach</tbody></table></div>
                <div class="d-md-none d-grid gap-2 mb-3">
                    @foreach($transfer->lines as $line)
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                                <div>
                                    <div class="small text-uppercase text-muted fw-semibold">Articol</div>
                                    <div class="fw-semibold">{{ $line->catalogItem?->name }}</div>
                                </div>
                                <span class="badge text-bg-light border">{{ $receiptStatusLabels[$line->received_status] ?? $line->received_status }}</span>
                            </div>
                            <div class="row g-2 small">
                                <div class="col-6"><span class="text-muted d-block">Echipament</span><span class="fw-medium">{{ $line->trackedAsset?->asset_code ?? '-' }}</span></div>
                                <div class="col-6"><span class="text-muted d-block">Cantitate</span><span class="fw-medium">{{ number_format((float)$line->quantity,3) }} {{ $line->unit }}</span></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($transfer->notes)<div><span class="text-muted">Observatii</span><div>{{ $transfer->notes }}</div></div>@endif
            </div></div>

            <div class="card mb-3"><div class="card-header bg-white"><strong>Aprobari curente</strong></div><div class="card-body approval-checklist">
                @foreach($approvals as $approval)
                    @php
                        $eligible = auth()->user()->can('approve', $transfer)
                            && (auth()->user()->isOperationsAdmin()
                                || ($approval->scope === 'driver' && $approval->expected_user_id === auth()->id())
                                || ($approval->location && $approval->location->activeManagers->contains(auth()->user())));
                    @endphp
                    @php
                        $approvalExpected = $approval->location?->name ?? $approval->expectedUser?->name ?? 'Asteapta alocarea';
                        $approvalDecider = $approval->decidedBy?->name;
                        if ($isDriverViewer && $approval->scope === 'driver') {
                            $approvalExpected = $approval->expected_user_id === auth()->id() ? 'Tu' : ($approval->expected_user_id ? 'Sofer alocat' : 'Asteapta alocarea');
                            $approvalDecider = $approval->decided_by_user_id === auth()->id() ? 'Tu' : ($approval->decided_by_user_id ? 'Decizie inregistrata' : null);
                        }
                    @endphp
                    <div class="approval-item"><div class="d-flex justify-content-between gap-2"><div><strong>{{ match($approval->scope){'source_manager'=>'Manager locatie sursa','destination_manager'=>'Manager locatie destinatie','driver'=>'Sofer',default=>$approval->scope} }}</strong><div class="small text-muted">{{ $approvalExpected }}</div>@if($approvalDecider)<div class="small">{{ $approvalDecider }} · {{ $approval->decided_at?->format('d.m.Y H:i') }}</div>@endif @if($approval->decision_note)<div class="small text-muted">{{ $approval->decision_note }}</div>@endif</div><x-status :status="$approval->status" /></div>
                        @if($approval->status === 'pending' && $eligible && $approval->scope !== 'driver')<form method="post" action="{{ route('transfer-approvals.update',$approval) }}" class="d-flex gap-2 mt-2">@csrf @method('put')<input name="decision_note" class="form-control form-control-sm" placeholder="Observatie obligatorie la refuz"><button name="decision" value="approved" class="btn btn-sm btn-outline-success">Aproba</button><button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Refuza</button></form>@elseif($approval->scope === 'driver' && $approval->status === 'pending' && $transfer->task)<a href="{{ route('tasks.show',$transfer->task) }}" class="btn btn-sm btn-outline-primary mt-2">Raspunsul soferului se da in sarcina</a>@endif
                    </div>
                @endforeach
            </div></div>

            <div class="card"><div class="card-header bg-white"><strong>Revizii</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Revizie</th><th>Modificat de</th><th>Motiv</th><th>Data</th></tr></thead><tbody>@foreach($transfer->revisions->sortByDesc('revision') as $revision)<tr><td>{{ $revision->revision }}</td><td>{{ $revision->changedBy?->name ?? '-' }}</td><td>{{ $revision->change_summary }}</td><td>{{ $revision->created_at->format('d.m.Y H:i') }}</td></tr>@endforeach</tbody></table></div></div>
        </div>

        <div class="col-xl-4">
            @if(! $isDriverViewer && $transfer->project)
                <div class="card mb-3">
                    <div class="card-header bg-white"><strong>Proiect și plan de materiale</strong></div>
                    <div class="card-body">
                        <div class="resource-code">{{ $transfer->project->code }}</div>
                        <div class="fw-bold mb-1">{{ $transfer->project->name }}</div>
                        <div class="small text-muted mb-3">{{ $transfer->project->location?->code }} — {{ $transfer->project->location?->name }}</div>
                        @if($projectOverruns->isNotEmpty())
                            <div class="alert alert-danger py-2 small">{{ $projectOverruns->count() }} {{ $projectOverruns->count() === 1 ? 'material depășește' : 'materiale depășesc' }} planul.</div>
                        @else
                            <div class="alert alert-success py-2 small">Cantitățile cumulate sunt în limitele planului.</div>
                        @endif
                        <a href="{{ route('projects.show', $transfer->project) }}" class="btn btn-outline-primary w-100">Deschide proiectul</a>
                    </div>
                </div>
            @endif
            <div class="card mb-3"><div class="card-header bg-white"><strong>Sarcina sofer</strong></div><div class="card-body"><div>Sofer: <strong>{{ $visibleDriverName($taskAssignment?->driver) }}</strong></div><div>Deadline: <strong>{{ $transfer->task?->manager_deadline?->format('d.m.Y H:i') ?? '-' }}</strong></div><div>Estimare: <strong>{{ $taskAssignment?->driver_estimate_at?->format('d.m.Y H:i') ?? '-' }}</strong></div>@if($transfer->task)<a href="{{ route('tasks.show',$transfer->task) }}" class="btn btn-outline-primary w-100 mt-3">Deschide sarcina</a>@endif</div></div>
            @if($transfer->parentTransfer)<div class="card mb-3"><div class="card-header bg-white"><strong>Transfer initial</strong></div><div class="card-body"><a href="{{ route('transfers.show',$transfer->parentTransfer) }}">{{ $transfer->parentTransfer->number }}</a></div></div>@endif
            @if($transfer->returns->isNotEmpty())<div class="card mb-3"><div class="card-header bg-white"><strong>Retururi initiate</strong></div><div class="card-body vstack gap-2">@foreach($transfer->returns as $return)<a href="{{ route('transfers.show',$return) }}">{{ $return->number }}</a>@endforeach</div></div>@endif
            @if($transfer->status === 'in_transit')@can('receive', $transfer)<div class="card mb-3"><div class="card-header bg-white"><strong>Confirmare primire</strong></div><div class="card-body"><form method="post" action="{{ route('transfers.receive',$transfer) }}" class="vstack gap-2">@csrf<textarea name="discrepancy_notes" class="form-control" placeholder="Diferente / observatii optionale"></textarea><button class="btn btn-success">Confirma primirea</button></form></div></div>@endcan @endif
            @can('cancel',$transfer)<div class="card mb-3"><div class="card-header bg-white"><strong>Anulare</strong></div><div class="card-body"><form method="post" action="{{ route('transfers.cancel',$transfer) }}" class="vstack gap-2">@csrf<textarea name="notes" class="form-control" placeholder="Motiv obligatoriu" required></textarea><button class="btn btn-outline-danger">Anuleaza fara stergere</button></form></div></div>@endcan
            @can('archive', $transfer)
                @if(! $transfer->archived_at)
                    <form method="post" action="{{ route('transfers.archive', $transfer) }}">@csrf<button class="btn btn-outline-secondary w-100">Arhiveaza</button></form>
                @endif
            @endcan
        </div>
    </div>
</div>
@endsection
