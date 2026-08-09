@extends('layouts.thrive')
@section('title', 'Facilities | THRIVE')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><p class="eyebrow mb-1">HEALTHCARE READINESS</p><h1 class="h2 mb-1">Facilities and response readiness</h1><p class="text-muted mb-0">Review exposure, missing inputs, and the next preparedness action.</p></div><a href="{{ route('dashboard.alerts') }}" class="btn btn-outline-primary">Open active alerts</a></div>
<div id="facility-grid" class="row g-3"><div class="col-12 text-muted">Loading facilities...</div></div>
@endsection
@section('scripts')
<script>
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
fetch('{{ route('climate.vulnerability') }}').then(r=>r.json()).then(data=>{const grid=document.getElementById('facility-grid');if(!data.facilities?.length){grid.innerHTML='<div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body"><strong>No facility records loaded yet.</strong><p class="small text-muted mb-0 mt-1">Import an approved national registry. Candidate OpenStreetMap records require verification.</p></div></div></div>';return}grid.innerHTML=data.facilities.map(f=>`<div class="col-12 col-md-6 col-xl-4"><article class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between gap-2"><h2 class="h6">${esc(f.name)}</h2><span class="badge text-bg-${f.risk_level==='incomplete'?'secondary':f.risk_level==='severe'?'danger':f.risk_level==='warning'?'warning':'success'}">${esc(f.risk_level)}</span></div><p class="small text-muted mb-1">${f.score===null?'Awaiting verified inputs':'Screening score: '+esc(f.score)}</p><div class="small text-muted mb-3">${esc(f.registry_status)} registry · ${esc(f.source)}</div><h3 class="h6">Next preparedness action</h3><ul class="small mb-0">${(f.recommended_actions||[]).map(action=>`<li>${esc(action)}</li>`).join('')}</ul>${f.missing_inputs?.length?`<div class="small text-warning mt-3">Missing: ${esc(f.missing_inputs.join(', '))}</div>`:''}</div></article></div>`).join('')}).catch(()=>document.getElementById('facility-grid').innerHTML='<div class="col-12 text-danger">Facility data is temporarily unavailable.</div>');
</script>
@endsection
