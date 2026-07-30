@extends('layouts.app')

@section('title', $releaseNote->title)

@section('content')
<div class="help-center-shell mx-3">
    <div class="release-detail-header">
        <div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge text-bg-primary">{{ $releaseNote->released_at->format('d.m.Y') }}</span>
                @if($releaseNote->version)<span class="badge text-bg-secondary">Versiunea {{ $releaseNote->version }}</span>@endif
                @if($releaseNote->requires_action)<span class="badge text-bg-warning">Necesită acțiune</span>@endif
            </div>
            <h1>{{ $releaseNote->title }}</h1>
            <p>{{ $releaseNote->summary }}</p>
        </div>
        <x-back-link :fallback="route('release-notes.index')" label="Înapoi la noutăți" />
    </div>

    <article class="help-article release-detail">
        @if($releaseNote->affected_modules)
            <div class="release-modules mb-4">
                @foreach($releaseNote->affected_modules as $module)
                    <span>{{ $module }}</span>
                @endforeach
            </div>
        @endif
        <div class="help-markdown">
            {!! $releaseBody !!}
        </div>
    </article>
</div>
@endsection
