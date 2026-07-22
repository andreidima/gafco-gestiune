@extends('layouts.app')

@section('title', $task->number)

@section('content')
@php
    $assignment = $task->currentAssignment;
    $isDriverViewer = auth()->user()->usesDriverWorkspace();
    $priorityLabels = ['low' => 'Scazuta', 'normal' => 'Normala', 'high' => 'Ridicata', 'urgent' => 'Urgenta'];
    $commentTypeLabels = ['observation' => 'Observatie', 'acceptance' => 'Acceptare', 'rejection' => 'Refuz', 'estimate' => 'Estimare', 'reassignment' => 'Realocare', 'status' => 'Schimbare stare'];
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

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header bg-white"><strong>Detalii operationale</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><span class="text-muted">Traseu</span><div class="fw-semibold">{{ $task->sourceLocation?->name ?? 'Nespecificat' }} → {{ $task->destinationLocation?->name ?? 'Nespecificat' }}</div></div>
                    <div class="col-md-3"><span class="text-muted">Deadline manager</span><div class="fw-semibold {{ $task->isOverdue() ? 'deadline-overdue' : '' }}">{{ $task->manager_deadline?->format('d.m.Y H:i') ?? '-' }}</div></div>
                    <div class="col-md-3"><span class="text-muted">Estimare sofer</span><div class="fw-semibold">{{ $assignment?->driver_estimate_at?->format('d.m.Y H:i') ?? '-' }}</div></div>
                    <div class="col-md-6"><span class="text-muted">Sofer</span><div class="fw-semibold">{{ $visibleDriverName($assignment?->driver) }}</div></div>
                    <div class="col-md-6"><span class="text-muted">Prioritate</span><div class="fw-semibold">{{ $priorityLabels[$task->priority] ?? $task->priority }}</div></div>
                    @if($task->notes)<div class="col-12"><span class="text-muted">Observatii initiale</span><div>{{ $task->notes }}</div></div>@endif
                </div>
            </div>

            @if($task->transfer)
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
                    <form method="post" action="{{ route('tasks.comments.store', $task) }}" class="d-flex gap-2 mb-3">@csrf<input name="body" class="form-control" placeholder="Adauga o observatie" required><button class="btn btn-outline-primary">Adauga</button></form>
                    <div class="vstack gap-2">@forelse($task->comments->sortByDesc('created_at') as $comment)@php($commentAuthor = $isDriverViewer && $comment->user?->usesDriverWorkspace() && $comment->user_id !== auth()->id() ? 'Echipa transport' : ($comment->user?->name ?? 'Sistem'))<div class="border rounded-3 p-2"><div>{{ $comment->body }}</div><div class="small text-muted">{{ $commentAuthor }} · {{ $comment->created_at->format('d.m.Y H:i') }} · {{ $commentTypeLabels[$comment->type] ?? $comment->type }}</div></div>@empty<div class="text-muted">Nu exista observatii.</div>@endforelse</div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            @if(auth()->user()->usesDriverWorkspace() && $assignment?->driver_id === auth()->id())
                @if($assignment->status === 'pending')
                    <div class="card mb-3"><div class="card-header bg-white"><strong>Raspunsul tau</strong></div><div class="card-body">
                        <form method="post" action="{{ route('task-assignments.respond', $assignment) }}" class="vstack gap-2">@csrf<textarea name="response_notes" class="form-control" placeholder="Observatie obligatorie la refuz"></textarea><div class="d-flex gap-2"><button name="decision" value="accepted" class="btn btn-success flex-fill">Accepta</button><button name="decision" value="rejected" class="btn btn-outline-danger flex-fill">Refuza</button></div></form>
                    </div></div>
                @elseif(in_array($assignment->status, ['accepted','reassignment_requested'], true))
                    <div class="card mb-3"><div class="card-header bg-white"><strong>Estimarea mea</strong></div><div class="card-body">
                        <form method="post" action="{{ route('task-assignments.estimate', $assignment) }}" class="vstack gap-2">@csrf<input name="driver_estimate_at" type="datetime-local" value="{{ $assignment->driver_estimate_at?->format('Y-m-d\TH:i') }}" class="form-control" required><textarea name="driver_estimate_note" class="form-control" placeholder="Explica estimarea" required>{{ $assignment->driver_estimate_note }}</textarea><button class="btn btn-outline-primary">Salveaza estimarea</button></form>
                    </div></div>
                    @if($assignment->status === 'accepted')<div class="card mb-3"><div class="card-header bg-white"><strong>Realocare</strong></div><div class="card-body"><form method="post" action="{{ route('task-assignments.request-reassignment', $assignment) }}" class="vstack gap-2">@csrf<textarea name="notes" class="form-control" placeholder="Motivul realocarii" required></textarea><button class="btn btn-outline-warning">Solicita realocare</button></form></div></div>@endif
                @endif
            @else
                @can('assign', $task)
                    <div class="card mb-3"><div class="card-header bg-white"><strong>Alocare sofer</strong></div><div class="card-body"><form method="post" action="{{ route('tasks.assignments.store', $task) }}" class="vstack gap-2">@csrf<select name="driver_id" class="form-select" data-tom-select required><option value="">Alege sofer</option>@foreach($drivers as $driver)<option value="{{ $driver->id }}">{{ $driver->name }}</option>@endforeach</select><button class="btn btn-primary">{{ $assignment ? 'Propune inlocuitor' : 'Trimite spre acceptare' }}</button></form></div></div>
                    @if($whatsAppRecipients->isNotEmpty())<div class="card mb-3"><div class="card-header bg-white"><strong>WhatsApp</strong></div><div class="card-body"><form method="get" action="{{ route('tasks.whatsapp', $task) }}" class="vstack gap-2"><select name="user_id" class="form-select" data-tom-select required><option value="">Destinatar</option>@foreach($whatsAppRecipients as $recipient)<option value="{{ $recipient->id }}">{{ $recipient->name }} · {{ $recipient->phone }}</option>@endforeach</select><button class="btn btn-success"><i class="fa-brands fa-whatsapp me-1"></i> Pregateste mesajul</button></form></div></div>@endif
                @endcan
            @endif

            @if($assignment?->status === 'accepted' && in_array($task->status, ['accepted','in_progress'], true))
                <div class="card"><div class="card-header bg-white"><strong>Executie</strong></div><div class="card-body"><form method="post" action="{{ route('tasks.transition', $task) }}" class="vstack gap-2">@csrf<input type="hidden" name="status" value="{{ $task->status === 'accepted' ? 'in_progress' : 'completed' }}"><textarea name="notes" class="form-control" placeholder="Observatie optionala"></textarea><button class="btn btn-{{ $task->status === 'accepted' ? 'primary' : 'success' }}">{{ $task->status === 'accepted' ? 'Porneste sarcina' : 'Finalizeaza sarcina' }}</button></form></div></div>
            @endif
        </div>
    </div>
</div>
@endsection
