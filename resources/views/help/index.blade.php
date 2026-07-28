@extends('layouts.app')

@section('title', 'Centru de ajutor')

@section('content')
<div class="help-center-shell mx-3">
    <div class="help-hero">
        <div>
            <span class="dashboard-pill"><i class="fa-solid fa-circle-question me-2"></i>Ajutor</span>
            <h1>Centru de ajutor</h1>
            <p>Înțelege fluxurile, responsabilitățile și efectul fiecărei operațiuni asupra materialelor și echipamentelor.</p>
        </div>
        <a href="{{ route('release-notes.index') }}" class="btn btn-light help-news-button">
            <i class="fa-solid fa-bullhorn me-2"></i>Vezi noutățile
        </a>
    </div>

    <form method="get" action="{{ route('help.index') }}" class="help-search" role="search">
        <label for="help-search-input" class="visually-hidden">Caută în Centrul de ajutor</label>
        <i class="fa-solid fa-magnifying-glass"></i>
        <input
            id="help-search-input"
            name="q"
            value="{{ $search }}"
            class="form-control"
            placeholder="Caută un flux, un rol, o pagină sau un status"
        >
        <button class="btn btn-primary">Caută</button>
        @if($search !== '')
            <a href="{{ route('help.index') }}" class="btn btn-outline-secondary">Resetează</a>
        @endif
    </form>

    @if($search !== '')
        <section class="help-search-results" aria-labelledby="help-search-results-title">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 id="help-search-results-title">Rezultate pentru „{{ $search }}”</h2>
                    <p>{{ $searchResults->count() }} {{ $searchResults->count() === 1 ? 'articol găsit' : 'articole găsite' }}</p>
                </div>
            </div>
            <div class="row g-3">
                @forelse($searchResults as $result)
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ route('help.show', $result) }}" class="help-result-card">
                            <span>{{ $sectionLabels[$result->section] ?? 'Ajutor' }}</span>
                            <strong>{{ $result->title }}</strong>
                            <p>{{ $result->summary }}</p>
                            <small>Deschide articolul <i class="fa-solid fa-arrow-right ms-1"></i></small>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="resource-empty-state">Nu am găsit articole. Încearcă un termen mai scurt sau caută după numele paginii.</div>
                    </div>
                @endforelse
            </div>
        </section>
    @else
        <div class="row g-3 help-layout">
            <aside class="col-lg-3" aria-label="Articole de ajutor">
                <nav class="help-navigation">
                    @foreach($articles->groupBy('section') as $section => $sectionArticles)
                        <div class="help-navigation-group">
                            <div class="help-navigation-title">{{ $sectionLabels[$section] ?? 'Ajutor' }}</div>
                            @foreach($sectionArticles as $article)
                                <a
                                    href="{{ route('help.show', $article) }}"
                                    class="{{ $selectedArticle?->is($article) ? 'active' : '' }}"
                                    @if($selectedArticle?->is($article)) aria-current="page" @endif
                                >
                                    {{ $article->title }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </nav>
            </aside>

            <div class="col-lg-9">
                @if($selectedArticle)
                    <article class="help-article">
                        <div class="help-article-heading">
                            <div>
                                <span>{{ $sectionLabels[$selectedArticle->section] ?? 'Ajutor' }}</span>
                                <h2>{{ $selectedArticle->title }}</h2>
                                <p>{{ $selectedArticle->summary }}</p>
                            </div>
                            <div class="help-article-meta">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                                Actualizat {{ $selectedArticle->updated_at->format('d.m.Y') }}
                            </div>
                        </div>
                        <div class="help-markdown">
                            {!! $articleBody !!}
                        </div>
                    </article>
                @else
                    <div class="resource-empty-state">Centrul de ajutor nu are încă articole publicate.</div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
