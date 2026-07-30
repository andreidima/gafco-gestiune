@extends('layouts.app')

@section('title', $task->number)

@section('content')
@php
    $assignment = $task->currentAssignment;
    $isDriverViewer = auth()->user()->usesDriverWorkspace();
    $latestEstimate = $assignment?->estimates?->sortByDesc('id')->first();
    $canCorrectLatestEstimate = $latestEstimate?->canBeCorrected() ?? false;
    $estimateInputValue = old(
        'driver_estimate_at',
        $canCorrectLatestEstimate
            ? $latestEstimate->estimated_at->format('Y-m-d\TH:i')
            : now()->addHour()->startOfMinute()->format('Y-m-d\TH:i'),
    );
    $estimateNoteValue = old('driver_estimate_note', $canCorrectLatestEstimate ? $latestEstimate->note : '');
    $showEstimateForm = ! $latestEstimate || $errors->has('driver_estimate_at') || $errors->has('driver_estimate_note');
    $priorityLabels = ['low' => 'Scazuta', 'normal' => 'Normala', 'high' => 'Ridicata', 'urgent' => 'Urgenta'];
    $commentTypeLabels = ['observation' => 'Observatie', 'acceptance' => 'Acceptare', 'rejection' => 'Refuz', 'estimate' => 'Estimare', 'reassignment' => 'Realocare', 'status' => 'Schimbare stare'];
    $receiptStatusLabels = ['pending' => 'În așteptare', 'received' => 'Primit', 'missing' => 'Lipsă', 'damaged' => 'Deteriorat'];
    $visibleDriverName = static function ($driver) use ($isDriverViewer): string {
        if (! $driver) {
            return 'Nealocat';
        }

        if (! $isDriverViewer) {
            return $driver->name;
        }

        return (int) $driver->id === (int) auth()->id() ? 'Tu' : 'Realocare in curs';
    };
@endphp
<div class="container-fluid px-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div><div class="small text-muted">{{ $task->number }}</div><h2 class="mb-1">{{ $task->title }}</h2><x-status :status="$task->status" /></div>
        <div class="d-flex gap-2">@can('edit', $task)<a href="{{ route('tasks.edit', $task) }}" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i>Modifica</a>@endcan<a href="{{ route('tasks.index') }}" class="btn btn-outline-secondary">Inapoi</a></div>
    </div>

    @if($task->isOverdue())<div class="alert alert-danger"><strong>Sarcina este intarziata</strong> fata de deadline-ul original al managerului.</div>@endif
    @if($task->isOperationallyLocked())
        <div class="alert alert-secondary d-flex gap-2 align-items-start">
            <i class="fa-solid fa-lock mt-1" aria-hidden="true"></i>
            <div><strong>Sarcină închisă, disponibilă doar pentru consultare.</strong> Detaliile, alocarea, estimările și observațiile nu mai pot fi modificate. Istoricul rămâne vizibil.</div>
        </div>
    @endif

    @include('tasks.partials.driver-action-panel')

    <div class="row g-3">
        <div class="{{ $isDriverViewer ? 'col-12' : 'col-xl-8' }}">
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Detalii operationale</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><span class="text-muted">Traseu</span><div class="fw-semibold">{{ $task->sourceLocation?->name ?? 'Nespecificat' }} → {{ $task->destinationLocation?->name ?? 'Nespecificat' }}</div></div>
                    <div class="col-md-3"><span class="text-muted">{{ $isDriverViewer ? 'Termen' : 'Deadline manager' }}</span><div class="fw-semibold {{ $task->isOverdue() ? 'deadline-overdue' : '' }}">{{ $task->manager_deadline?->format('d.m.Y H:i') ?? '-' }}</div></div>
                    <div class="col-md-3"><span class="text-muted">{{ $isDriverViewer ? 'Estimarea mea' : 'Estimare sofer' }}</span><div class="fw-semibold">{{ $assignment?->driver_estimate_at?->format('d.m.Y H:i') ?? '-' }}</div></div>
                    @unless($isDriverViewer)<div class="col-md-6"><span class="text-muted">Sofer</span><div class="fw-semibold">{{ $visibleDriverName($assignment?->driver) }}</div></div>@endunless
                    <div class="col-md-6"><span class="text-muted">Prioritate</span><div class="fw-semibold">{{ $priorityLabels[$task->priority] ?? $task->priority }}</div></div>
                    @if($task->notes)<div class="col-12"><span class="text-muted">Observatii initiale</span><div>{{ $task->notes }}</div></div>@endif
                </div>
            </div>

            @include('tasks.partials.estimate-history')

            @if($task->transfer)
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <strong>Conținutul transferului {{ $task->transfer->number }}</strong>
                        <a href="{{ route('transfers.show', $task->transfer) }}" class="btn btn-sm btn-outline-primary">Deschide transferul</a>
                    </div>
                    <div class="card-body">
                        <div class="task-transfer-summary mb-3">
                            <span><i class="fa-solid fa-boxes-stacked me-1"></i>{{ $task->transfer->lines->count() }} {{ $task->transfer->lines->count() === 1 ? 'poziție' : 'poziții' }}</span>
                            @if($task->transfer->document_number)<span><i class="fa-solid fa-file-lines me-1"></i>Aviz {{ $task->transfer->document_number }}</span>@endif
                        </div>
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-sm align-middle mb-0">
                                <thead><tr><th>Articol</th><th>Identificare</th><th class="text-end">Cantitate</th><th>Stare la primire</th></tr></thead>
                                <tbody>
                                @forelse($task->transfer->lines as $line)
                                    <tr>
                                        <td><strong>{{ $line->catalogItem?->name ?? 'Articol indisponibil' }}</strong>@if($line->notes)<span class="resource-secondary">{{ $line->notes }}</span>@endif</td>
                                        <td>{{ $line->trackedAsset?->asset_code ?? 'Material cantitativ' }}@if($line->trackedAsset?->serial_number)<span class="resource-secondary">Serie: {{ $line->trackedAsset->serial_number }}</span>@endif</td>
                                        <td class="text-end text-nowrap">{{ number_format((float) $line->quantity, 3, ',', '.') }} {{ $line->unit }}</td>
                                        <td>{{ $receiptStatusLabels[$line->received_status] ?? $line->received_status }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-3">Transferul nu are poziții înregistrate.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="d-grid gap-2 d-md-none">
                            @forelse($task->transfer->lines as $line)
                                <div class="task-transfer-line">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong>{{ $line->catalogItem?->name ?? 'Articol indisponibil' }}</strong>
                                        <span class="text-nowrap">{{ number_format((float) $line->quantity, 3, ',', '.') }} {{ $line->unit }}</span>
                                    </div>
                                    <div class="resource-secondary">{{ $line->trackedAsset?->asset_code ?? 'Material cantitativ' }} · {{ $receiptStatusLabels[$line->received_status] ?? $line->received_status }}</div>
                                    @if($line->notes)<div class="small mt-1">{{ $line->notes }}</div>@endif
                                </div>
                            @empty
                                <div class="text-muted">Transferul nu are poziții înregistrate.</div>
                            @endforelse
                        </div>
                        @if($task->transfer->notes)<div class="small mt-3"><strong>Observații transfer:</strong> {{ $task->transfer->notes }}</div>@endif
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between"><strong>Aprobari transfer {{ $task->transfer->number }}</strong><a href="{{ route('transfers.show', $task->transfer) }}">Deschide transferul</a></div>
                    <div class="card-body approval-checklist">
                        @foreach($task->transfer->approvals->where('revision', $task->transfer->revision) as $approval)
                            @php
                                $approvalPerson = $approval->decidedBy?->name ?? $approval->location?->name ?? $approval->expectedUser?->name;
                                if ($isDriverViewer && $approval->scope === 'driver') {
                                    $approvalPerson = $approval->expected_user_id === auth()->id() || $approval->decided_by_user_id === auth()->id()
                                        ? 'Tu'
                                        : ($approval->expected_user_id ? 'Sofer alocat' : 'Asteapta alocarea');
                                }
                            @endphp
                            <div class="approval-item d-flex justify-content-between gap-2"><div><strong>{{ match($approval->scope) {'source_manager'=>'Manager sursa','destination_manager'=>'Manager destinatie','driver'=>'Sofer',default=>$approval->scope} }}</strong><div class="small text-muted">{{ $approvalPerson }}</div></div><x-status :status="$approval->status" /></div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header bg-white"><strong>Observatii si istoric</strong></div>
                <div class="card-body">
                    @can('comment', $task)
                        <form method="post" action="{{ route('tasks.comments.store', $task) }}" class="d-flex gap-2 mb-3">@csrf<input name="body" class="form-control" placeholder="Adauga o observatie" required><button class="btn btn-outline-primary">Adauga</button></form>
                    @endcan
                    <div class="vstack gap-2">@forelse($task->comments->sortByDesc('created_at') as $comment)@php($commentAuthor = $isDriverViewer && $comment->user?->usesDriverWorkspace() && $comment->user_id !== auth()->id() ? 'Echipa transport' : ($comment->user?->name ?? 'Sistem'))<div class="border rounded-3 p-2"><div>{{ $comment->body }}</div><div class="small text-muted">{{ $commentAuthor }} · {{ $comment->created_at->format('d.m.Y H:i') }} · {{ $commentTypeLabels[$comment->type] ?? $comment->type }}</div></div>@empty<div class="text-muted">Nu exista observatii.</div>@endforelse</div>
                </div>
            </div>
        </div>

        @unless($isDriverViewer)
            <div class="col-xl-4">
                @can('assign', $task)
                    <div class="card mb-3"><div class="card-header bg-white"><strong>Alocare sofer</strong></div><div class="card-body"><form method="post" action="{{ route('tasks.assignments.store', $task) }}" class="vstack gap-2">@csrf<select name="driver_id" class="form-select" data-tom-select required><option value="">Alege sofer</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}">{{ $driver->name }}</option>@endforeach</select><button class="btn btn-primary">{{ $assignment ? 'Propune inlocuitor' : 'Trimite spre acceptare' }}</button></form></div></div>
                    @if($whatsAppRecipients->isNotEmpty())<div class="card mb-3"><div class="card-header bg-white"><strong>WhatsApp</strong></div><div class="card-body"><form method="get" action="{{ route('tasks.whatsapp', $task) }}" class="vstack gap-2"><select name="user_id" class="form-select" data-tom-select required><option value="">Destinatar</option>@foreach($whatsAppRecipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }} · {{ $recipient->phone }}</option>@endforeach</select><button class="btn btn-success"><i class="fa-brands fa-whatsapp me-1"></i> Pregateste mesajul</button></form></div></div>@endif
                @endcan

                @if($assignment?->status === 'accepted' && in_array($task->status, ['accepted','in_progress'], true))
                    <div class="card"><div class="card-header bg-white"><strong>Executie</strong></div><div class="card-body"><form method="post" action="{{ route('tasks.transition', $task) }}" class="vstack gap-2">@csrf<input type="hidden" name="status" value="{{ $task->status === 'accepted' ? 'in_progress' : 'completed' }}"><textarea name="notes" class="form-control" placeholder="Observatie optionala"></textarea><button class="btn btn-{{ $task->status === 'accepted' ? 'primary' : 'success' }}">{{ $task->status === 'accepted' ? 'Porneste sarcina' : 'Finalizeaza sarcina' }}</button></form></div></div>
                @endif
            </div>
        @endunless
    </div>
</div>
@endsection
