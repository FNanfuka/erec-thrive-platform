@extends('layouts.thrive')
@section('title', 'National Risk Map | THRIVE')
@section('head')<style>#national-map{height:clamp(430px,70vh,720px);border-radius:1rem}.legend span{margin-right:1rem;font-size:.85rem}.dot{display:inline-block;width:.7rem;height:.7rem;border-radius:50%;margin-right:.25rem}</style>@endsection
@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4"><div><p class="eyebrow mb-1">NATIONAL VIEW</p><h1 class="h2 mb-1">Uganda risk map</h1><p class="text-muted mb-0">Explore heat and heavy-rain screening across the country.</p></div><a href="{{ route('home') }}" class="btn btn-outline-primary align-self-start">New location</a></div>
<section class="card border-0 shadow-sm"><div class="card-body p-2 p-md-3"><div id="national-map"></div><div class="legend p-3"><span><i class="dot" style="background:#bfdbfe"></i>Low</span><span><i class="dot" style="background:#60a5fa"></i>Watch</span><span><i class="dot" style="background:#2563eb"></i>Warning</span><span><i class="dot" style="background:#1e3a8a"></i>Severe</span></div><p id="map-status" class="small text-muted px-3 mb-2">Loading risk data…</p></div></section>
@endsection
@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><script>
const map=L.map('national-map').setView([1.2,32.3],6);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors'}).addTo(map);const colors={low:'#bfdbfe',watch:'#60a5fa',warning:'#2563eb',severe:'#1e3a8a'};
Promise.all([fetch('{{ route('climate.national-risk') }}').then(r=>r.json()),fetch('https://unosat-geodrr.cern.ch/data/rest/services/Hosted/UGA_District_Boundary/FeatureServer/23/query?where=1%3D1&outFields=admin2name_en,admin2pcode&returnGeometry=true&outSR=4326&f=geojson').then(r=>r.json())]).then(([risk,geo])=>{(risk.cells||[]).forEach(c=>{let s=.5;L.rectangle([[c.latitude-s,c.longitude-s],[c.latitude+s,c.longitude+s]],{color:colors[c.flood_level],fillColor:colors[c.flood_level],fillOpacity:.5,weight:1}).addTo(map).bindPopup(`<strong>Heavy rain screening</strong><br>${c.flood_level}`)});L.geoJSON(geo,{style:{color:'#52606d',weight:1,fill:false}}).addTo(map);document.getElementById('map-status').textContent=`${risk.cells?.length||0} national screening cells · official district boundaries overlaid`}).catch(()=>document.getElementById('map-status').textContent='Risk data is temporarily unavailable.');
</script>
@endsection
