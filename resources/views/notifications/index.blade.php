@extends('layouts.app')

@section('title', 'Notificari')

@section('content')
<div class="resource-shell">
    <x-resource-page-header
        title="Notificari"
        description="Aprobari, alocari si schimbari care necesita atentia ta."
        :count="$notifications->total()"
        icon="fa-bell"
    >
        @if($unreadCount > 0)
            <x-slot:actions>
                <form method="post" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-check-double me-1"></i>Marcheaza toate citite</button></form>
            </x-slot:actions>
        @endif
    </x-resource-page-header>

    <div class="vstack gap-2">
        @forelse($notifications as $notification)
            <form method="post" action="{{ route('notifications.read', $notification->id) }}">
                @csrf
                <button class="card border-0 shadow-sm w-100 text-start {{ $notification->read_at ? '' : 'border-start border-4 border-primary' }}">
                    <span class="card-body d-flex justify-content-between align-items-start gap-3">
                        <span>
                            <strong class="d-block">{{ $notification->data['title'] ?? 'Notificare' }}</strong>
                            <span class="text-muted">{{ $notification->data['message'] ?? '' }}</span>
                        </span>
                        <small class="text-muted text-nowrap">{{ $notification->created_at->locale('ro')->diffForHumans() }}</small>
                    </span>
                </button>
            </form>
        @empty
            <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">Nu exista notificari.</div></div>
        @endforelse
    </div>

    <div class="resource-table-footer mt-3">{{ $notifications->links() }}</div>
</div>
@endsection
