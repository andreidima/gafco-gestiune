<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#703b3b">
    <title>Conexiune indisponibilă - {{ config('app.name') }}</title>
    @vite('resources/js/app.js')
</head>
<body class="offline-page">
    <main class="offline-card">
        <span class="offline-icon"><img src="{{ asset('icons/gafco-driver-192.png') }}" alt="" width="48" height="48"></span>
        <h1>Nu există conexiune la internet</h1>
        <p>Datele și acțiunile șoferului nu sunt salvate offline, pentru a evita confirmările greșite. Verifică semnalul și încearcă din nou.</p>
        <button type="button" class="btn btn-primary" onclick="window.location.reload()">Încearcă din nou</button>
    </main>
</body>
</html>
