# EREC THRIVE Intelligence System

**Eco Reset Edge Connect (EREC)** | Kampala, Uganda | [ecoresetedge.org](https://ecoresetedge.org)

> An open-source, AI-powered climate-health data platform that translates environmental risk data into actionable intelligence for frontline health workers, local governments, and humanitarian actors in Uganda's most climate-vulnerable communities.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Python](https://img.shields.io/badge/Python-3.9+-blue.svg)](https://python.org)
[![Open Source](https://img.shields.io/badge/Open%20Source-Yes-brightgreen.svg)]()
[![UNICEF Venture Fund](https://img.shields.io/badge/UNICEF-Venture%20Fund%20Applicant-00AEEF.svg)]()

---

## The Problem

Uganda's climate-vulnerable communities — including children in refugee settlements, urban informal settlements, and rural districts — face compounding climate and health risks with **no accessible, real-time data systems** to guide local decision-making.

- Over **76% of young Ugandans** reported climate-disrupted livelihoods in 2025
- Health facilities **lack tools** to anticipate climate-driven disease surges
- Local governments **operate without** AI-ready vulnerability data linking environmental exposure to child health outcomes
- Children and adolescents bear the greatest burden

## The Solution

The **THRIVE Intelligence System** uses machine learning to:

1. **Map** environmental risk factors (flood exposure, air quality, heat stress, disease vectors) against community health data
2. **Generate** vulnerability scores for communities and health facilities
3. **Translate** complex multi-source data into **localized, actionable alerts** for frontline health workers and local governments
4. **Expose** publicly accessible real-time dashboards to enable community-level anticipatory action

### Target Areas (4 UNICEF Climate-Health Pillars)

| Pillar | THRIVE Approach |
|--------|----------------|
| Strategic Planning | AI-driven vulnerability mapping and resource optimization |
| Early Warning, Early Action | Hyper-local climate-health risk alerts |
| Healthcare Readiness | Predictive disease forecasting for malaria, heat illness |
| Point-of-Care Support | Offline-capable dashboards for community health workers |

---

## Architecture

```
Climate Data Sources          Community Health Data
(Weather, Air Quality,   →    (DHIS2, Kobo Toolbox,    →   ML Risk Models   →   Open Dashboard
 Flood Maps, Satellite)        THRIVE Programme Data)                              & Alerts API
```

### Key Components

```
erec-thrive-platform/
├── src/
│   ├── ingestion/        # Data pipeline — climate + health data collection
│   ├── models/           # ML models — vulnerability scoring, disease forecasting
│   ├── dashboard/        # Open data dashboard — Flask + Leaflet.js
│   └── utils/            # Shared utilities, data cleaning, config
├── notebooks/            # Exploratory analysis and model development
├── data/
│   ├── sample/           # Sample datasets for testing (anonymized)
│   ├── raw/              # Raw ingested data (gitignored in production)
│   └── processed/        # Cleaned, feature-engineered datasets
├── docs/                 # Platform documentation
└── tests/                # Unit and integration tests
```

---

## Quick Start

### Prerequisites

```bash
Python 3.9+
pip install -r requirements.txt
```

### Installation

```bash
git clone https://github.com/FNanfuka/erec-thrive-platform.git
cd erec-thrive-platform
pip install -r requirements.txt
```

### Run the vulnerability mapping demo

```bash
cd notebooks
jupyter notebook 01_climate_health_risk_mapping.ipynb
```

### Launch the dashboard (development)

```bash
cd src/dashboard
python app.py
# Open http://localhost:5000
```

---

## Data Sources

The platform integrates openly available datasets:

| Source | Data Type | Coverage |
|--------|-----------|----------|
| [NASA POWER](https://power.larc.nasa.gov/) | Temperature, rainfall, humidity | Uganda-wide |
| [OpenAQ](https://openaq.org/) | Air quality (PM2.5, PM10) | Kampala, Lira |
| [CHIRPS](https://www.chc.ucsb.edu/data/chirps) | Precipitation anomalies | East Africa |
| [Uganda DHIS2](https://dhis2.org/) | Disease incidence, health facility data | District level |
| [OpenStreetMap](https://openstreetmap.org/) | Health facility locations, roads | Uganda |
| [UNHCR Data](https://data.unhcr.org/) | Refugee settlement populations | Uganda settlements |

---

## ML Models

### 1. Climate-Health Vulnerability Scoring
- **Input:** Environmental indicators + demographic data + health facility proximity
- **Output:** Community vulnerability score (0–100) with risk category
- **Algorithm:** Random Forest Classifier with SHAP explainability
- **Status:** Prototype — trained on Kampala urban dataset

### 2. Disease Outbreak Forecasting
- **Input:** Climate anomalies (rainfall, temperature) + historical malaria/disease incidence
- **Output:** 4-week disease surge probability by district
- **Algorithm:** Time-series LSTM + XGBoost ensemble
- **Status:** In development

### 3. Early Warning Alert Engine
- **Input:** Real-time weather feeds + vulnerability scores
- **Output:** Tiered alerts (green/amber/red) by community zone
- **Algorithm:** Rule-based threshold system + anomaly detection
- **Status:** Prototype

---

## Community Grounding — THRIVE Programme

The platform is built on and tested through EREC's active **THRIVE community programme**, which works with **300+ adolescents and youth** across climate-vulnerable Ugandan communities in Kampala and Lira.

This provides:
- A **real-world pilot environment** from day one
- **Community-validated problem framing** and user feedback loops
- **Field data collection** capacity through trained community facilitators
- **Direct integration** with local health facilities and local government structures

---

## Open Source Commitment

This platform is released under the **MIT License** and is committed to being a **Digital Public Good**.

All code, models, and documentation are freely available for:
- Reuse by national governments, NGOs, and community organisations
- Adaptation to other climate-vulnerable contexts beyond Uganda
- Contribution from the open-source community

We follow [UNICEF's Principles for Digital Development](https://digitalprinciples.org/).

---

## Roadmap

| Phase | Timeline | Milestone |
|-------|----------|-----------|
| Foundation | Month 1–2 | Platform architecture, repo setup, needs assessment |
| Core Development | Month 3–4 | Risk mapping module, data pipeline integration |
| Beta Pilot | Month 5–6 | Deployment with community health workers in Kampala + Lira |
| Integration | Month 7–8 | DHIS2 + Kobo Toolbox integration, open dashboard launch |
| Evaluation | Month 9–10 | Formal pilot assessment, partner co-design |
| Open Release | Month 11–12 | Full open-source release, documentation, scale-up planning |

---

## About EREC

**Eco Reset Edge Connect (EREC)** is a youth- and women-centered climate innovation initiative working at the intersection of climate resilience, community health, and digital data systems in Uganda.

**Founder:** Nanfuka Fatuma — Data Scientist, MERL practitioner, MSc candidate in Data Science & Analytics (Uganda Christian University). Currently MERL Coordinator at Plan International Uganda with field experience across refugee response, GBV programming, and humanitarian M&E in Kyangwali, Kyaka II, Nakivale, and Oruchinga settlements.

**Published work:**
- [Grassroots groups in Uganda are keeping GBV services going despite the cuts](https://www.thenewhumanitarian.org/) — The New Humanitarian, July 2025
- [The Youth-Led Container Garden Movement Tackling Child Hunger in Uganda](https://triplepundit.com/2025/school-hunger-uganda-kanyanya-youth-urban-oasis/) — Triple Pundit, November 2025

---

## Contributing

We welcome contributions from data scientists, public health practitioners, and developers. See [CONTRIBUTING.md](docs/CONTRIBUTING.md) for guidelines.

## License

MIT License — see [LICENSE](LICENSE) for details.

## Contact

- **Email:** ecoresetedge@gmail.com
- **Website:** [ecoresetedge.org](https://ecoresetedge.com)
- **GitHub:** [@FNanfuka](https://github.com/FNanfuka)
