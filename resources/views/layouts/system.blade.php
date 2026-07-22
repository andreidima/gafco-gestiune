<!doctype html>
<html class="h-100" lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Administrare tehnica') - {{ config('app.name') }}</title>
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito:400,600,700,800" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    @vite('resources/js/app.js')
</head>
<body class="d-flex flex-column h-100 bg-light">
    <header>
        <nav class="navbar navbar-dark shadow culoare1">
            <div class="container d-flex justify-content-between">
                <a class="navbar-brand fw-bold" href="{{ route('dashboard') }}">{{ config('app.name') }}</a>
                <div class="d-flex align-items-center gap-3 text-white">
                    <span><i class="fa-solid fa-user me-1"></i>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-light" type="submit">Deconectare</button>
                    </form>
                </div>
            </div>
        </nav>
    </header>

    <main class="flex-shrink-0 py-4">
        @yield('content')
    </main>

    <footer class="mt-auto py-3 culoare1 text-white">
        <div class="container d-flex justify-content-between">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}</span>
            <a class="text-white" href="{{ route('dashboard') }}">Inapoi in aplicatie</a>
        </div>
    </footer>
</body>
</html>
