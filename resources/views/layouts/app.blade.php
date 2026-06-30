<!doctype html>
<html class="h-100" lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name')) - {{ config('app.name') }}</title>
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    @vite('resources/js/app.js')
</head>
<body class="d-flex flex-column h-100">
    @auth
        <header>
            <nav class="navbar navbar-lg navbar-expand-lg navbar-dark shadow culoare1">
                <div class="container">
                    <a class="navbar-brand me-4 fw-bold" href="{{ route('dashboard') }}">
                        {{ config('app.name') }}
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Meniu">
                        <span class="navbar-toggler-icon"></span>
                    </button>

                    <div class="collapse navbar-collapse" id="mainNavbar">
                        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                    <i class="fa-solid fa-house me-1"></i> Acasa
                                </a>
                            </li>
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('locations.*') ? 'active' : '' }}" href="{{ route('locations.index') }}">
                                    <i class="fa-solid fa-building me-1"></i> Locatii
                                </a>
                            </li>
                            <li class="nav-item me-2 dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('catalog-items.*', 'tracked-assets.*', 'supplier-receptions.*') ? 'active' : '' }}" href="#" id="stocuriDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-boxes-stacked me-1"></i> Stocuri
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="stocuriDropdown">
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('tracked-assets.*') ? 'active' : '' }}" href="{{ route('tracked-assets.index') }}">
                                            <i class="fa-solid fa-screwdriver-wrench me-1"></i> Echipamente
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('catalog-items.*') ? 'active' : '' }}" href="{{ route('catalog-items.index') }}">
                                            <i class="fa-solid fa-list me-1"></i> Nomenclator
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('supplier-receptions.*') ? 'active' : '' }}" href="{{ route('supplier-receptions.index') }}">
                                            <i class="fa-solid fa-receipt me-1"></i> Receptii
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('transfers.*') ? 'active' : '' }}" href="{{ route('transfers.index') }}">
                                    <i class="fa-solid fa-right-left me-1"></i> Transferuri
                                </a>
                            </li>
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('driver-requests.*') ? 'active' : '' }}" href="{{ route('driver-requests.index') }}">
                                    <i class="fa-solid fa-truck me-1"></i> Cereri sofer
                                </a>
                            </li>
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('qr-scan.*') ? 'active' : '' }}" href="{{ route('qr-scan.index') }}">
                                    <i class="fa-solid fa-qrcode me-1"></i> QR
                                </a>
                            </li>
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                                    <i class="fa-solid fa-chart-column me-1"></i> Rapoarte
                                </a>
                            </li>
                            @if(auth()->user()?->hasAnyRole(['admin','super-admin']))
                                <li class="nav-item me-2 dropdown">
                                    <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*') ? 'active' : '' }}" href="#" id="utileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-screwdriver-wrench me-1"></i> Utile
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="utileDropdown">
                                        <li>
                                            <a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                                <i class="fa-solid fa-users me-1"></i> Utilizatori
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            @endif
                        </ul>

                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle text-white" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-user me-1"></i> {{ auth()->user()->name }}
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li>
                                        <form method="post" action="{{ route('logout') }}">
                                            @csrf
                                            <button class="dropdown-item text-danger">
                                                <i class="fa-solid fa-sign-out-alt me-1"></i> Deconectare
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </header>
    @endauth

    <main class="flex-shrink-0 py-4">
        @if(session('status'))
            <div class="container">
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="container">
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1">Verifica datele introduse.</div>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="mt-auto py-2 text-center text-white culoare1">
        <p class="mb-1">© {{ date('Y') }} {{ config('app.name') }}</p>
        <span class="small">
            Aplicatie web dezvoltata de
            <a href="https://validsoftware.ro/" class="text-white" target="_blank" rel="noopener">validsoftware.ro</a>
        </span>
    </footer>
</body>
</html>
