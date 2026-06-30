@extends('layouts.app')

@section('title', 'Pagina de pornire')

@section('content')
<div class="login-shell">
    <div class="container">
        <div class="landing-card p-4 p-lg-5 rounded-4 shadow">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <div class="d-flex flex-wrap gap-2 mb-4">
                        <span class="login-pill">GAFCO</span>
                        <span class="login-pill alt">Baze si santiere</span>
                    </div>
                    <h1 class="display-6 fw-bold mb-3">{{ config('app.name') }}</h1>
                    <p class="lead text-muted mb-4">
                        Platforma pentru evidenta materialelor, utilajelor, sculelor, transferurilor si cererilor de sofer intre santiere.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('login') }}" class="btn login-submit text-white px-4 rounded-3">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> Autentificare
                        </a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stat-card accent-rose h-100">
                                <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
                                <div class="fw-semibold">Locatii</div>
                                <div class="small text-muted">Baze si santiere active.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card accent-slate h-100">
                                <div class="stat-icon"><i class="fa-solid fa-right-left"></i></div>
                                <div class="fw-semibold">Transferuri</div>
                                <div class="small text-muted">Miscari fara dublari.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card accent-forest h-100">
                                <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                                <div class="fw-semibold">Stocuri</div>
                                <div class="small text-muted">Evidenta pe locatie.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stat-card accent-amber h-100">
                                <div class="stat-icon"><i class="fa-solid fa-truck"></i></div>
                                <div class="fw-semibold">Soferi</div>
                                <div class="small text-muted">Cereri din santier.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
