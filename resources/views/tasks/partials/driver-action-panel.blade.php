@if($isDriverViewer && $assignment?->driver_id === auth()->id() && ! $task->isOperationallyLocked())
    <section id="actiune-sofer" class="card driver-action-panel mb-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="driver-action-kicker">Acțiunea următoare</span>
                <strong class="d-block">
                    @if($assignment->status === 'pending')
                        Răspunde la alocare
                    @elseif($task->status === 'accepted')
                        Estimează și pornește sarcina
                    @elseif($task->status === 'in_progress')
                        Continuă sarcina
                    @else
                        Actualizează estimarea
                    @endif
                </strong>
            </div>
            <x-status :status="$task->status" />
        </div>

        <div class="card-body">
            @if($assignment->status === 'pending')
                <form method="post" action="{{ route('task-assignments.respond', $assignment) }}" class="vstack gap-3">
                    @csrf
                    <textarea name="response_notes" class="form-control" rows="2" placeholder="Observație obligatorie doar la refuz">{{ old('response_notes') }}</textarea>
                    <div class="d-flex gap-2">
                        <button name="decision" value="accepted" class="btn btn-success flex-fill">Acceptă</button>
                        <button name="decision" value="rejected" class="btn btn-outline-danger flex-fill">Refuză</button>
                    </div>
                </form>
            @elseif(in_array($assignment->status, ['accepted', 'reassignment_requested'], true))
                <div class="driver-action-grid">
                    <div class="driver-action-step">
                        <div class="driver-action-step-heading">
                            <span class="driver-action-step-number">1</span>
                            <div><strong>Estimare de finalizare</strong><small>Ora este completată automat cu o oră în avans.</small></div>
                        </div>
                        <form method="post" action="{{ route('task-assignments.estimate', $assignment) }}" class="vstack gap-2">
                            @csrf
                            <label for="driver-estimate-at" class="form-label mb-0">Ora estimată</label>
                            <input id="driver-estimate-at" name="driver_estimate_at" type="datetime-local" value="{{ $estimateInputValue }}" class="form-control" required>
                            <label for="driver-estimate-note" class="form-label mb-0">Observație <span class="text-muted fw-normal">(opțional)</span></label>
                            <textarea id="driver-estimate-note" name="driver_estimate_note" class="form-control" rows="2" placeholder="De exemplu: trafic aglomerat">{{ $estimateNoteValue }}</textarea>
                            @if($canCorrectLatestEstimate)
                                <small class="text-muted">Poți corecta această estimare până la {{ $latestEstimate->correctionDeadline()->format('H:i') }}.</small>
                            @elseif($latestEstimate)
                                <small class="text-muted">Ultima estimare rămâne în istoric. Salvarea va adăuga o estimare nouă.</small>
                            @endif
                            <button class="btn btn-outline-primary">
                                {{ $canCorrectLatestEstimate ? 'Corectează estimarea' : 'Salvează estimarea' }}
                            </button>
                        </form>
                    </div>

                    @if($assignment->status === 'accepted' && $task->status === 'accepted')
                        <div class="driver-action-step driver-action-step-primary">
                            <div class="driver-action-step-heading">
                                <span class="driver-action-step-number">2</span>
                                <div><strong>Pornește sarcina</strong><small>Sarcina rămâne neîncepută până apeși butonul de mai jos.</small></div>
                            </div>
                            @if($latestEstimate)
                                <div class="driver-start-reminder"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Estimarea este salvată. Sarcina nu este încă pornită.</span></div>
                            @else
                                <div class="driver-start-reminder is-pending"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Salvează estimarea, apoi pornește sarcina.</span></div>
                            @endif
                            <form method="post" action="{{ route('tasks.transition', $task) }}" class="vstack gap-2">
                                @csrf
                                <input type="hidden" name="status" value="in_progress">
                                <textarea name="notes" class="form-control" rows="2" placeholder="Observație la pornire (opțional)"></textarea>
                                <button class="btn btn-primary btn-lg"><i class="fa-solid fa-play me-2"></i>Pornește sarcina</button>
                            </form>
                        </div>
                    @elseif($assignment->status === 'accepted' && $task->status === 'in_progress')
                        <div class="driver-action-step driver-action-step-primary">
                            <div class="driver-action-step-heading">
                                <span class="driver-action-step-number"><i class="fa-solid fa-truck-fast"></i></span>
                                <div><strong>Sarcină în lucru</strong><small>Actualizează estimarea când este nevoie sau finalizează sarcina.</small></div>
                            </div>
                            <form method="post" action="{{ route('tasks.transition', $task) }}" class="vstack gap-2">
                                @csrf
                                <input type="hidden" name="status" value="completed">
                                <textarea name="notes" class="form-control" rows="2" placeholder="Observație la finalizare (opțional)"></textarea>
                                <button class="btn btn-success btn-lg"><i class="fa-solid fa-check me-2"></i>Finalizează sarcina</button>
                            </form>
                        </div>
                    @elseif($assignment->status === 'reassignment_requested')
                        <div class="driver-action-step driver-action-step-muted">
                            <div class="driver-action-step-heading">
                                <span class="driver-action-step-number"><i class="fa-solid fa-user-clock"></i></span>
                                <div><strong>Realocare solicitată</strong><small>Managerul a primit solicitarea. Poți comunica în continuare estimări actualizate.</small></div>
                            </div>
                        </div>
                    @endif
                </div>

                @if($assignment->status === 'accepted')
                    <details class="driver-secondary-action mt-3">
                        <summary>Solicită realocarea sarcinii</summary>
                        <form method="post" action="{{ route('task-assignments.request-reassignment', $assignment) }}" class="vstack gap-2 mt-2">
                            @csrf
                            <textarea name="notes" class="form-control" rows="2" placeholder="Motivul realocării" required></textarea>
                            <button class="btn btn-outline-warning">Trimite solicitarea de realocare</button>
                        </form>
                    </details>
                @endif
            @endif
        </div>
    </section>
@endif
