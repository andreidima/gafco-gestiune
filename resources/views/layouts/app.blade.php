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
                            <li class="nav-item me-2 dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('locations.*', 'catalog-items.*', 'tracked-assets.*', 'supplier-receptions.*', 'consumption-reports.*', 'returns.*') ? 'active' : '' }}" href="#" id="gestiuneDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-boxes-stacked me-1"></i> Gestiune
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="gestiuneDropdown">
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('locations.*') ? 'active' : '' }}" href="{{ route('locations.index') }}">
                                            Locatii
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('tracked-assets.*') ? 'active' : '' }}" href="{{ route('tracked-assets.index') }}">
                                            Echipamente
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('catalog-items.*') ? 'active' : '' }}" href="{{ route('catalog-items.index') }}">
                                            Nomenclator
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('supplier-receptions.*') ? 'active' : '' }}" href="{{ route('supplier-receptions.index') }}">
                                            Receptii
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('consumption-reports.*') ? 'active' : '' }}" href="{{ route('consumption-reports.index') }}">
                                            Consum
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('returns.*') ? 'active' : '' }}" href="{{ route('returns.index') }}">
                                            Retururi
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item me-2 dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('transfers.*', 'driver-requests.*') ? 'active' : '' }}" href="#" id="transferuriDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-right-left me-1"></i> Transferuri
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="transferuriDropdown">
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('transfers.*') ? 'active' : '' }}" href="{{ route('transfers.index') }}">
                                            Transferuri
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('driver-requests.*') ? 'active' : '' }}" href="{{ route('driver-requests.index') }}">
                                            Soferi
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item me-2 dropdown">
                                <a class="nav-link dropdown-toggle {{ request()->routeIs('field.*') ? 'active' : '' }}" href="#" id="terenDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fa-solid fa-mobile-screen-button me-1"></i> Teren
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="terenDropdown">
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('field.driver') ? 'active' : '' }}" href="{{ route('field.driver') }}">
                                            Sofer
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('field.site-manager') ? 'active' : '' }}" href="{{ route('field.site-manager') }}">
                                            Sef santier
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('field.worker') ? 'active' : '' }}" href="{{ route('field.worker') }}">
                                            Muncitor
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item {{ request()->routeIs('qr-scan.*') ? 'active' : '' }}" href="{{ route('qr-scan.index') }}">
                                            QR
                                        </a>
                                    </li>
                                </ul>
                            </li>
                            <li class="nav-item me-2">
                                <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
                                    <i class="fa-solid fa-chart-column me-1"></i> Rapoarte
                                </a>
                            </li>
                            @if(auth()->user()?->hasAnyRole(['admin','super-admin']))
                                <li class="nav-item me-2 dropdown">
                                    <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*') ? 'active' : '' }}" href="#" id="utileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa-solid fa-gear me-1"></i> Setari
                                    </a>
                                    <ul class="dropdown-menu" aria-labelledby="utileDropdown">
                                        <li>
                                            <a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                                Utilizatori
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
