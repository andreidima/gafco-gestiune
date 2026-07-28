@extends('layouts.app')

@section('title', 'Noutăți')

@section('content')
<div class="help-center-shell mx-3">
    <div class="help-hero help-hero-news">
        <div>
            <span class="dashboard-pill"><i class="fa-solid fa-bullhorn me-2"></i>Noutăți</span>
            <h1>Ce s-a schimbat în aplicație</h1>
            <p>Schimbările importante, explicate pe scurt pentru utilizatorii aplicației.</p>
        </div>
        <a href="{{ route('help.index') }}" class="btn btn-light help-news-button">
            <i class="fa-solid fa-circle-question me-2"></i>Deschide ajutorul
        </a>
    </div>

    <div class="release-timeline">
        @forelse($releaseNotes as $note)
            <article class="release-card">
                <div class="release-date">
                    <span>{{ $note->released_at->format('d') }}</span>
                    <strong>{{ mb_strtoupper($note->released_at->locale('ro')->translatedFormat('M')) }}</strong>
                    <small>{{ $note->released_at->format('Y') }}</small>
                </div>
                <div class="release-card-content">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        @if($note->version)<span class="badge text-bg-primary">Versiunea {{ $note->version }}</span>@endif
                        @if($note->released_at->gte(today()->subDays(30)))<span class="badge text-bg-success">Nou</span>@endif
                        @if($note->requires_action)<span class="badge text-bg-warning">Necesită acțiune</span>@endif
                    </div>
                    <h2>{{ $note->title }}</h2>
                    <p>{{ $note->summary }}</p>
                    @if($note->affected_modules)
                        <div class="release-modules">
                            @foreach($note->affected_modules as $module)
                                <span>{{ $module }}</span>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('release-notes.show', $note) }}" class="btn btn-outline-primary btn-sm">
                        Citește detaliile <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </article>
        @empty
            <div class="resource-empty-state">Nu există încă noutăți publicate.</div>
        @endforelse
    </div>

    {{ $releaseNotes->links() }}
</div>
@endsection
