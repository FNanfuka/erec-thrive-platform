# THRIVE Climate-Health Intelligence MVP

## Product direction

THRIVE is a child-centred climate-health decision-support platform for Uganda and other low-resource settings. It combines environmental hazards, vulnerability, and health signals to support early warning and early action for communities, schools, and health services.

The current Laravel prototype implements the first vertical slice: location-based weather and air-quality signals with transparent caching, graceful provider failure, and a climate-health dashboard layer.

## Four hazard tracks

| Track | Current prototype | Next data/model increment |
| --- | --- | --- |
| Air quality | Open-Meteo AQI and pollutant snapshot | OpenAQ observations and PM2.5/PM10 anomaly detection |
| Heat stress | Apparent-temperature screening indicator | Heatwave morbidity model and local vulnerability weighting |
| Flood exposure | Daily precipitation screening indicator | CHIRPS rainfall anomaly, flood exposure and facility overlays |
| Disease warning | Readiness state only | DHIS2-linked malaria forecasting using LSTM/XGBoost ensemble |

The screening indicators are deliberately labelled as prototype decision support. They are not clinical diagnoses, official warnings, or substitutes for local public-health guidance.

The first early-action slice now requests a three-day air-quality forecast and exposes the next 24-hour AQI/PM2.5 peak alongside each local assessment. Heat and air signals include audience, confidence, and recommended actions for schools, communities, and health facilities. The alert engine also creates a national heat signal when the screening grid reaches warning or severe thresholds and stores the intended audience and action window in alert metadata. These outputs remain screening signals until validated with local health outcomes.

## Proposed data flow

```text
NASA POWER / Open-Meteo / OpenAQ / CHIRPS / DHIS2 / OSM / UNHCR
                              |
                 Scheduled ingestion + validation
                              |
              Normalized observations by time and geography
                              |
        Vulnerability scoring + anomaly detection + forecasting
                              |
               Tiered green / amber / red alert engine
                              |
        Dashboard, community guidance, health-service action
```

## Safeguarding and governance

- Store aggregate district, facility, and environmental observations—not individual or child-level health records.
- Keep source, timestamp, geographic precision, model version, and confidence with every derived signal.
- Treat DHIS2 access as a formal dependency requiring Ministry of Health or district authorization.
- Document model metrics, drift checks, known data gaps, and the reason for every alert threshold.
- Keep the platform MIT-licensed and design future content and guidance for low-bandwidth, multilingual use.

## Immediate engineering backlog

1. Add normalized observation tables and scheduled ingestion jobs with retries and freshness metadata.
2. Add district/facility geography and Leaflet map layers using OpenStreetMap boundaries.
3. Integrate OpenAQ and CHIRPS, then validate anomaly thresholds in Kampala and Lira.
4. Replace the heat and precipitation screening rules with validated, documented models.
5. Add DHIS2/sample historical data interfaces and evaluate malaria forecasts using accuracy, F1, recall, calibration, and lead-time metrics.
6. Add alert history, audit logs, role-based access, and a public read-only view.

The first ingestion command is available as:

```bash
php artisan climate:ingest-open-meteo --latitude=-0.3476 --longitude=32.5825
```

It records an auditable `ingestion_runs` row and writes normalized observations for the requested monitoring point. A scheduler or queue worker can invoke this command for a configured list of district and facility locations once those locations are loaded.

Approved locations can be loaded with a CSV containing `name,type,latitude,longitude` and optional `country_code,admin_level,external_id,is_active` columns:

```bash
php artisan climate:import-locations storage/app/locations.csv
```

The dashboard map reads active locations from `/api/climate-locations` and currently uses OpenStreetMap tiles through Leaflet.

The national risk layer reads `/api/national-risk`. It uses a cached multi-coordinate Open-Meteo request across a Uganda screening grid, then displays apparent-temperature and daily-rainfall summaries. It is intentionally a screening surface: the cells are not administrative boundaries, the rainfall signal is not a flood inundation model, and heat thresholds must be validated against local health outcomes before operational alerts are issued. CHIRPS, elevation/drainage data, official district boundaries, and validated vulnerability models are required for the production flood and heat layers.

The CHIRPS district adapter is available as:

```bash
php artisan climate:import-chirps storage/app/chirps-district-rainfall.csv
```

The CSV must contain `external_id,observed_at,rainfall_mm,baseline_mm`. The importer joins rows to approved district locations, stores the raw rainfall and baseline as normalized observations, and exposes anomaly and drainage-adjusted risk through `/api/district-risk`. CHIRPS v3 is the selected source; its historical baseline and 0.05° gridded rainfall are appropriate for anomaly analysis, but the flood model still needs elevation, drainage, exposure, and local validation before operational use.

Official district geography is synchronized with:

```bash
php artisan climate:sync-uganda-districts
```

The command is scheduled weekly, uses official district codes for boundary joins, and preserves an ingestion audit record. It expects at least 100 features so a partial provider response cannot silently replace the national geography.

CHIRPS requests are queued rather than executed inside web requests:

```bash
php artisan climate:queue-chirps
php artisan queue:work --queue=default
```

The command is scheduled daily. Each district request is submitted to ClimateSERV, polled asynchronously, and written as `chirps-v3` observations when complete. A queue worker must be running for the scheduled requests to progress. A rainfall observation without a climatological baseline remains `awaiting_baseline` and is not presented as an operational flood-risk score.

Monthly baselines can be loaded with:

```bash
php artisan climate:import-baselines storage/app/chirps-baselines.csv
```

The baseline CSV must contain `external_id,month,mean_mm,stddev_mm,sample_count`; `period_start` and `period_end` are optional. Once both observations and baselines exist, `/api/district-risk` can return district anomaly and risk scores.

OpenAQ v3 is available through an optional authenticated connector:

```bash
OPENAQ_API_KEY=your-key
php artisan climate:sync-openaq
```

The connector discovers Uganda stations within the national bounding box, stores PM2.5/PM10 observations, and exposes a seven-observation minimum anomaly readiness check through `/api/air-quality-risk`. OpenAQ v3 requires the `X-API-Key` header and its older v1/v2 endpoints are retired.

Health facilities can be imported with the location importer using `type=facility`. Optional columns include `elevation_m`, `drainage_score`, and JSON `metadata` fields for `children_served`, `catchment_population`, and `criticality` (1–5). The resulting screening assessment is available from `/api/vulnerability` and is intentionally incomplete until those inputs are supplied; it is not a validated clinical or resource-allocation model.

Public candidate facilities can also be synchronized with:

```bash
php artisan climate:sync-osm-facilities
```

OpenStreetMap facilities are labelled as candidate registry records and must be verified against an approved health-facility registry before operational planning decisions are made.

The alert engine is available with:

```bash
php artisan climate:generate-alerts
```

It runs every 15 minutes, persists active/resolved alert history, assigns green/amber/red evidence tiers, and attaches recommended first actions. It only creates alerts from available signals; missing baselines do not become false reassurance or warnings.

The operational alert flow is exposed through `/api/alerts` and `/api/alerts/{alert}/action`. Each active alert identifies the intended audience, action window, recommended actions, source, and confidence. A responder can acknowledge, start, or complete one alert action with a named responder and aggregate facility-level notes. The alerts dashboard displays this lifecycle, while the facilities dashboard provides a next preparedness action for each facility. Authentication and role-based permissions remain a production requirement before this prototype endpoint is deployed beyond a controlled pilot.
