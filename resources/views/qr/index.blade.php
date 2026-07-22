@extends('layouts.app')

@section('title', 'Scanare QR')

@section('content')
<div class="login-shell">
    <div class="container">
        <div class="landing-card p-4 p-lg-5 rounded-4 shadow">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="login-pill mb-3"><i class="fa-solid fa-qrcode me-1"></i> Scanare QR</span>
                    <h1 class="display-6 fw-bold mb-3">Identificare rapida echipament</h1>
                    <p class="lead text-muted mb-4">
                        Introdu codul de pe eticheta QR sau codul intern al echipamentului.
                    </p>
                    <form method="post" action="{{ route('qr-scan.lookup') }}" class="row g-2">
                        @csrf
                        <div class="col-md-8">
                            <input name="code" value="{{ old('code') }}" class="form-control form-control-lg rounded-3" placeholder="Ex: QR-SCU-ROT-001-A1" autofocus required>
                        </div>
                        <div class="col-md-4">
                            <button class="btn login-submit text-white btn-lg rounded-3 w-100">
                                <i class="fa-solid fa-magnifying-glass me-1"></i> Cauta
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5 text-center">
                    <div class="qr-card mx-auto">
                        <div class="qr-box qr-box-lg"><i class="fa-solid fa-qrcode"></i></div>
                        <div class="fw-semibold mt-3">Cautare dupa cod</div>
                        <div class="small text-muted">Identificare rapida dupa QR sau cod intern.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
