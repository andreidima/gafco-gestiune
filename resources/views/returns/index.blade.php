@extends('layouts.app')

@section('title', 'Retururi')

@section('content')
<div class="container py-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-2">Retururi</h1>
            <p class="text-muted mb-3">Retururile sunt gestionate impreuna cu transferurile, pentru a pastra traseul, aprobarile si istoricul in acelasi loc.</p>
            <a href="{{ route('transfers.index', ['purpose' => 'return']) }}" class="btn btn-primary">Vezi retururile</a>
        </div>
    </div>
</div>
@endsection
