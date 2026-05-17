"""
EREC THRIVE Intelligence System
Open Data Dashboard — Flask API + Leaflet.js frontend

Serves vulnerability scores and climate-health alerts as:
- Interactive map dashboard (browser)
- REST API (for integration with DHIS2, Kobo Toolbox, etc.)

Author: Nanfuka Fatuma | Eco Reset Edge Connect
License: MIT
"""

from flask import Flask, jsonify, render_template_string, request
from flask_cors import CORS
import pandas as pd
import numpy as np
import json
from datetime import datetime
import sys
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent))
from models.vulnerability_scorer import VulnerabilityScorer, generate_sample_data

app = Flask(__name__)
CORS(app)  # enable cross-origin for DHIS2/Kobo integration

# Load model and generate demo data on startup
scorer = VulnerabilityScorer()
_demo_df = generate_sample_data(n_communities=200)
_labels = (
    (_demo_df["flood_risk_score"] > 6)
    | (_demo_df["malaria_incidence_rate"] > 100)
    | (_demo_df["poverty_rate"] > 50)
).astype(int)
scorer.fit(_demo_df, _labels)
_scored_df = scorer.score(_demo_df)


DASHBOARD_HTML = """
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EREC THRIVE Intelligence System</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f9fa; }
    header { background: #1a5276; color: white; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem; }
    header h1 { font-size: 1.1rem; font-weight: 600; }
    header p { font-size: 0.8rem; opacity: 0.8; }
    .badge { background: #28b463; font-size: 0.7rem; padding: 2px 8px; border-radius: 12px; }
    .layout { display: flex; height: calc(100vh - 70px); }
    #map { flex: 1; }
    .sidebar { width: 300px; background: white; border-left: 1px solid #dee2e6; overflow-y: auto; padding: 1rem; }
    .sidebar h2 { font-size: 0.85rem; font-weight: 600; color: #495057; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem; }
    .stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem; }
    .stat { background: #f8f9fa; border-radius: 6px; padding: 0.6rem; text-align: center; }
    .stat .num { font-size: 1.4rem; font-weight: 700; }
    .stat .label { font-size: 0.7rem; color: #6c757d; }
    .critical .num { color: #c0392b; }
    .high .num { color: #e67e22; }
    .moderate .num { color: #f1c40f; }
    .low .num { color: #27ae60; }
    .community-card { border: 1px solid #dee2e6; border-radius: 6px; padding: 0.6rem; margin-bottom: 0.5rem; cursor: pointer; }
    .community-card:hover { background: #f8f9fa; }
    .community-card .name { font-size: 0.85rem; font-weight: 600; }
    .community-card .meta { font-size: 0.75rem; color: #6c757d; }
    .score-bar { height: 6px; border-radius: 3px; margin-top: 0.4rem; }
    .risk-badge { font-size: 0.65rem; padding: 1px 6px; border-radius: 10px; font-weight: 600; }
    .risk-critical { background: #fadbd8; color: #c0392b; }
    .risk-high { background: #fdebd0; color: #e67e22; }
    .risk-moderate { background: #fef9e7; color: #b7950b; }
    .risk-low { background: #d5f5e3; color: #1e8449; }
    footer { background: #1a5276; color: rgba(255,255,255,0.7); font-size: 0.72rem; padding: 0.4rem 1rem; text-align: center; }
  </style>
</head>
<body>
<header>
  <div>
    <h1>EREC THRIVE Intelligence System</h1>
    <p>Climate-Health Vulnerability Dashboard &nbsp;<span class="badge">Open Source</span></p>
  </div>
</header>
<div class="layout">
  <div id="map"></div>
  <div class="sidebar">
    <h2>Summary</h2>
    <div class="stat-grid" id="stats"></div>
    <h2 style="margin-top:1rem">Highest Risk Communities</h2>
    <div id="community-list"></div>
  </div>
</div>
<footer>Eco Reset Edge Connect (EREC) | ecoresetedge.org | MIT License | Data: NASA POWER, OpenAQ, DHIS2</footer>

<script>
const RISK_COLORS = { critical: '#c0392b', high: '#e67e22', moderate: '#f39c12', low: '#27ae60' };

const map = L.map('map').setView([1.5, 32.5], 7);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

fetch('/api/communities')
  .then(r => r.json())
  .then(data => {
    renderStats(data);
    renderMap(data);
    renderList(data);
  });

function renderStats(data) {
  const counts = { critical: 0, high: 0, moderate: 0, low: 0 };
  data.forEach(d => counts[d.risk_category] = (counts[d.risk_category] || 0) + 1);
  const labels = { critical: 'Critical', high: 'High', moderate: 'Moderate', low: 'Low' };
  document.getElementById('stats').innerHTML = Object.entries(counts).map(([k, v]) =>
    `<div class="stat ${k}"><div class="num">${v}</div><div class="label">${labels[k]}</div></div>`
  ).join('');
}

function renderMap(data) {
  data.forEach(c => {
    const color = RISK_COLORS[c.risk_category] || '#999';
    const radius = 6 + c.vulnerability_score / 20;
    L.circleMarker([c.latitude, c.longitude], {
      radius, color, fillColor: color, fillOpacity: 0.7, weight: 1
    }).addTo(map).bindPopup(`
      <b>${c.community_name}</b><br>
      District: ${c.district}<br>
      Vulnerability score: <b>${c.vulnerability_score}</b><br>
      Risk: <b style="color:${color}">${c.risk_category.toUpperCase()}</b><br>
      Top factors: ${c.top_risk_factors.join(', ')}
    `);
  });
}

function renderList(data) {
  const top10 = data.sort((a, b) => b.vulnerability_score - a.vulnerability_score).slice(0, 10);
  document.getElementById('community-list').innerHTML = top10.map(c => `
    <div class="community-card">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span class="name">${c.community_name}</span>
        <span class="risk-badge risk-${c.risk_category}">${c.risk_category}</span>
      </div>
      <div class="meta">${c.district} &middot; Score: ${c.vulnerability_score}</div>
      <div class="score-bar" style="width:${c.vulnerability_score}%;background:${RISK_COLORS[c.risk_category]}"></div>
    </div>
  `).join('');
}
</script>
</body>
</html>
"""


@app.route("/")
def dashboard():
    return render_template_string(DASHBOARD_HTML)


@app.route("/api/communities")
def get_communities():
    """Return all community vulnerability scores as JSON."""
    district_filter = request.args.get("district")
    risk_filter = request.args.get("risk_category")

    df = _scored_df.copy()
    if district_filter:
        df = df[df["district"] == district_filter]
    if risk_filter:
        df = df[df["risk_category"] == risk_filter]

    records = df[[
        "community_id", "community_name", "district",
        "latitude", "longitude",
        "vulnerability_score", "risk_category", "top_risk_factors",
    ]].to_dict(orient="records")
    return jsonify(records)


@app.route("/api/alerts")
def get_alerts():
    """Return active high/critical risk alerts."""
    alerts = _scored_df[_scored_df["risk_category"].isin(["high", "critical"])].copy()
    return jsonify({
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "alert_count": len(alerts),
        "alerts": alerts[[
            "community_id", "community_name", "district",
            "vulnerability_score", "risk_category", "top_risk_factors",
        ]].to_dict(orient="records"),
    })


@app.route("/api/districts")
def get_districts():
    """Return district-level summary statistics."""
    summary = _scored_df.groupby("district").agg(
        community_count=("community_id", "count"),
        mean_vulnerability=("vulnerability_score", "mean"),
        critical_count=("risk_category", lambda x: (x == "critical").sum()),
        high_count=("risk_category", lambda x: (x == "high").sum()),
    ).round(1).reset_index()
    return jsonify(summary.to_dict(orient="records"))


@app.route("/api/health")
def health():
    """API health check — for DHIS2 integration testing."""
    return jsonify({
        "status": "ok",
        "version": "0.1.0",
        "communities_loaded": len(_scored_df),
        "model_fitted": scorer.is_fitted,
        "timestamp": datetime.utcnow().isoformat() + "Z",
    })


if __name__ == "__main__":
    print("EREC THRIVE Dashboard running at http://localhost:5000")
    app.run(debug=True, host="0.0.0.0", port=5000)
