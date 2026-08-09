<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#063f35">
    <title>Weather Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <style>
        :root { --forest: #063f35; --green: #0d5b49; --gold: #d4a72c; --cream: #f7f5ec; --ink: #173c33; --muted: #5f766e; }
        body { background: var(--cream); color: var(--ink); }
        .topbar { background: var(--forest); color: #fff; border-bottom: 4px solid var(--gold); border-radius: 0 0 1.25rem 1.25rem; }
        .brand-lockup { display: inline-flex; align-items: center; }
        .brand-mark { width: auto; height: auto; display: block; overflow: visible; border: 3px solid var(--gold); border-radius: .75rem; background: #fff; padding: .25rem .5rem; box-shadow: 0 5px 18px rgba(0,0,0,.2); }
        .brand-mark img { height: 64px; width: auto; max-width: none; display: block; }
        .company-name { display: none; }
        .eyebrow { color: #f7d76b; letter-spacing: .13em; font-size: .7rem; font-weight: 700; }
        .metric { border-left: 4px solid var(--gold); background: #fffdf5 !important; min-height: 92px; }
        .metric .h4 { color: var(--forest); }
        .card { border-radius: 1rem; }
        .signal-card { transition: transform .18s ease, box-shadow .18s ease; }
        .signal-card:hover { transform: translateY(-2px); box-shadow: 0 .75rem 1.5rem rgba(16,42,67,.1) !important; }
        .activity-card { border-top: 4px solid var(--gold) !important; }
        .activity-card .activity-score { width: .7rem; height: .7rem; border-radius: 50%; display: inline-block; background: #198754; box-shadow: 0 0 0 4px rgba(25,135,84,.12); }
        .activity-card[data-score="2"] .activity-score { background: #f0ad00; box-shadow: 0 0 0 4px rgba(240,173,0,.14); }
        .activity-card[data-score="3"] .activity-score { background: #dc3545; box-shadow: 0 0 0 4px rgba(220,53,69,.14); }
        .pest-signal { position: relative; min-height: 154px; padding: 1rem 1rem 1rem 1.15rem; border-left: 5px solid #8b168a; background: linear-gradient(90deg,#f4f1f7 0%,#fff 100%); }
        .pest-signal[data-score="2"] { border-left-color: #ff5a36; }
        .pest-signal[data-score="3"] { border-left-color: #b51687; }
        .pest-signal .pest-mark { display: inline-grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: var(--forest); color: #f7d76b; font-size: .9rem; }
        .child-risk-panel { background: linear-gradient(135deg,#063f35 0%,#0d5b49 72%,#174f42 100%); color: #f7f5ec; overflow: hidden; }
        .child-risk-panel .risk-chip { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: .45rem .7rem; }
        .child-risk-panel .pathway { border-top: 1px solid rgba(255,255,255,.18); padding: .8rem 0; }
        .aqi-value { font-size: 3rem; font-weight: 700; line-height: 1; }
        .aqi-ring { width: 158px; height: 158px; border-radius: 50%; display: grid; place-items: center; background: conic-gradient(var(--gold) 0 42%, #0d5b49 42% 70%, rgba(255,255,255,.16) 70% 100%); box-shadow: 0 0 0 8px rgba(212,167,44,.12), 0 16px 32px rgba(6,63,53,.2); position: relative; }
        .aqi-ring::after { content: ''; position: absolute; inset: 13px; border-radius: 50%; background: #fffdf5; }
        .aqi-ring > div { position: relative; z-index: 1; }
        .aqi-ring .aqi-value { font-size: 2.7rem; }
        .air-pollutant { border-top: 1px solid #d8dee7; padding: .85rem 0; }
        .air-pollutant:last-child { border-bottom: 1px solid #d8dee7; }
        .air-command-panel { background: linear-gradient(145deg,#fffdf5 0%,#eef6f0 100%); border: 1px solid rgba(13,91,73,.16) !important; overflow: hidden; }
        .air-command-panel .card-body { position: relative; }
        .air-command-panel .card-body::before { content: 'THRIVE / AIR SIGNAL'; position: absolute; right: 1.25rem; top: 1.25rem; color: rgba(6,63,53,.28); font-size: .62rem; letter-spacing: .16em; font-weight: 800; }
        .pollutant-meter { height: .35rem; border-radius: 99px; background: #dfe9e2; overflow: hidden; margin-top: .45rem; }
        .pollutant-meter span { display: block; height: 100%; width: var(--meter, 40%); background: linear-gradient(90deg,var(--green),var(--gold)); border-radius: inherit; }
        .air-map { height: 390px; border-radius: .75rem; }
        .air-tab { color: var(--muted); text-decoration: none; border-bottom: 2px solid transparent; padding: .65rem .85rem; }
        .air-tab.active, .air-tab:hover { color: var(--forest); border-bottom-color: var(--gold); }
        .local-weather-card { background: #20242c; color: #f6f7f2; overflow: hidden; }
        .local-weather-card .metric { background: transparent !important; border-left: 0; border-bottom: 1px solid rgba(255,255,255,.14); border-radius: 0 !important; }
        .local-weather-card .metric .h4, .local-weather-card h2 { color: #fff; }
        .local-weather-card .text-muted { color: #aeb8c2 !important; }
        .weather-symbol { font-size: 3.2rem; line-height: 1; color: #f7d76b; text-shadow: 0 0 18px rgba(247,215,107,.35); }
        .weather-chart-wrap { height: 190px; background: linear-gradient(180deg,rgba(255,255,255,.04),rgba(255,255,255,.01)); border-top: 1px solid rgba(255,255,255,.12); }
        .weather-tabs button { color: #dce5e9; background: transparent; border: 0; border-bottom: 3px solid transparent; padding: .55rem .75rem; }
        .weather-tabs button.active { color: #fff; border-bottom-color: #f7d76b; }
        .badge-orange { background-color: #f08c00; color: #fff; }
        #climate-map, #national-risk-map { min-height: 280px; height: clamp(280px, 48vw, 420px) !important; }
        .legend-dot { display: inline-block; width: .65rem; height: .65rem; border-radius: 50%; margin-right: .25rem; }
        .chart-wrap { position: relative; height: 300px; padding: .75rem .5rem .25rem; border: 1px solid rgba(0,245,255,.28); border-radius: .9rem; background: radial-gradient(circle at 12% 0%, rgba(0,245,255,.13), transparent 38%), linear-gradient(145deg, #071522 0%, #0b1b2d 100%); box-shadow: inset 0 0 28px rgba(0,245,255,.06), 0 0 18px rgba(0,245,255,.08); }
        .risk-details { background: linear-gradient(135deg, #063f35 0%, #0d5b49 100%); color: #f7f5ec; border: 1px solid rgba(212,167,44,.42); box-shadow: inset 0 0 22px rgba(212,167,44,.08); }
        .risk-details .risk-value { color: #7df9ff; font-size: 1.25rem; font-weight: 800; }
        .risk-details .risk-label { color: #9fb3c8; font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; }
        .rain-shower-icon { width: 44px; height: 44px; position: relative; text-align: center; filter: drop-shadow(0 0 8px rgba(0,200,255,.75)); pointer-events: none; }
        .rain-cloud { display: block; color: #dff6ff; font-size: 2rem; line-height: 1; text-shadow: 0 0 8px #00c8ff; }
        .rain-drops { display: block; color: #00c8ff; font-size: 1.6rem; line-height: .6; letter-spacing: .12rem; animation: rain-drop-fall .9s linear infinite; }
        @keyframes rain-drop-fall { 0%,100% { transform: translateY(-2px); opacity: .45; } 50% { transform: translateY(5px); opacity: 1; } }
        @media (max-width: 575.98px) { .container { padding-left: 1rem; padding-right: 1rem; } .topbar { margin-left: -.25rem; margin-right: -.25rem; border-radius: 0 0 1rem 1rem; } .topbar .btn { min-height: 44px; } .aqi-value { font-size: 2.5rem; } .card-body { padding: 1.15rem !important; } }
    </style>
</head>

<body>
    <main class="container pb-5">
        <div class="topbar px-3 px-md-4 py-3 mb-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <span class="brand-lockup"><span class="brand-mark"><img src="{{ asset('logo.png') }}" alt="Eco Reset Edge logo"></span><span class="company-name">Eco Reset Edge<br><small>Connect Ltd</small></span></span>
                <div><p class="eyebrow mb-1">ECO RESET EDGE / THRIVE</p><h1 class="h4 mb-0">Local conditions</h1></div>
            </div>
            <div class="d-flex align-items-center gap-2 gap-md-3">
                <a href="{{ route('dashboard.overview') }}" class="btn btn-sm btn-outline-light">Overview</a>
                <a href="{{ route('dashboard.map') }}" class="btn btn-sm btn-outline-light d-none d-sm-inline-block">National map</a>
                <a href="{{ route('home') }}" class="btn btn-sm btn-light">New search</a>
            </div>
        </div>
        <p class="text-muted mb-4">Live environmental signals for your current location.</p>

        <div class="row g-4">
            <div class="col-lg-6">
                <section class="card local-weather-card border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3"><div class="d-flex align-items-center gap-3"><span class="weather-symbol">{{ $weatherSummary['icon'] }}</span><div><div class="display-4 fw-light lh-1">{{ $data['current']['temperature_2m'] ?? '—' }}<sup class="fs-6">°C</sup></div><div class="small text-muted">Feels like {{ $data['current']['apparent_temperature'] ?? '—' }}°C</div></div></div><div class="text-end"><h2 class="h5 mb-1">{{ $weatherSummary['label'] }}</h2><div class="small text-muted">{{ $data['daily']['time'][0] ?? 'Today' }}</div><div class="small mt-1">{{ $weatherSummary['rain'] ? 'Rain expected' : 'Dry interval expected' }}</div></div></div>
                        @if(!empty($data['current']) && is_array($data['current']))
                            <div class="row g-3">
                                <div class="col-4"><div class="metric p-2"><small class="text-muted">Rain chance</small><div class="h6 mb-0">{{ $data['daily']['precipitation_probability_max'][0] ?? '—' }}%</div></div></div>
                                <div class="col-4"><div class="metric p-2"><small class="text-muted">Humidity</small><div class="h6 mb-0">{{ $data['current']['relative_humidity_2m'] ?? '—' }}%</div></div></div>
                                <div class="col-4"><div class="metric p-2"><small class="text-muted">Wind</small><div class="h6 mb-0">{{ $data['current']['wind_speed_10m'] ?? '—' }} km/h</div></div></div>
                            </div>
                            <div class="weather-tabs d-flex mt-3"><button type="button" class="active" data-weather-mode="temperature">Temperature</button><button type="button" data-weather-mode="precipitation">Precipitation</button><button type="button" data-weather-mode="wind">Wind</button></div><div class="weather-chart-wrap"><canvas id="local-weather-chart"></canvas></div>
                        @else
                            <p class="text-muted mb-0">Unable to load weather data right now.</p>
                        @endif
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="card air-command-panel border-0 shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h2 class="h5 mb-1">Air quality</h2>
                                <small class="text-muted">European Air Quality Index</small>
                            </div>
                            <span class="badge {{ $airQualityIndex['class'] === 'orange' ? 'badge-orange' : 'text-bg-'.$airQualityIndex['class'] }}">{{ $airQualityIndex['label'] }}</span>
                        </div>
                        @if(!empty($airQuality['current']) && is_array($airQuality['current']))
                            <div class="d-flex align-items-center gap-4 mb-4">
                                <div class="aqi-ring"><div class="text-center"><div class="aqi-value">{{ $airQualityValue ?? '—' }}</div><small class="text-muted">AQI</small></div></div>
                                <div><div class="h5 mb-2">{{ $airQualityIndex['label'] }}</div><p class="small mb-1">{{ $airQualityHealth }}</p><small class="text-muted">Based on current pollutants</small></div>
                            </div>
                            <div class="small">
                                @foreach([['PM2.5','Fine particulate matter','pm2_5'],['PM10','Particulate matter','pm10'],['NO₂','Nitrogen dioxide','nitrogen_dioxide'],['O₃','Ground-level ozone','ozone']] as $pollutant)
                                    <div class="air-pollutant d-flex justify-content-between gap-3"><div class="flex-grow-1"><strong>{{ $pollutant[0] }}</strong><div class="text-muted">{{ $pollutant[1] }}</div><div class="pollutant-meter" style="--meter: {{ is_numeric($airQuality['current'][$pollutant[2]] ?? null) ? min(100, max(8, ((float) $airQuality['current'][$pollutant[2]] / 160) * 100)) : 8 }}%"><span></span></div></div><div class="text-end"><strong>{{ $airQuality['current'][$pollutant[2]] ?? 'N/A' }}</strong><div class="text-muted">µg/m³</div></div></div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted mb-0">Air quality data is temporarily unavailable.</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <section class="child-risk-panel rounded-4 shadow-sm mt-4 p-4">
            <div class="row g-4 align-items-start"><div class="col-lg-4"><p class="eyebrow mb-1">THRIVE CHILD CLIMATE RISK</p><h2 class="h4 mb-2">What could this location mean for children?</h2><p class="small mb-3" style="color:#cce2d4">A location-specific screening layer that combines terrain, rainfall, air quality and water proximity.</p><div class="d-flex flex-wrap gap-2"><span class="risk-chip small">{{ $childRisk['location']['terrain'] ?? 'Terrain unavailable' }}</span><span class="risk-chip small">{{ $childRisk['location']['altitude_m'] !== null ? number_format($childRisk['location']['altitude_m'], 0).' m altitude' : 'Altitude unavailable' }}</span><span class="risk-chip small">{{ $childRisk['location']['relief_m'] !== null ? $childRisk['location']['relief_m'].' m local relief' : 'Terrain relief unavailable' }}</span><span class="risk-chip small">{{ $childRisk['location']['nearest_water_body'] ?? 'Water proximity unavailable' }} · {{ $childRisk['location']['water_distance_km'] ?? '—' }} km</span></div></div><div class="col-lg-8"><div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Risk pathways</h3><span class="badge text-bg-{{ $childRisk['overall']['status'] ?? 'secondary' }}">{{ $childRisk['overall']['label'] ?? 'Unavailable' }}</span></div>@foreach(($childRisk['pathways'] ?? []) as $pathway)<div class="pathway"><div class="d-flex justify-content-between gap-3"><strong class="small">{{ $pathway['name'] }}</strong><span class="small" style="color:#f7d76b">{{ $pathway['risk']['label'] }}</span></div><div class="small mt-1" style="color:#cce2d4">{{ $pathway['why'] }}</div></div>@endforeach</div></div><div class="border-top mt-3 pt-3 small" style="border-color:rgba(255,255,255,.18)!important;color:#cce2d4"><strong>Action now:</strong> {{ implode(' ', $childRisk['actions'] ?? []) }}<br><span class="opacity-75">{{ $childRisk['scope'] ?? 'Screening support only.' }}</span></div>
            <div class="mt-4 pt-3 border-top" style="border-color:rgba(255,255,255,.18)!important"><div class="d-flex justify-content-between align-items-center mb-3"><strong class="small">Why this result?</strong><span class="small" style="color:#f7d76b">{{ $childRisk['explainability']['evidence_status'] ?? 'Evidence status unavailable' }}</span></div><div class="row g-2">@foreach(($childRisk['explainability']['chain'] ?? []) as $step)<div class="col-6 col-xl-3"><div class="risk-chip h-100"><span class="small" style="color:#f7d76b">0{{ $step['step'] }}</span><strong class="d-block small mt-1">{{ $step['title'] }}</strong><span class="small" style="color:#cce2d4">{{ $step['detail'] }}</span></div></div>@endforeach</div><div class="row g-2 mt-2">@foreach(($childRisk['explainability']['drivers'] ?? []) as $driver)<div class="col-12 col-md-6"><div class="risk-chip d-flex justify-content-between gap-3"><span><strong class="small">{{ $driver['title'] }}</strong><span class="small d-block" style="color:#cce2d4">{{ $driver['detail'] }}</span></span><span class="small text-nowrap" style="color:#f7d76b">{{ $driver['value'] }}</span></div></div>@endforeach</div></div>
        </section>

        <section id="air-quality-section" class="card border-0 shadow-sm mt-4">
            <div class="card-body p-0">
                <nav class="d-flex flex-wrap border-bottom px-3" aria-label="Local conditions"><a class="air-tab active" href="#air-quality-section">AIR QUALITY</a><a class="air-tab" href="#health-activities-section">HEALTH & ACTIVITIES</a><a class="air-tab" href="#climate-map">MONITORING MAP</a></nav>
                <div class="p-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><p class="eyebrow mb-1">UGANDA AIR QUALITY OUTLOOK</p><h2 class="h5 mb-1">Current air quality across monitored locations</h2><p class="small text-muted mb-0">Open-Meteo air-quality screening with an easy-to-read AQI scale.</p></div><span class="badge text-bg-light border">Open-Meteo</span></div><div id="air-quality-map" class="air-map bg-light"></div><div class="d-flex flex-wrap gap-3 small mt-3"><span><i class="legend-dot" style="background:#1a9850"></i>Good</span><span><i class="legend-dot" style="background:#fee08b"></i>Moderate</span><span><i class="legend-dot" style="background:#fdae61"></i>Sensitive groups</span><span><i class="legend-dot" style="background:#d73027"></i>Unhealthy</span><span><i class="legend-dot" style="background:#762a83"></i>Very unhealthy</span></div><div id="air-quality-map-status" class="small text-muted mt-2">Loading regional air-quality data...</div></div>
            </div>
        </section>

        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div><p class="eyebrow mb-1">EARLY ACTION</p><h2 class="h5 mb-1">What local teams can do now</h2><p class="small text-muted mb-0">Screening guidance for schools, communities and health facilities.</p></div>
                    <span class="badge text-bg-light border text-secondary">{{ $signals['air']['confidence'] ?? 'unavailable' }} confidence</span>
                </div>
                <div class="row g-3">
                    @foreach(['air' => 'Air quality', 'heat' => 'Heat stress'] as $key => $title)
                        <div class="col-md-6"><h3 class="h6">{{ $title }} · {{ $signals[$key]['label'] }}</h3><ul class="small mb-0">@foreach(($signals[$key]['actions'] ?? ['No action guidance available.']) as $action)<li>{{ $action }}</li>@endforeach</ul></div>
                    @endforeach
                </div>
                @if(($airQualityForecast['available'] ?? false) && !empty($airQualityForecast['peak']))
                    <p class="small text-muted border-top mt-3 pt-3 mb-0">Next 24-hour air-quality peak: AQI {{ $airQualityForecast['peak']['european_aqi'] ?? 'N/A' }} · PM2.5 {{ $airQualityForecast['peak']['pm2_5'] ?? 'N/A' }} µg/m³.</p>
                @endif
            </div>
        </section>

        <section id="health-activities-section" class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div><p class="eyebrow mb-1">HEALTH & ACTIVITIES</p><h2 class="h5 mb-1">How today’s conditions may affect people</h2><p class="small text-muted mb-0">THRIVE translates environmental signals into practical guidance for children, communities and field teams.</p></div>
                    <div class="text-end"><span class="badge text-bg-{{ $signals['activities']['overall']['status'] ?? 'secondary' }}">{{ $signals['activities']['overall']['label'] ?? 'Unavailable' }}</span><div class="small text-muted mt-2">{{ $signals['activities']['confidence'] ?? 'unavailable' }} confidence</div></div>
                </div>
                <p class="small mb-3">{{ $signals['activities']['overall']['summary'] ?? 'Activity guidance is unavailable.' }}</p>
                <div class="row g-3">
                    @foreach(($signals['activities']['activities'] ?? []) as $activity)
                        <div class="col-md-6 col-xl-3"><article class="card activity-card border-0 shadow-sm h-100" data-score="{{ $activity['score'] ?? 0 }}"><div class="card-body"><div class="d-flex justify-content-between align-items-center gap-2 mb-2"><span class="activity-score"></span><span class="small text-muted">{{ ($activity['score'] ?? 0) >= 3 ? 'High caution' : (($activity['score'] ?? 0) === 2 ? 'Use caution' : 'Fair') }}</span></div><h3 class="h6">{{ $activity['title'] }}</h3><p class="small text-muted">{{ $activity['best_window'] }}</p><ul class="small mb-0">@foreach($activity['actions'] ?? [] as $action)<li>{{ $action }}</li>@endforeach</ul></div></article></div>
                    @endforeach
                </div>
                <p class="small text-muted border-top mt-3 pt-3 mb-0">{{ $signals['activities']['scope'] ?? 'Screening guidance only.' }}</p>
            </div>
        </section>

        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><p class="eyebrow mb-1">PEST OUTLOOK</p><h2 class="h5 mb-1">Will pests be active today?</h2><p class="small text-muted mb-0">Environmental screening for prevention planning around homes, schools and health facilities.</p></div><span class="badge text-bg-light border text-secondary">Screening signal</span></div>
                <div class="row g-3">
                    @foreach(($signals['pests']['pests'] ?? []) as $pest)
                        <div class="col-md-4"><article class="pest-signal h-100" data-score="{{ $pest['risk']['score'] ?? 0 }}"><div class="d-flex justify-content-between align-items-center mb-3"><span class="pest-mark" aria-hidden="true">✣</span><span class="small fw-semibold">{{ $pest['risk']['label'] ?? 'Unavailable' }}</span></div><h3 class="h6 mb-2">{{ $pest['title'] }}</h3><p class="small text-muted mb-2">{{ $pest['summary'] }}</p><ul class="small mb-0">@foreach($pest['actions'] ?? [] as $action)<li>{{ $action }}</li>@endforeach</ul></article></div>
                    @endforeach
                </div>
                <p class="small text-muted border-top mt-3 pt-3 mb-0">{{ $signals['pests']['scope'] ?? 'Pest guidance unavailable.' }}</p>
            </div>
        </section>

        <section class="mt-5">
            <div class="d-flex justify-content-between align-items-end mb-3">
                <div>
                    <p class="text-primary fw-semibold mb-1">THRIVE CLIMATE-HEALTH LAYER</p>
                    <h2 class="h4 mb-1">Early signals for action</h2>
                    <p class="text-muted mb-0">Decision support for communities, schools and health services.</p>
                </div>
                <span class="badge text-bg-light border text-secondary">Prototype screening</span>
            </div>
            <div class="row g-3">
                @foreach([
                    'air' => ['title' => 'Air quality', 'icon' => 'PM'],
                    'heat' => ['title' => 'Heat stress', 'icon' => '°C'],
                    'flood' => ['title' => 'Flood exposure', 'icon' => '雨'],
                    'disease' => ['title' => 'Disease warning', 'icon' => '⚕'],
                ] as $key => $meta)
                    <div class="col-md-6 col-xl-3">
                        <article class="card signal-card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-primary fw-bold">{{ $meta['icon'] }}</span>
                                    <span class="badge text-bg-{{ $signals[$key]['status'] }}">{{ $signals[$key]['label'] }}</span>
                                </div>
                                <h3 class="h6">{{ $meta['title'] }}</h3>
                                <p class="small text-muted mb-2">{{ $signals[$key]['summary'] }}</p>
                                <small class="text-uppercase text-secondary">{{ $signals[$key]['scope'] }}</small>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- local forecast chart removed; restored to the original national outlook -->
        <!--
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-3">
                    <div><p class="forecast-kicker mb-1">LOCAL OUTLOOK</p><h2 id="local-forecast-title" class="h4 mb-1">Seven-day forecast</h2><p class="small text-muted mb-0">Temperature and rainfall planning for this location.</p></div>
                    <span class="badge text-bg-light border text-secondary">Open-Meteo · 7 days</span>
                </div>
                @if(!empty($data['daily']['time']) && is_array($data['daily']['time']))
                    <div class="chart-wrap"><canvas id="local-forecast-chart" aria-label="Seven day local temperature and rainfall forecast"></canvas></div>
                    <div id="forecast-note" class="forecast-note rounded p-3 mt-3 small">Forecast summary is loading...</div>
                @else
                    <div class="alert alert-light mb-0">The local forecast is temporarily unavailable.</div>
                @endif
            </div>
        </section>
        -->

        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex flex-column flex-sm-row justify-content-between gap-3 mb-3">
                    <div>
                        <p class="eyebrow mb-1">NATIONAL SCREENING LAYER</p>
                        <h2 class="h5 mb-1">Uganda climate risk outlook</h2>
                        <p class="small text-muted mb-0">A national heat surface highlights areas needing closer attention.</p>
                    </div>
                    <div class="btn-group align-self-start" role="group" aria-label="Risk layer">
                        <button type="button" class="btn btn-sm btn-outline-danger active" id="heat-layer-button">Heat</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="flood-layer-button">Heavy rain</button>
                    </div>
                </div>
                <div class="input-group mb-3" role="search">
                    <input id="national-risk-search" type="search" class="form-control" placeholder="Search a district or facility" aria-label="Search a district or facility">
                    <button id="national-risk-search-button" class="btn btn-outline-primary" type="button">Search</button>
                </div>
                <p id="national-risk-search-status" class="small text-muted mb-3" aria-live="polite"></p>
                <div id="national-risk-map" class="rounded mb-3"></div>
                <div class="d-flex flex-wrap gap-3 small text-muted mb-4">
                    <span><i class="legend-dot" data-level="low"></i> Low</span><span><i class="legend-dot" data-level="watch"></i> Watch</span><span><i class="legend-dot" data-level="warning"></i> Warning</span><span><i class="legend-dot" data-level="severe"></i> Severe</span>
                </div>
                <div id="national-risk-details" class="risk-details rounded p-3 mb-3" aria-live="polite">Loading risk details...</div>
                <div class="chart-wrap"><canvas id="national-trend-chart" aria-label="Seven historical days and two forecast days national risk trend"></canvas></div>
                <p id="national-risk-status" class="small text-muted mt-3 mb-0">Loading national screening data…</p>
            </div>
        </section>

        <section class="card border-0 shadow-sm mt-4" aria-labelledby="coverage-title">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div><p class="eyebrow mb-1">NATIONAL COVERAGE</p><h2 id="coverage-title" class="h5 mb-1">Data pipeline status</h2></div>
                    <span id="pipeline-updated" class="small text-muted">Checking…</span>
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3"><div class="metric rounded p-3"><small class="text-muted">Districts</small><div id="district-count" class="h4 mb-0">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="metric rounded p-3"><small class="text-muted">Facilities</small><div id="facility-count" class="h4 mb-0">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="metric rounded p-3"><small class="text-muted">CHIRPS queued</small><div id="chirps-queued" class="h4 mb-0">—</div></div></div>
                    <div class="col-6 col-md-3"><div class="metric rounded p-3"><small class="text-muted">CHIRPS complete</small><div id="chirps-complete" class="h4 mb-0">—</div></div></div>
                </div>
                <p id="pipeline-note" class="small text-muted mt-3 mb-0">District boundaries are synchronized. Flood scores become available after CHIRPS rainfall and climatological baselines are loaded.</p>
            </div>
        </section>

        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <h2 class="h6">Data and safeguarding</h2>
                        <p class="small text-muted mb-0">This prototype uses aggregate environmental data and does not collect personal or child-level health records. Signals are decision support, not a diagnosis or emergency instruction.</p>
                    </div>
                    <div class="col-lg-5">
                        <h2 class="h6">Pipeline readiness</h2>
                        <div class="d-flex justify-content-between small border-bottom py-2"><span>Weather and AQI</span><span class="text-success">Live</span></div>
                        <div class="d-flex justify-content-between small border-bottom py-2"><span>OpenAQ anomaly baseline</span><span class="text-info">Connector ready</span></div>
                        <div class="d-flex justify-content-between small border-bottom py-2"><span>CHIRPS flood layer</span><span class="text-warning">Queued</span></div>
                        <div class="d-flex justify-content-between small pt-2"><span>DHIS2 disease feed</span><span class="text-warning">Access required</span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card border-0 shadow-sm mt-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h2 class="h5 mb-1">Monitoring locations</h2>
                        <p class="text-muted small mb-0">District and facility points can be added through the controlled location importer.</p>
                    </div>
                    <span id="location-count" class="badge text-bg-light border text-secondary">Loading…</span>
                </div>
                <div class="input-group mb-2" role="search">
                    <input id="monitoring-location-search" type="search" class="form-control" placeholder="Search a district or facility" aria-label="Search a monitoring location">
                    <button id="monitoring-location-search-button" class="btn btn-outline-primary" type="button">Find location</button>
                </div>
                <p id="monitoring-location-search-status" class="small text-muted mb-3" aria-live="polite"></p>
                <div id="climate-map" class="rounded" style="height: 360px;"></div>
                <div id="monitoring-location-details" class="small text-muted mt-3">Search for a monitored district or facility to focus this map.</div>
            </div>
        </section>

        <p class="text-muted small mt-4">Data provided by Open-Meteo. Weather is cached for 10 minutes and air quality for 15 minutes.</p>
    </main>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <script>
        const currentLocation = @json([$latitude, $longitude]);
        const localHourly = @json($data['hourly'] ?? []);
        let localWeatherChart;
        function renderLocalWeatherChart(mode = 'temperature') {
            const canvas = document.getElementById('local-weather-chart');
            if (!canvas || !window.Chart || !localHourly.time?.length) return;
            const labels = localHourly.time.slice(0, 24).map(time => new Date(time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }));
            const settings = { temperature: { label: 'Temperature °C', key: 'temperature_2m', color: '#f7d76b', fill: 'rgba(212,167,44,.28)' }, precipitation: { label: 'Rain chance %', key: 'precipitation_probability', color: '#62d0ff', fill: 'rgba(98,208,255,.24)' }, wind: { label: 'Wind km/h', key: 'wind_speed_10m', color: '#9be28f', fill: 'rgba(155,226,143,.2)' } }[mode];
            const values = (localHourly[settings.key] || []).slice(0, 24).map(value => Number(value));
            if (localWeatherChart) localWeatherChart.destroy();
            localWeatherChart = new Chart(canvas, { type: 'line', data: { labels, datasets: [{ label: settings.label, data: values, borderColor: settings.color, backgroundColor: settings.fill, fill: true, tension: .38, pointRadius: 0, borderWidth: 2 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } }, scales: { x: { grid: { display: false }, ticks: { color: '#aeb8c2', maxTicksLimit: 7 } }, y: { beginAtZero: mode !== 'temperature', grid: { color: 'rgba(255,255,255,.1)' }, ticks: { color: '#aeb8c2' } } } } });
        }
        document.querySelectorAll('[data-weather-mode]').forEach(button => button.addEventListener('click', () => { document.querySelectorAll('[data-weather-mode]').forEach(item => item.classList.remove('active')); button.classList.add('active'); renderLocalWeatherChart(button.dataset.weatherMode); }));
        renderLocalWeatherChart();
        const airMap = L.map('air-quality-map').setView([1.3733, 32.2903], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap contributors' }).addTo(airMap);
        fetch('{{ route('climate.air-quality-map') }}').then(response => response.json()).then(payload => {
            const points = (payload.points || []).filter(point => Number.isFinite(Number(point.aqi)));
            const heatPoints = points.map(point => [point.latitude, point.longitude, Math.min(1, Number(point.aqi) / 200)]);
            if (heatPoints.length && L.heatLayer) L.heatLayer(heatPoints, { radius: 42, blur: 30, minOpacity: .42, gradient: { .1: '#1a9850', .3: '#fee08b', .5: '#fdae61', .7: '#d73027', 1: '#762a83' } }).addTo(airMap);
            const colors = { 'Good': '#1a9850', 'Moderate': '#fee08b', 'Unhealthy for sensitive groups': '#fdae61', 'Unhealthy': '#d73027', 'Very Unhealthy': '#762a83', 'Hazardous': '#542788' };
            points.forEach(point => L.circleMarker([point.latitude, point.longitude], { radius: 8, color: '#fff', weight: 2, fillColor: colors[point.label] || '#d73027', fillOpacity: .95 }).addTo(airMap).bindPopup(`<strong>${point.name}</strong><br>AQI ${point.aqi}<br>PM2.5 ${point.pm2_5 ?? '—'} µg/m³<br>${point.label}`));
            document.getElementById('air-quality-map-status').textContent = points.length ? `${points.length} monitored locations · Source: ${payload.source}` : 'Regional air-quality data is temporarily unavailable.';
        }).catch(() => document.getElementById('air-quality-map-status').textContent = 'Regional air-quality data is temporarily unavailable.');
        /*
        const localForecast = @json($data['daily'] ?? []);

        function renderLocalForecast() {
            const canvas = document.getElementById('local-forecast-chart');
            if (!canvas || !window.Chart || !localForecast.time?.length) return;
            const labels = localForecast.time.map(date => new Intl.DateTimeFormat(undefined, { weekday: 'short', day: 'numeric' }).format(new Date(`${date}T12:00:00`)));
            const maxTemps = localForecast.temperature_2m_max || [];
            const minTemps = localForecast.temperature_2m_min || [];
            const rainfall = localForecast.precipitation_sum || [];
            const peakRain = Math.max(...rainfall.map(Number).filter(Number.isFinite), 0);
            const hottest = Math.max(...maxTemps.map(Number).filter(Number.isFinite));
            const hottestDay = maxTemps.indexOf(hottest);
            const note = document.getElementById('forecast-note');
            if (note && Number.isFinite(hottest)) note.innerHTML = `<strong>${labels[hottestDay] || 'Forecast'}:</strong> highest daytime temperature around ${hottest.toFixed(1)} °C${peakRain > 0 ? ` · maximum daily rainfall around ${peakRain.toFixed(1)} mm` : ' · no significant rainfall signal in this outlook'}. Plan outdoor activities around the warmer and wetter periods.`;
            const context = canvas.getContext('2d');
            const temperatureGradient = context.createLinearGradient(0, 0, 0, 280);
            temperatureGradient.addColorStop(0, 'rgba(15,118,110,.28)');
            temperatureGradient.addColorStop(1, 'rgba(15,118,110,.02)');
            new Chart(canvas, {
                type: 'line',
                data: { labels, datasets: [
                    { label: 'High °C', data: maxTemps, borderColor: '#0f766e', backgroundColor: temperatureGradient, fill: true, tension: .38, borderWidth: 3, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#ffffff', pointBorderWidth: 2, yAxisID: 'temperature' },
                    { label: 'Low °C', data: minTemps, borderColor: '#63b3ed', backgroundColor: 'transparent', fill: false, tension: .38, borderWidth: 2, pointRadius: 3, pointHoverRadius: 5, yAxisID: 'temperature' },
                    { type: 'bar', label: 'Rain mm', data: rainfall, backgroundColor: 'rgba(49,130,206,.42)', borderColor: '#3182ce', borderWidth: 1, borderRadius: 6, maxBarThickness: 18, yAxisID: 'rainfall' }
                ]},
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 18, color: '#486581', font: { family: 'system-ui', size: 12, weight: '600' } } }, tooltip: { backgroundColor: '#102a43', padding: 12, cornerRadius: 10, displayColors: true } }, scales: { x: { grid: { display: false }, ticks: { color: '#627d98', font: { weight: '600' } } }, temperature: { position: 'left', title: { display: true, text: 'Temperature °C', color: '#627d98' }, grid: { color: 'rgba(98,125,152,.12)' }, ticks: { color: '#627d98' } }, rainfall: { position: 'right', beginAtZero: true, title: { display: true, text: 'Rainfall mm', color: '#627d98' }, grid: { drawOnChartArea: false }, ticks: { color: '#3182ce' } } }
                } }
            });
        }
        renderLocalForecast();
        */
        const map = L.map('climate-map').setView(currentLocation, 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        L.marker(currentLocation).addTo(map).bindPopup('<strong>Current search location</strong>').openPopup();

        let monitoredLocations = [];

        function searchMonitoringLocation() {
            const input = document.getElementById('monitoring-location-search');
            const status = document.getElementById('monitoring-location-search-status');
            const details = document.getElementById('monitoring-location-details');
            const query = input.value.trim().toLowerCase();
            if (!query) { status.textContent = 'Type a district or facility name to search.'; return; }
            const match = monitoredLocations.find(location => location.name.toLowerCase().includes(query));
            if (!match) { status.textContent = monitoredLocations.length ? 'No monitored location matched that search.' : 'Monitoring locations are still loading.'; return; }
            const zoom = match.type === 'facility' ? 13 : 10;
            map.flyTo([match.latitude, match.longitude], zoom, { duration: 1.1 });
            L.popup({ closeButton: true, offset: [0, -8] }).setLatLng([match.latitude, match.longitude]).setContent(`<strong>${match.name}</strong><br>${match.type}`).openOn(map);
            status.textContent = `Showing ${match.name} · ${match.type}`;
            details.innerHTML = `<strong>${match.name}</strong><br><span class="text-capitalize">${match.type}</span> · ${match.latitude.toFixed(4)}, ${match.longitude.toFixed(4)}<br><span class="text-muted">The map is zoomed to this monitoring location. Facility vulnerability overlays may use a separate readiness assessment.</span>`;
        }

        document.getElementById('monitoring-location-search-button').addEventListener('click', searchMonitoringLocation);
        document.getElementById('monitoring-location-search').addEventListener('keydown', event => { if (event.key === 'Enter') searchMonitoringLocation(); });

        fetch('{{ route('climate.locations') }}')
            .then(response => response.json())
            .then(payload => {
                const locations = payload.data || [];
                monitoredLocations = locations;
                document.getElementById('location-count').textContent = `${locations.length} monitored location${locations.length === 1 ? '' : 's'}`;
                locations.forEach(location => {
                    L.circleMarker([location.latitude, location.longitude], {
                        radius: location.type === 'facility' ? 7 : 8,
                        color: location.type === 'facility' ? '#ff3b81' : '#0d6efd',
                        fillColor: location.type === 'facility' ? '#ff3b81' : '#0d6efd',
                        fillOpacity: 0.75
                    }).addTo(map).bindPopup(`<strong>${location.name}</strong><br>${location.type}`);
                });
            })
            .catch(() => {
                document.getElementById('location-count').textContent = 'Locations unavailable';
            });

        fetch('{{ route('climate.vulnerability') }}')
            .then(response => response.json())
            .then(payload => {
                const colors = { low: '#198754', watch: '#f0ad00', warning: '#f97316', severe: '#dc3545', incomplete: '#6c757d' };
                (payload.facilities || []).forEach(facility => {
                    const level = facility.risk_level || 'incomplete';
                    L.circleMarker([facility.latitude, facility.longitude], {
                        radius: 7, color: colors[level], fillColor: colors[level], fillOpacity: .85, weight: 2
                    }).addTo(map).bindPopup(`<strong>${facility.name}</strong><br>Vulnerability: ${level}<br>${facility.score === null ? 'Awaiting verified inputs' : 'Score: '+facility.score}`);
                });
            })
            .catch(() => {});

        const nationalMap = L.map('national-risk-map').setView([1.2, 32.3], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(nationalMap);
        Promise.all([
            fetch('https://unosat-geodrr.cern.ch/data/rest/services/Hosted/UGA_District_Boundary/FeatureServer/23/query?where=1%3D1&outFields=admin2name_en,admin2pcode&returnGeometry=true&outSR=4326&f=geojson').then(response => response.json()),
            fetch('{{ route('climate.district-risk') }}').then(response => response.json())
        ]).then(([geojson, districtRisk]) => {
            const districts = Object.fromEntries((districtRisk.districts || []).map(district => [district.external_id, district]));
            L.geoJSON(geojson, {
                style: feature => {
                    const district = districts[feature.properties?.admin2pcode];
                    const color = district?.risk_level === 'severe' ? '#1e3a8a' : district?.risk_level === 'warning' ? '#2563eb' : district?.risk_level === 'watch' ? '#60a5fa' : '#bfdbfe';
                    return { color: '#52606d', weight: 1, fillColor: color, fillOpacity: district?.status === 'available' ? .52 : 0, opacity: .8 };
                },
                onEachFeature: (feature, layer) => {
                    const district = districts[feature.properties?.admin2pcode];
                    layer.bindTooltip(feature.properties?.admin2name_en || 'District');
                    if (district?.status === 'available') layer.bindPopup(`<strong>${district.name}</strong><br>CHIRPS anomaly: ${district.anomaly_percent}%<br>Risk: ${district.risk_level}`);
                }
            }).addTo(nationalMap);
        }).catch(() => {});
        const riskPalettes = {
            heat: { low: '#198754', watch: '#f0ad00', warning: '#f97316', severe: '#dc3545' },
            flood: { low: '#bfdbfe', watch: '#60a5fa', warning: '#2563eb', severe: '#1e3a8a' }
        };
        let riskCells = [], riskMode = 'heat', riskData;

        function drawRiskLayer() {
            riskCells.forEach(layer => nationalMap.removeLayer(layer));
            riskCells = (riskData?.cells || []).map(cell => {
                const level = riskMode === 'heat' ? cell.heat_level : cell.flood_level;
                const color = riskPalettes[riskMode][level];
                const cellSize = .5;
                return L.rectangle([
                    [cell.latitude - cellSize, cell.longitude - cellSize],
                    [cell.latitude + cellSize, cell.longitude + cellSize]
                ], {
                    color: color, fillColor: color, fillOpacity: .48, weight: 1
                }).addTo(nationalMap).bindPopup(`<strong>${riskMode === 'heat' ? 'Heat' : 'Heavy rain'} screening area</strong><br>${level}<br>${cell.latitude.toFixed(1)}°, ${cell.longitude.toFixed(1)}°`);
            });
            document.querySelectorAll('.legend-dot').forEach(dot => { dot.style.backgroundColor = riskPalettes[riskMode][dot.dataset.level]; });
        }

        let nationalHeatLayer = null, rainShowerLayers = [];

        function renderRiskDetails(cell = null, label = 'National overview') {
            const panel = document.getElementById('national-risk-details');
            if (!panel) return;
            const cells = riskData?.cells || [];
            const selected = cell || cells.reduce((best, candidate) => Number(candidate.temperature) > Number(best?.temperature ?? -Infinity) ? candidate : best, null);
            if (!selected) { panel.textContent = 'Risk details are temporarily unavailable.'; return; }
            const rain = Number(selected.rainfall);
            const temperature = Number(selected.temperature);
            panel.innerHTML = `<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-3"><div><div class="risk-label">Selected area</div><strong>${label}</strong></div><div class="small text-md-end">${riskMode === 'heat' ? 'Heat screening' : 'Heavy-rain screening'} · ${riskData.available ? 'Available' : 'Unavailable'}</div></div><div class="row g-3"><div class="col-6 col-md-3"><div class="risk-label">Feels like</div><div class="risk-value">${Number.isFinite(temperature) ? temperature.toFixed(1) : '—'} °C</div><div class="small">${selected.heat_level || 'No level'}</div></div><div class="col-6 col-md-3"><div class="risk-label">Rainfall</div><div class="risk-value">${Number.isFinite(rain) ? rain.toFixed(1) : '—'} mm</div><div class="small">${selected.flood_level || 'No level'}</div></div><div class="col-6 col-md-3"><div class="risk-label">Heat action</div><div class="small">${selected.heat_level === 'severe' || selected.heat_level === 'warning' ? 'Review heat precautions' : 'Routine monitoring'}</div></div><div class="col-6 col-md-3"><div class="risk-label">Rain action</div><div class="small">${selected.flood_level === 'severe' || selected.flood_level === 'warning' ? 'Check drainage and access' : 'Routine monitoring'}</div></div></div>`;
        }

        function drawRiskHeatLayer() {
            if (nationalHeatLayer) nationalMap.removeLayer(nationalHeatLayer);
            rainShowerLayers.forEach(layer => nationalMap.removeLayer(layer));
            rainShowerLayers = [];
            const cells = riskData?.cells || [];
            const heatMode = riskMode === 'heat';
            const valueFor = cell => heatMode ? Number(cell.temperature) : Number(cell.rainfall);
            const intensityFor = cell => heatMode
                ? Math.min(1, Math.max(.08, (valueFor(cell) - 24) / 10))
                : Math.min(1, Math.max(.08, valueFor(cell) / 80));
            const points = cells.map(cell => [cell.latitude, cell.longitude, intensityFor(cell)]);
            const gradient = heatMode
                ? { 0.2: '#2c7bb6', 0.4: '#00a6ca', 0.6: '#ffff8c', 0.8: '#fdae61', 1: '#d7191c' }
                : { 0.2: '#dff6ff', 0.45: '#73c8ff', 0.68: '#2389da', 0.84: '#1355b5', 1: '#081f5c' };
            const legendColors = heatMode ? ['#2c7bb6', '#ffff8c', '#fdae61', '#d7191c'] : ['#dff6ff', '#73c8ff', '#2389da', '#081f5c'];
            document.querySelectorAll('.legend-dot').forEach((dot, index) => { dot.style.backgroundColor = legendColors[index]; });
            if (typeof L.heatLayer === 'function' && points.length) {
                nationalHeatLayer = L.heatLayer(points, { radius: 45, blur: 32, maxZoom: 7, minOpacity: .42, gradient }).addTo(nationalMap);
            }
            if (!heatMode) {
                cells.filter(cell => Number(cell.rainfall) >= 50).forEach(cell => {
                    const shower = L.marker([cell.latitude, cell.longitude], {
                        interactive: false,
                        icon: L.divIcon({ className: 'rain-shower-marker', iconSize: [44, 44], iconAnchor: [22, 34], html: '<div class="rain-shower-icon"><span class="rain-cloud">☁</span><span class="rain-drops">⋮⋮</span></div>' })
                    }).addTo(nationalMap);
                    rainShowerLayers.push(shower);
                });
            }
            /* Cell markers intentionally omitted: the heat surface should remain clean. 
            cells.forEach(cell => {
                const level = heatMode ? cell.heat_level : cell.flood_level;
                const marker = L.circleMarker([cell.latitude, cell.longitude], {
                    radius: 4,
                    color: heatMode ? '#ff3b81' : '#00c8ff',
                    fillColor: heatMode ? '#ff3b81' : '#00c8ff',
                    fillOpacity: .7,
                    weight: 1
                }).addTo(nationalMap).bindPopup(`<strong>${heatMode ? 'Heat' : 'Heavy rain'} screening point</strong><br>Level: ${level}<br>${heatMode ? 'Apparent temperature' : 'Rainfall'}: ${valueFor(cell).toFixed(1)} ${heatMode ? '°C' : 'mm'}`);
                nationalRiskMarkers.push(marker);
            }); */
        }

        function searchNationalLocation() {
            const input = document.getElementById('national-risk-search');
            const status = document.getElementById('national-risk-search-status');
            const query = input.value.trim().toLowerCase();
            if (!query) { status.textContent = 'Type a district or facility name to search.'; return; }
            const match = monitoredLocations.find(location => location.name.toLowerCase().includes(query));
            if (!match) { status.textContent = 'No monitored district or facility matched that search.'; return; }
            nationalMap.flyTo([match.latitude, match.longitude], 11, { duration: 1.1 });
            L.popup({ closeButton: true, offset: [0, -8] }).setLatLng([match.latitude, match.longitude]).setContent(`<strong>${match.name}</strong><br>${match.type}`).openOn(nationalMap);
            const nearest = (riskData?.cells || []).reduce((best, cell) => {
                const distance = ((cell.latitude - match.latitude) ** 2) + ((cell.longitude - match.longitude) ** 2);
                return !best || distance < best.distance ? { cell, distance } : best;
            }, null);
            renderRiskDetails(nearest?.cell || null, match.name);
            status.textContent = `Showing ${match.name} · ${match.type}`;
        }

        document.getElementById('national-risk-search-button').addEventListener('click', searchNationalLocation);
        document.getElementById('national-risk-search').addEventListener('keydown', event => { if (event.key === 'Enter') searchNationalLocation(); });

        function renderTrendChart() {
            if (!window.Chart || !riskData) return;
            const context = document.getElementById('national-trend-chart');
            Chart.defaults.font.family = 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#8be9fd';
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.padding = 18;
            Chart.defaults.plugins.tooltip.backgroundColor = '#06111f';
            Chart.defaults.plugins.tooltip.titleColor = '#00f5ff';
            Chart.defaults.plugins.tooltip.bodyColor = '#f8fbff';
            Chart.defaults.plugins.tooltip.padding = 12;
            Chart.defaults.plugins.tooltip.cornerRadius = 10;
            const temperatureValues = riskData.trends.map(item => Number(item.temperature));
            const rainfallValues = riskData.trends.map(item => Number(item.rainfall));
            const predictionStart = riskData.trends.findIndex(item => item.is_prediction === true);
            const extensionStart = predictionStart > 0 ? predictionStart - 1 : Math.max(0, temperatureValues.length - 1);
            const baselineTemperature = temperatureValues[extensionStart];
            const baselineRainfall = rainfallValues[extensionStart];
            const predictedTemperature = temperatureValues[predictionStart >= 0 ? predictionStart : extensionStart];
            const predictedRainfall = rainfallValues[predictionStart >= 0 ? predictionStart : extensionStart];
            const temperatureRise = Number.isFinite(predictedTemperature) && Number.isFinite(baselineTemperature) ? predictedTemperature >= baselineTemperature : true;
            const rainfallRise = Number.isFinite(predictedRainfall) && Number.isFinite(baselineRainfall) ? predictedRainfall >= baselineRainfall : true;
            const directionValues = temperatureValues.map((value, index) => index < extensionStart ? null : value);
            const rainfallDirectionValues = rainfallValues.map((value, index) => index < extensionStart ? null : value);
            const trendChart = new Chart(context, {
                type: 'line',
                data: { labels: riskData.trends.map(item => item.date), datasets: [
                    { label: 'Average apparent temperature (°C)', data: riskData.trends.map(item => item.temperature), borderColor: '#dc3545', backgroundColor: '#dc354522', tension: .35, yAxisID: 'temperature' },
                    { label: 'Average rainfall (mm)', data: riskData.trends.map(item => item.rainfall), borderColor: '#0d6efd', backgroundColor: '#0d6efd22', tension: .35, yAxisID: 'rainfall' }
                ]},
                options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { temperature: { position: 'left', title: { display: true, text: '°C' } }, rainfall: { position: 'right', title: { display: true, text: 'mm' }, grid: { drawOnChartArea: false } } }, plugins: { legend: { position: 'bottom' } } }
            });
            trendChart.data.datasets[0].data = temperatureValues.map((value, index) => index <= extensionStart ? value : null);
            trendChart.data.datasets[1].data = rainfallValues.map((value, index) => index <= extensionStart ? value : null);
            trendChart.data.datasets[0].borderColor = '#ff3b81';
            trendChart.data.datasets[0].backgroundColor = 'rgba(255, 59, 129, .16)';
            trendChart.data.datasets[0].borderWidth = 3;
            trendChart.data.datasets[0].pointBackgroundColor = '#071522';
            trendChart.data.datasets[0].pointBorderColor = '#ff3b81';
            trendChart.data.datasets[0].pointBorderWidth = 2;
            trendChart.data.datasets[1].borderColor = '#00c8ff';
            trendChart.data.datasets[1].backgroundColor = 'rgba(0, 200, 255, .14)';
            trendChart.data.datasets[1].borderWidth = 2;
            trendChart.data.datasets[1].pointBackgroundColor = '#071522';
            trendChart.data.datasets[1].pointBorderColor = '#00c8ff';
            trendChart.data.datasets.push({ label: temperatureRise ? 'Next 2 days · predicted rise' : 'Next 2 days · predicted fall', data: directionValues, borderColor: temperatureRise ? '#ffe66d' : '#b388ff', borderDash: [6, 6], borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: false, tension: .35, yAxisID: 'temperature' });
            trendChart.data.datasets.push({ label: rainfallRise ? 'Next 2 days rainfall · predicted rise' : 'Next 2 days rainfall · predicted fall', data: rainfallDirectionValues, borderColor: '#7df9ff', borderDash: [4, 7], borderWidth: 2, pointRadius: 0, pointHoverRadius: 4, fill: false, tension: .35, yAxisID: 'rainfall' });
            trendChart.options.scales.temperature.ticks.color = '#ff8fb3';
            trendChart.options.scales.temperature.title.color = '#ff8fb3';
            trendChart.options.scales.rainfall.ticks.color = '#00c8ff';
            trendChart.options.scales.rainfall.title.color = '#00c8ff';
            trendChart.options.scales.temperature.grid = { color: 'rgba(255, 59, 129, .12)' };
            trendChart.update();
        }

        fetch('{{ route('climate.national-risk') }}')
            .then(response => response.json())
            .then(payload => {
                riskData = payload;
                renderRiskDetails();
                document.getElementById('national-risk-status').textContent = payload.available ? `${payload.cells.length} national screening cells · refreshed every 15 minutes` : 'National screening data is temporarily unavailable.';
                drawRiskHeatLayer();
                renderTrendChart();
            })
            .catch(() => { document.getElementById('national-risk-status').textContent = 'National screening data is temporarily unavailable.'; });

        fetch('{{ route('climate.pipeline-status') }}')
            .then(response => response.json())
            .then(status => {
                document.getElementById('district-count').textContent = status.districts ?? '—';
                document.getElementById('facility-count').textContent = status.facilities ?? '—';
                document.getElementById('chirps-queued').textContent = (status.chirps?.queued || 0) + (status.chirps?.processing || 0);
                document.getElementById('chirps-complete').textContent = status.chirps?.complete || 0;
                document.getElementById('pipeline-updated').textContent = 'Updated just now';
            })
            .catch(() => { document.getElementById('pipeline-updated').textContent = 'Status unavailable'; });

        document.getElementById('heat-layer-button').addEventListener('click', function () { riskMode = 'heat'; this.classList.add('active'); document.getElementById('flood-layer-button').classList.remove('active'); drawRiskHeatLayer(); renderRiskDetails(); });
        document.getElementById('flood-layer-button').addEventListener('click', function () { riskMode = 'flood'; this.classList.add('active'); document.getElementById('heat-layer-button').classList.remove('active'); drawRiskHeatLayer(); renderRiskDetails(); });
    </script>
</body>
</html>
