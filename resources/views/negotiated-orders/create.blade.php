@extends('layouts.app')

@section('title', 'Comandă negociată nouă')

@section('content')
<x-resource-form-shell
    title="Comandă negociată nouă"
    description="Înregistrează materialele, cantitățile și prețurile convenite. Stocul nu se modifică în această etapă."
    :back-route="route('negotiated-orders.index')"
    icon="fa-file-invoice-dollar"
>
    @include('negotiated-orders._form', [
        'action' => route('negotiated-orders.store'),
        'method' => 'POST',
        'backRoute' => route('negotiated-orders.index'),
        'submitLabel' => 'Creează comanda',
    ])
</x-resource-form-shell>
@endsection
