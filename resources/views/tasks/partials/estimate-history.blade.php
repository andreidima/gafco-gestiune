@if($assignment?->estimates->isNotEmpty())
    <details class="card task-estimate-history mb-3" @unless($isDriverViewer) open @endunless>
        <summary class="card-header bg-white">
            <span><strong>Istoric estimări</strong><span class="badge text-bg-light border ms-2">{{ $assignment->estimates->count() }}</span></span>
            <span class="task-estimate-latest">Ultima: {{ $latestEstimate->estimated_at->format('d.m.Y H:i') }}</span>
        </summary>
        <div class="card-body">
            <div class="task-estimate-list">
                @foreach($assignment->estimates->sortByDesc('id') as $estimate)
                    <div class="task-estimate-item">
                        <div>
                            <strong>{{ $estimate->estimated_at->format('d.m.Y H:i') }}</strong>
                            @if($estimate->id === $latestEstimate->id)<span class="badge text-bg-primary ms-1">Actuală</span>@endif
                            @if($estimate->note)<div class="small mt-1">{{ $estimate->note }}</div>@endif
                        </div>
                        <div class="small text-muted text-end">
                            Comunicată {{ $estimate->created_at->format('d.m.Y H:i') }}
                            @unless($isDriverViewer)<span class="d-block">{{ $estimate->driver?->name ?? 'Șofer' }}</span>@endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </details>
@endif
