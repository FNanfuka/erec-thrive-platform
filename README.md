# EREC THRIVE Intelligence System

**Eco Reset Edge Connect (EREC)** | Kampala, Uganda | [ecoresetedge.com](https://ecoresetedge.com)

> An open-source climate-health intelligence platform that translates environmental risk data into actionable alerts for frontline health workers, local governments, and communities in Uganda's most climate-vulnerable districts.

![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)
![PHP](https://img.shields.io/badge/PHP-Laravel-blue.svg)
![Open Source](https://img.shields.io/badge/Open%20Source-Yes-brightgreen.svg)
![UNICEF Venture Fund](https://img.shields.io/badge/UNICEF-Venture%20Fund%20Applicant-00AEEF.svg)

---

## The problem

Uganda's climate-vulnerable communities — including children in refugee settlements, urban informal settlements, and rural districts — face compounding climate and health risks with no accessible, real-time data system to guide local decision-making. Health facilities lack tools to anticipate climate-driven disease surges, and local governments operate without vulnerability data linking environmental exposure to child health outcomes.

## The solution

THRIVE monitors environmental signals across Uganda and translates them into tiered, actionable alerts for schools, health facilities, and local governments — with a distinct child-health screening layer that flags risk pathways (flooding, mosquito-borne disease, respiratory aggravation, heat stress) without overstating certainty.

The live prototype currently monitors **494 locations across 137 districts**.

## Current status (honest, as of this build)

THRIVE is a working prototype, not a finished product. We believe in being precise about what's built versus planned:

| Component | Status |
|---|---|
| Weather/heat/air-quality ingestion (Open-Meteo) | **Live** |
| Flood risk ingestion (CHIRPS v3 via ClimateSERV) | **Live** |
| Air quality connector (OpenAQ v3) | **Live** (optional, authenticated) |
| Facility vulnerability screening | **Live** — transparent weighted-formula ("screening-v1"), not yet a trained ML model |
| Rule-based alert engine (green/amber/red) | **Live**, tested |
| Child Climate Risk panel | **Live** |
| Data Health transparency view | **Live** |
| DHIS2 health-outcome integration | Coded, disabled by default — pending formal Ministry of Health / district authorisation |
| Machine learning (Random Forest w/ SHAP for vulnerability scoring) | **Planned**, not yet implemented |
| Disease-outbreak forecasting (LSTM+XGBoost) | **Planned** — "readiness state only," no live signal yet |

We would rather document this precisely than overstate progress.

## Tech stack

- **Backend:** Laravel (PHP), scheduled jobs and Artisan console commands for data ingestion
- **Frontend:** Vite-bundled assets, Leaflet.js for map rendering, Blade templates
- **Database:** Relational (SQLite for local development)
- **Testing:** Pest/PHPUnit — 21+ feature tests covering fail-safe behaviour, data-safety checks, and the alert lifecycle

## Data sources

| Source | Data type | Status |
|---|---|---|
| [Open-Meteo](https://open-meteo.com/) | Temperature, rainfall, air quality | Live |
| [CHIRPS v3](https://www.chc.ucsb.edu/data/chirps) (via ClimateSERV) | Precipitation anomalies / flood risk | Live |
| [OpenAQ](https://openaq.org/) | Air quality (PM2.5, PM10) | Live, optional |
| [OpenStreetMap](https://openstreetmap.org/) | Health facility candidate locations | Live |
| [Uganda DHIS2](https://dhis2.org/) | Aggregate health-outcome indicators | Coded, pending authorisation |

## Data safeguarding

- No personally identifiable information or individual-level children's data is collected or stored.
- Facility-level metadata (e.g. "children served") is aggregate, not individual records.
- Every derived signal is stored with source, timestamp, geographic precision, and confidence.
- The health-outcomes API explicitly rejects any child-identifying fields (enforced and tested).
- The Child Climate Risk panel is explicitly labelled as screening support — not a diagnosis, flood warning, or clinical prediction.

## Community grounding — THRIVE programme

The platform is built on and tested through EREC's active THRIVE community programme, which works with 300+ adolescents and youth across climate-vulnerable communities in Kampala and Lira. This gives THRIVE a real-world pilot environment, community-validated problem framing, and direct integration with local health facilities and government structures.

## Open source commitment

This platform is released under the **MIT License**. All code and documentation are freely available for reuse and adaptation by other governments, NGOs, and community organisations.

## About EREC

**Eco Reset Edge Connect (EREC)** is a youth- and women-centred climate innovation initiative working at the intersection of climate resilience, community health, and digital data systems in Uganda.

**Founder:** Nanfuka Fatuma — Data Scientist, MERL practitioner, MSc candidate in Data Science & Analytics (Uganda Christian University).

## Contact

- **Email:** ecoresetedge@gmail.com
- **Website:** [ecoresetedge.com](https://ecoresetedge.com)
- **GitHub:** [@FNanfuka](https://github.com/FNanfuka)

## License

MIT License — see [LICENSE](LICENSE) for details.
