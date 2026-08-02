<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · GAFCO Gestiune</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; color: #141850; background: linear-gradient(145deg, #f7f5f4, #eef1fa); }
        main { width: min(560px, 100%); padding: 42px 32px; text-align: center; background: #fff; border: 1px solid rgba(20, 24, 80, .1); border-radius: 22px; box-shadow: 0 18px 50px rgba(20, 24, 80, .09); }
        .code { margin: 0 0 8px; color: #833f39; font-size: clamp(42px, 10vw, 72px); font-weight: 800; line-height: 1; }
        h1 { margin: 0; font-size: clamp(24px, 5vw, 34px); }
        p { margin: 14px auto 0; max-width: 440px; color: #667085; font-size: 17px; line-height: 1.55; }
        a { display: inline-block; margin-top: 26px; padding: 11px 18px; color: #fff; background: #833f39; border-radius: 10px; font-weight: 700; text-decoration: none; }
        a:focus-visible { outline: 3px solid rgba(131, 63, 57, .3); outline-offset: 3px; }
    </style>
</head>
<body>
<main>
    <div class="code">@yield('code')</div>
    <h1>@yield('message')</h1>
    <p>@yield('description')</p>
    <a href="{{ url('/') }}">Înapoi în aplicație</a>
</main>
</body>
</html>
