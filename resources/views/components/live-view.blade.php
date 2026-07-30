@props([
    'viewKey',
    'interval' => 300,
    'compact' => false,
])

<div
    class="live-view {{ $compact ? 'live-view-compact' : '' }}"
    data-live-view
    data-live-view-key="{{ $viewKey }}"
    data-live-view-interval="{{ $interval }}"
>
    <span class="live-view-indicator" aria-hidden="true"></span>
    <span class="live-view-copy">
        <strong>Live</strong>
        <span data-live-view-status>Actualizare în 5:00</span>
    </span>
    <button
        class="btn btn-sm btn-outline-secondary live-view-toggle"
        type="button"
        data-live-view-toggle
        aria-pressed="true"
        title="Oprește actualizarea automată"
    >
        <i class="fa-solid fa-pause" aria-hidden="true"></i>
        <span class="visually-hidden">Oprește actualizarea automată</span>
    </button>
    <button
        class="btn btn-sm btn-outline-secondary live-view-refresh"
        type="button"
        data-live-view-refresh
        title="Actualizează acum"
    >
        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
        <span class="visually-hidden">Actualizează acum</span>
    </button>
</div>
