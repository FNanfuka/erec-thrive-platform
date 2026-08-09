<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#063f35">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <title>@yield('title', 'THRIVE Climate Health')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        :root { --forest:#063f35; --green:#0d5b49; --gold:#d4a72c; --cream:#f7f5ec; --ink:#173c33; --muted:#5f766e; }
        body { background:var(--cream); color:var(--ink); padding-bottom:4.5rem; }
        .topbar { background:var(--forest); color:#fff; border-bottom:4px solid var(--gold); }
        .brand-lockup { display:inline-flex; align-items:center; gap:.7rem; }
        .brand-mark { width:auto; height:auto; display:block; overflow:visible; border:3px solid var(--gold); border-radius:.75rem; background:#fff; padding:.25rem .5rem; box-shadow:0 5px 20px rgba(0,0,0,.2); }
        .brand-mark img { height:64px; width:auto; max-width:none; display:block; }
        .company-name { display:none; }
        .brand { color:#f7e7a6; letter-spacing:.13em; font-size:.72rem; font-weight:700; }
        .topbar a { color:#e8f1eb; text-decoration:none; }
        .topbar a:hover, .topbar a.active { color:#f7d76b; }
        .eyebrow { color:var(--green); letter-spacing:.13em; font-size:.7rem; font-weight:700; }
        .card { border-radius:1rem; }
        .mobile-nav { position:fixed; z-index:1000; bottom:0; left:0; right:0; background:#fff; border-top:1px solid #d9e2ec; }
        .mobile-nav a { color:var(--muted); font-size:.7rem; text-align:center; text-decoration:none; padding:.65rem .2rem; }
        .mobile-nav a.active { color:var(--green); font-weight:700; }
        .mobile-nav span { display:block; }
        @media (min-width:768px) { body { padding-bottom:0; } }
    </style>
    @yield('head')
</head>
<body>
    <header class="topbar">
        <div class="container py-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <a href="{{ route('dashboard.overview') }}" class="text-white text-decoration-none d-flex align-items-center gap-3"><span class="brand-lockup"><span class="brand-mark"><img src="{{ asset('logo.png') }}" alt="Eco Reset Edge logo"></span><span class="company-name">Eco Reset Edge<br><small>Connect Ltd</small></span></span><span><span class="brand d-block">ECO RESET EDGE / THRIVE</span><span class="fw-semibold">Climate-health intelligence</span></span></a>
            <nav class="d-none d-md-flex gap-4 small">
                <a href="{{ route('dashboard.overview') }}" class="{{ request()->routeIs('dashboard.overview') ? 'active' : '' }}">Overview</a>
                <a href="{{ route('dashboard.map') }}" class="{{ request()->routeIs('dashboard.map') ? 'active' : '' }}">Risk map</a>
                <a href="{{ route('dashboard.alerts') }}" class="{{ request()->routeIs('dashboard.alerts') ? 'active' : '' }}">Alerts</a>
                <a href="{{ route('dashboard.facilities') }}" class="{{ request()->routeIs('dashboard.facilities') ? 'active' : '' }}">Facilities</a>
                <a href="{{ route('dashboard.reports') }}" class="{{ request()->routeIs('dashboard.reports') ? 'active' : '' }}">Reports</a>
                <a href="{{ route('dashboard.support') }}" class="{{ request()->routeIs('dashboard.support') ? 'active' : '' }}">Support</a>
                <a href="{{ route('dashboard.pipeline') }}" class="{{ request()->routeIs('dashboard.pipeline') ? 'active' : '' }}">Data health</a>
            </nav>
        </div>
    </header>
    <main class="container py-4 py-md-5">@yield('content')</main>
    <nav class="mobile-nav d-md-none d-flex justify-content-around">
        <a href="{{ route('dashboard.overview') }}" class="{{ request()->routeIs('dashboard.overview') ? 'active' : '' }}">⌂<span>Overview</span></a>
        <a href="{{ route('dashboard.map') }}" class="{{ request()->routeIs('dashboard.map') ? 'active' : '' }}">⌖<span>Risk map</span></a>
        <a href="{{ route('dashboard.alerts') }}" class="{{ request()->routeIs('dashboard.alerts') ? 'active' : '' }}">!<span>Alerts</span></a>
        <a href="{{ route('dashboard.facilities') }}" class="{{ request()->routeIs('dashboard.facilities') ? 'active' : '' }}">♙<span>Facilities</span></a>
        <a href="{{ route('dashboard.reports') }}" class="{{ request()->routeIs('dashboard.reports') ? 'active' : '' }}">▣<span>Reports</span></a>
        <a href="{{ route('dashboard.pipeline') }}" class="{{ request()->routeIs('dashboard.pipeline') ? 'active' : '' }}">◷<span>Data health</span></a>
    </nav>
    @yield('scripts')
    <script>
        if ('serviceWorker' in navigator) navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
    </script>
</body>
</html>
