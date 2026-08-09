<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#063f35">
    <title>THRIVE Climate Health</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --forest: #063f35; --green: #0d5b49; --gold: #d4a72c; --cream: #f7f5ec; --ink: #173c33; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; color: var(--ink); background: var(--cream); }
        .hero { min-height: 100vh; display: grid; place-items: center; padding: 1rem; background: radial-gradient(circle at 90% 0%, rgba(212,167,44,.3) 0, transparent 30%), linear-gradient(135deg, #063f35 0%, #0d5b49 58%, #174f42 100%); }
        .shell { width: min(100%, 1120px); }
        .brand { color: #f7d76b; letter-spacing: .14em; font-size: .76rem; font-weight: 700; }
        .hero-branding { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; }
        .hero-logo { width: min(100%, 330px); height: auto; object-fit: contain; flex: 0 0 auto; background: #fff; border: 5px solid var(--gold); border-radius: .8rem; padding: .4rem .7rem; box-shadow: 0 12px 30px rgba(0,0,0,.22); }
        .hero-company { display:none; }
        .hero-company small { display:block; color:#f7d76b; font-size:.8em; margin-top:.35rem; }
        .hero-copy { color: #f5f5e9; max-width: 650px; }
        .hero-copy h1 { font-size: clamp(2.5rem, 8vw, 5.6rem); letter-spacing: -.06em; line-height: .98; }
        .hero-copy p { color: #dce9df; max-width: 540px; font-size: clamp(1rem, 2vw, 1.2rem); }
        .location-card { max-width: 430px; margin-left: auto; background: rgba(255,255,255,.97); border-radius: 1.35rem; padding: clamp(1.25rem, 4vw, 2rem); box-shadow: 0 24px 70px rgba(0,0,0,.22); }
        .location-card .btn { min-height: 52px; border-radius: .8rem; font-weight: 700; background: var(--green); border-color: var(--gold); }
        .privacy { font-size: .76rem; color: #6b8195; }
        .location-search { border: 2px solid #dbe7df; border-radius: .8rem; padding: .85rem 1rem; }
        .location-search:focus { border-color: var(--gold); box-shadow: 0 0 0 .2rem rgba(212,167,44,.16); outline: 0; }
        .search-results button { border: 0; border-bottom: 1px solid #edf1ed; background: #fff; text-align: left; padding: .7rem .5rem; width: 100%; }
        .search-results button:hover { background: #f3f8f3; }
        @media (max-width: 767.98px) { .hero { padding: 1.25rem; align-items: start; } .hero-copy { padding-top: 2rem; } .location-card { margin-top: 2rem; } }
    </style>
</head>
<body>
    <main class="hero">
        <div class="shell">
            <div class="row align-items-center g-4 g-lg-5">
                <div class="col-lg-7 hero-copy">
                    <div class="hero-branding"><img class="hero-logo" src="{{ asset('logo.png') }}" alt="Eco Reset Edge logo"><div class="hero-company">Eco Reset Edge<small>Connect Ltd</small></div></div>
                    <div class="brand mb-4">THRIVE / CLIMATE HEALTH INTELLIGENCE</div>
                    <h1 class="fw-bold mb-4">Weather insight for healthier communities.</h1>
                    <p class="mb-0">Understand local weather and air-quality conditions, then turn climate signals into safer decisions for children, schools and health services.</p>
                </div>
                <div class="col-lg-5">
                    <section class="location-card" aria-labelledby="location-title">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-circle d-grid place-items-center" style="width:48px;height:48px;background:#f7e7a6;color:#0d5b49;font-size:1.4rem;">◎</div>
                            <div><h2 id="location-title" class="h5 mb-1">Check your location</h2><p class="small text-muted mb-0">Takes less than a minute</p></div>
                        </div>
                        <form id="weather-form" method="POST" action="{{ route('weather.search') }}">
                            @csrf
                            <input type="hidden" id="latitude" name="latitude">
                            <input type="hidden" id="longitude" name="longitude">
                            <label for="location-query" class="small fw-semibold mb-2">Search a town, district or facility</label>
                            <div class="input-group mb-2"><input id="location-query" class="form-control location-search" type="search" placeholder="Try Kampala, Buliisa or Gulu" autocomplete="off"><button type="button" class="btn btn-outline-secondary" style="min-height:52px;background:#fff;color:var(--forest);border-color:#dbe7df" onclick="searchLocation()">Search</button></div>
                            <div id="search-results" class="search-results small mb-3" aria-live="polite"></div>
                            <button type="button" class="btn btn-primary btn-lg w-100" onclick="getLocation()">Use my current location</button>
                        </form>
                        <p id="location-status" class="small text-muted mt-3 mb-0" role="status">Your location is used only to retrieve nearby environmental data.</p>
                        <p class="privacy mt-3 mb-0">No personal or child-level health data is collected.</p>
                    </section>
                </div>
            </div>
        </div>
    </main>
    <script>
        function selectLocation(latitude, longitude, label) {
            document.getElementById('latitude').value = latitude;
            document.getElementById('longitude').value = longitude;
            document.getElementById('location-status').textContent = `${label} selected. Loading climate-health risk...`;
            document.getElementById('weather-form').submit();
        }
        async function searchLocation() {
            const query = document.getElementById('location-query').value.trim();
            const results = document.getElementById('search-results');
            if (query.length < 2) { results.textContent = 'Enter at least two characters.'; return; }
            results.textContent = 'Searching locations...';
            try {
                const response = await fetch(`{{ route('climate.location-search') }}?q=${encodeURIComponent(query)}`);
                const payload = await response.json();
                const matches = payload.data || [];
                results.innerHTML = matches.length ? matches.map((item, index) => `<button type="button" data-result-index="${index}"><strong>${item.name}</strong><br><span class="text-muted">${item.subtitle || 'THRIVE location'}</span></button>`).join('') : 'No matching location found.';
                matches.forEach((item, index) => document.querySelector(`[data-result-index="${index}"]`).addEventListener('click', () => selectLocation(Number(item.latitude), Number(item.longitude), item.name)));
            } catch (error) { results.textContent = 'Location search is temporarily unavailable.'; }
        }
        document.getElementById('location-query').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); searchLocation(); } });
        function getLocation() {
            const status = document.getElementById('location-status');
            if (!navigator.geolocation) { status.textContent = 'Location is not supported by this browser.'; return; }
            status.textContent = 'Requesting your location…';
            navigator.geolocation.getCurrentPosition(function (position) {
                document.getElementById('latitude').value = position.coords.latitude;
                document.getElementById('longitude').value = position.coords.longitude;
                status.textContent = 'Location found. Loading your dashboard…';
                document.getElementById('weather-form').submit();
            }, function () { status.textContent = 'Location permission was not granted. Please enable it and try again.'; }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 });
        }
    </script>
</body>
</html>
