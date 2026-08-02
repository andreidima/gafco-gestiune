@extends('layouts.app')

@section('title', 'Autentificare')

@section('content')
<div class="login-shell">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-7 order-2 order-lg-1">
                <div class="login-hero h-100 p-4 p-lg-5 rounded-4">
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
                        <span class="login-pill">Pagina de pornire</span>
                        <span class="login-pill alt">Gestiune santiere</span>
                    </div>
                    <h1 class="display-6 fw-bold mb-3">
                        Bine ai venit la {{ config('app.name') }}
                    </h1>
                    <p class="lead mb-4">
                        Un singur loc pentru baze, santiere, transferuri, receptii si cereri de sofer.
                    </p>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon"><i class="fa-solid fa-right-left"></i></div>
                                <div>
                                    <div class="fw-semibold">Transferuri clare</div>
                                    <div class="small text-muted">Urmaresti miscarea materialelor si sculelor intre santiere.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                                <div>
                                    <div class="fw-semibold">Stocuri organizate</div>
                                    <div class="small text-muted">Materiale cantitative si echipamente cu identificare unica.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon"><i class="fa-solid fa-truck"></i></div>
                                <div>
                                    <div class="fw-semibold">Cereri de sofer</div>
                                    <div class="small text-muted">Santierele pot cere transport direct din aplicatie.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="login-feature">
                                <div class="login-feature-icon"><i class="fa-solid fa-receipt"></i></div>
                                <div>
                                    <div class="fw-semibold">Receptii cu aviz</div>
                                    <div class="small text-muted">Marfa intrata este legata de locatie si document.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="login-cta mt-4">
                        <div class="login-check"><i class="fa-solid fa-check"></i><span>Roluri pentru administrator, dispecer, sofer si sef santier</span></div>
                        <div class="login-check"><i class="fa-solid fa-check"></i><span>Panou rapid pentru operatiuni zilnice</span></div>
                        <div class="login-check"><i class="fa-solid fa-check"></i><span>Interfata simpla si usor de folosit</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 order-1 order-lg-2">
                <div class="card login-card border-0 shadow h-100">
                    <div class="card-header login-card-header text-center">
                        <div class="login-logo">
                            <i class="fa-solid fa-layer-group"></i>
                        </div>
                        <div class="fs-4 fw-semibold mt-3">{{ config('app.name') }}</div>
                        <div class="small opacity-75">Intra in cont pentru a continua</div>
                    </div>
                    <div class="card-body pb-0">
                        <div class="login-helper mb-3">
                            Foloseste codul sau adresa de email si parola primite de la administrator.
                            <span class="d-block mt-1">Cont pentru verificare: <strong>admin@example.com</strong> / <strong>password</strong>.</span>
                        </div>

                        <form method="POST" action="{{ route('login.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="login_code" class="form-label">Cod utilizator sau email</label>
                                <div class="input-group">
                                    <span class="input-group-text culoare1" aria-hidden="true"><i class="fas fa-user"></i></span>
                                    <input id="login_code" class="form-control @error('login_code') is-invalid @enderror" name="login_code" value="{{ old('login_code') }}" autocomplete="username" autofocus placeholder="Cod utilizator sau email" required>
                                </div>
                                @error('login_code')<span class="text-danger small">{{ $message }}</span>@enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Parola</label>
                                <div class="input-group">
                                    <span class="input-group-text culoare1" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="current-password" placeholder="Parola" required>
                                </div>
                                @error('password')<span class="text-danger small">{{ $message }}</span>@enderror
                            </div>

                            <div class="d-flex justify-content-center mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label" for="remember">Tine-ma minte</label>
                                </div>
                            </div>

                            <div class="d-grid col-md-8 mx-auto mb-3">
                                <button type="submit" class="btn login-submit text-white fs-5 shadow-sm rounded-3">
                                    Autentificare
                                </button>
                            </div>

                            <div class="text-center small text-muted mb-3">
                                Aplicatie pentru gestiune utilaje, scule si echipamente.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
