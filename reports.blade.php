@extends('layouts.thrive')
@section('title', 'Pilot Reports | THRIVE')
@section('content')
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div><p class="eyebrow mb-1">PILOT EVIDENCE</p><h1 class="h2 mb-1">Pilot evidence report</h1><p class="text-muted mb-0">Aggregate operational evidence for climate-health early action.</p></div>
    <div class="d-flex align-items-center gap-2"><label for="period" class="small text-muted">Reporting period</label><select id="period" class="form-select" style="width:auto"><option value="7">Last 7 days</option><option value="30" selected>Last 30 days</option><option value="90">Last 90 days</option><option value="365">Last 12 months</option></select></div>
</div>
<div id="report-status" class="small text-muted mb-3">Loading report...</div>
<div id="report-cards" class="row g-3 mb-4"></div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div><p class="eyebrow mb-1">CLIMATE → HEALTH LINKAGE</p><h2 class="h5 mb-1">Aggregate child-health outcomes</h2><p class="small text-muted mb-0">Pilot evidence is recorded by location, indicator, age group, and reporting period only.</p></div><div id="health-linkage" class="text-end text-muted">Loading...</div></div></div></div>
<div class="row g-4">
    <div class="col-12 col-xl-7"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><p class="eyebrow mb-1">RESPONSE LOG</p><h2 class="h5 mb-0">Alerts by hazard</h2></div><span class="badge text-bg-light">Aggregate only</span></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Hazard</th><th>Total</th><th>Active</th><th>Resolved</th><th>Completed actions</th></tr></thead><tbody id="hazard-rows"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody></table></div></div></div></div>
    <div class="col-12 col-xl-5"><div class="card border-0 shadow-sm h-100"><div class="card-body"><p class="eyebrow mb-1">FACILITY READINESS</p><h2 class="h5 mb-3">Pilot coverage</h2><div id="facility-summary" class="row g-3"><div class="col-12 text-muted">Loading...</div></div></div></div></div>
    <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex justify-content-between align-items-center mb-3"><div><p class="eyebrow mb-1">TRUST AND PROVENANCE</p><h2 class="h5 mb-0">Data-source freshness</h2></div><span class="small text-muted">Observed signals in selected period</span></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Source</th><th>Observations</th><th>Last observed</th><th>Freshness</th><th>Status</th></tr></thead><tbody id="source-rows"><tr><td colspan="5" class="text-muted">Loading...</td></tr></tbody></table></div></div></div></div>
    <div class="col-12"><div class="card border-0 shadow-sm" style="border-left:5px solid var(--gold)!important"><div class="card-body"><p class="eyebrow mb-1">WHAT THE PILOT STILL NEEDS</p><h2 class="h5 mb-1">Evidence gaps</h2><p class="small text-muted">These are implementation gaps to close before claiming health impact. No child-level personal data is shown here.</p><div id="gap-list" class="row g-3"></div></div></div></div>
</div>
@endsection
@section('scripts')
<script>
const esc = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
const number = value => Number(value || 0).toLocaleString();
const date = value => value ? new Date(value).toLocaleString([], {dateStyle:'medium', timeStyle:'short'}) : 'No observation';
const badge = status => `<span class="badge rounded-pill ${status === 'fresh' ? 'text-bg-success' : status === 'unavailable' ? 'text-bg-secondary' : 'text-bg-warning'}">${esc(status)}</span>`;

async function loadReport() {
    const days = document.getElementById('period').value;
    const status = document.getElementById('report-status');
    status.textContent = 'Loading report...';
    try {
        const response = await fetch(`{{ route('climate.pilot-report') }}?days=${days}`);
        if (!response.ok) throw new Error('Report unavailable');
        const report = await response.json();
        const o = report.overview || {}, f = report.facilities || {};
        document.getElementById('report-cards').innerHTML = [
            ['Alerts', number(o.alerts_total), `${number(o.alerts_active)} active · ${number(o.alerts_resolved)} resolved`, 'var(--forest)'],
            ['Actions completed', number(o.actions_completed), `${number(o.actions_recorded)} actions recorded`, 'var(--green)'],
            ['Completion rate', `${o.action_completion_rate || 0}%`, `${o.response_coverage || 0}% of alerts have a response record`, 'var(--gold)'],
            ['Facilities represented', number(f.total), `${f.readiness_rate || 0}% assessment readiness`, '#8b5e34']
        ].map(card => `<div class="col-12 col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">${card[0]}</small><div class="display-6 fw-semibold" style="color:${card[3]}">${card[1]}</div><p class="small text-muted mb-0">${card[2]}</p></div></div></div>`).join('');
        document.getElementById('hazard-rows').innerHTML = (report.hazards || []).length ? report.hazards.map(row => `<tr><td class="text-capitalize fw-semibold">${esc(row.hazard)}</td><td>${number(row.total)}</td><td>${number(row.active)}</td><td>${number(row.resolved)}</td><td>${number(row.actions_completed)}</td></tr>`).join('') : '<tr><td colspan="5" class="text-muted">No alerts recorded in this period.</td></tr>';
        document.getElementById('facility-summary').innerHTML = [['Verified', f.verified], ['Candidate', f.candidate], ['Incomplete', f.incomplete_assessments], ['High risk', f.high_risk]].map(item => `<div class="col-6"><small class="text-muted">${item[0]}</small><div class="h4 mb-0">${number(item[1])}</div></div>`).join('');
        const health = report.health_outcomes || {};
        document.getElementById('health-linkage').innerHTML = `<span class="badge ${health.status === 'connected' ? 'text-bg-success' : 'text-bg-warning'}">${health.status === 'connected' ? 'Connected' : 'Not connected'}</span><div class="small mt-2">${number(health.records)} records · ${number(health.locations)} locations</div><div class="small text-muted">${(health.indicators || []).map(esc).join(' · ') || 'Add aggregate outcomes to demonstrate impact.'}</div>`;
        document.getElementById('source-rows').innerHTML = (report.data_sources || []).length ? report.data_sources.map(row => `<tr><td class="fw-semibold">${esc(row.source)}</td><td>${number(row.observations)}</td><td>${date(row.latest_observed_at)}</td><td>${row.freshness_hours === null ? '—' : `${number(row.freshness_hours)}h ago`}</td><td>${badge(row.status)}</td></tr>`).join('') : '<tr><td colspan="5" class="text-muted">No observations recorded in this period.</td></tr>';
        document.getElementById('gap-list').innerHTML = (report.evidence_gaps || []).map(gap => `<div class="col-12 col-md-6"><div class="p-3 rounded-3" style="background:#fbf7e8"><div class="fw-semibold">${esc(gap.title)}</div><div class="small text-muted mt-1">${esc(gap.detail)}</div></div></div>`).join('');
        status.textContent = `Showing ${report.period.from} to ${report.period.to} · Generated ${date(report.period.generated_at)}`;
    } catch (error) {
        status.className = 'small text-danger mb-3';
        status.textContent = 'The pilot report is temporarily unavailable.';
    }
}
document.getElementById('period').addEventListener('change', loadReport);
loadReport();
</script>
@endsection
