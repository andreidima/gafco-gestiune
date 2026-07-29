@extends('layouts.app')

@section('title', 'Modifică '.$order->number)

@section('content')
<x-resource-form-shell
    :title="'Modifică '.$order->number"
    description="Comanda poate fi corectată cât timp este în starea Creat."
    :back-route="route('negotiated-orders.show', $order)"
    icon="fa-file-pen"
>
    @include('negotiated-orders._form', [
        'action' => route('negotiated-orders.update', $order),
        'method' => 'PUT',
        'backRoute' => route('negotiated-orders.show', $order),
        'submitLabel' => 'Salvează modificările',
    ])
</x-resource-form-shell>
@endsection
