@extends('layouts.app')

@section('title', 'Cereri sofer')

@section('content')
<div class="container py-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-2">Cereri sofer</h1>
            <p class="text-muted mb-3">Cererile si alocarile de soferi sunt gestionate acum in modulul de sarcini.</p>
            <a href="{{ route('tasks.index') }}" class="btn btn-primary">Deschide sarcinile</a>
        </div>
    </div>
</div>
@endsection
