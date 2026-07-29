@extends('layouts.app')

@php($editing = (bool) $user)
@section('title', $editing ? 'Modifica utilizatorul' : 'Utilizator nou')

@section('content')
<x-resource-form-shell
    :title="$editing ? 'Modifica utilizatorul' : 'Utilizator nou'"
    description="Codul de conectare inlocuieste emailul la autentificare."
    :back-route="route('users.index')"
    icon="fa-user-gear"
>
    <form method="post" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="resource-form-card">
        @csrf
        @if($editing) @method('put') @endif
        <div class="resource-form-section">
            <div class="resource-form-section-title">Identitate si conectare</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Nume complet</label><input name="name" value="{{ old('name', $user?->name) }}" class="form-control" required autofocus></div>
                <div class="col-md-3"><label class="form-label">Cod conectare</label><input name="login_code" value="{{ old('login_code', $user?->login_code) }}" class="form-control text-uppercase" required></div>
                <div class="col-md-3"><label class="form-label">Parola {{ $editing ? 'noua, optional' : '' }}</label><input name="password" type="password" class="form-control" @required(!$editing)><div class="form-text">{{ $editing ? 'Lasa gol pentru a pastra parola curenta.' : 'Minimum 6 caractere.' }}</div></div>
            </div>
        </div>
        <div class="resource-form-section">
            <div class="resource-form-section-title">Contact si acces</div>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Telefon / WhatsApp</label><input name="phone" value="{{ old('phone', $user?->phone) }}" class="form-control" placeholder="07... sau 407..."></div>
                <div class="col-md-4"><label class="form-label">Email optional</label><input name="email" type="email" value="{{ old('email', $user?->email && !str_ends_with($user->email, '@login.invalid') ? $user->email : '') }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">Roluri</label><select name="roles[]" class="form-select" multiple data-tom-select>@foreach($roles as $role)<option value="{{ $role->name }}" @selected(in_array($role->name, old('roles', $user?->roles?->pluck('name')->all() ?? [])))>{{ config("roles.labels.{$role->name}", $role->name) }}</option>@endforeach</select></div>
                <div class="col-12"><div class="form-check"><input type="hidden" name="active" value="0"><input name="active" value="1" class="form-check-input" type="checkbox" id="user-active" @checked(old('active', $user?->active ?? true))><label class="form-check-label" for="user-active">Cont activ</label></div></div>
            </div>
        </div>
        <div class="resource-form-actions-bar"><a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Renunta</a><button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>{{ $editing ? 'Salveaza modificarile' : 'Creeaza utilizatorul' }}</button></div>
    </form>
</x-resource-form-shell>
@endsection
