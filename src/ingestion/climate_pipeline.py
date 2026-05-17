"""
EREC THRIVE Intelligence System
Climate Data Ingestion Pipeline

Fetches and harmonises climate data from open sources:
- NASA POWER API (temperature, humidity, rainfall)
- OpenAQ API (air quality — PM2.5, PM10)
- CHIRPS (precipitation anomalies)

Author: Nanfuka Fatuma | Eco Reset Edge Connect
License: MIT
"""

import requests
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from typing import Optional
import logging
import time

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Uganda bounding box
UGANDA_BBOX = {"lat_min": -1.5, "lat_max": 4.2, "lon_min": 29.5, "lon_max": 35.0}

# Key Uganda districts for pilot
PILOT_DISTRICTS = [
    {"name": "Kampala", "lat": 0.3476, "lon": 32.5825},
    {"name": "Lira", "lat": 2.2499, "lon": 32.8997},
    {"name": "Kyangwali", "lat": 1.4167, "lon": 30.9000},
    {"name": "Nakivale", "lat": -0.8833, "lon": 30.9833},
    {"name": "Gulu", "lat": 2.7747, "lon": 32.2990},
    {"name": "Mbale", "lat": 1.0840, "lon": 34.1751},
]


class NASAPowerClient:
    """
    Fetches meteorological data from NASA POWER API.
    Free, open access — no API key required.
    https://power.larc.nasa.gov/
    """

    BASE_URL = "https://power.larc.nasa.gov/api/temporal/daily/point"

    PARAMETERS = {
        "T2M": "temperature_2m_c",           # Temperature at 2m (°C)
        "T2M_MAX": "temperature_max_c",       # Max daily temperature
        "RH2M": "relative_humidity_pct",      # Relative humidity (%)
        "PRECTOTCORR": "rainfall_mm",         # Precipitation (mm/day)
        "WS2M": "wind_speed_ms",              # Wind speed (m/s)
    }

    def fetch(
        self,
        lat: float,
        lon: float,
        start_date: str,
        end_date: str,
    ) -> pd.DataFrame:
        """
        Fetch daily climate data for a location.

        Args:
            lat, lon: Coordinates (decimal degrees)
            start_date, end_date: Format YYYYMMDD

        Returns:
            DataFrame with daily climate observations
        """
        params = {
            "parameters": ",".join(self.PARAMETERS.keys()),
            "community": "RE",
            "longitude": lon,
            "latitude": lat,
            "start": start_date,
            "end": end_date,
            "format": "JSON",
        }

        logger.info(f"Fetching NASA POWER data for ({lat:.3f}, {lon:.3f})...")
        try:
            response = requests.get(self.BASE_URL, params=params, timeout=30)
            response.raise_for_status()
            data = response.json()

            records = {}
            props = data["properties"]["parameter"]
            for nasa_key, col_name in self.PARAMETERS.items():
                if nasa_key in props:
                    records[col_name] = props[nasa_key]

            df = pd.DataFrame(records)
            df.index = pd.to_datetime(df.index, format="%Y%m%d")
            df.index.name = "date"
            df["latitude"] = lat
            df["longitude"] = lon

            # Replace NASA fill value (-999) with NaN
            df = df.replace(-999.0, np.nan)
            logger.info(f"Fetched {len(df)} daily records.")
            return df

        except requests.RequestException as e:
            logger.error(f"NASA POWER API error: {e}")
            return pd.DataFrame()

    def compute_heat_stress(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Compute Heat Stress Index (HSI) from temperature and humidity.
        Simplified Steadman formula — values >27°C indicate heat stress risk.
        """
        T = df["temperature_2m_c"]
        RH = df["relative_humidity_pct"]
        df["heat_stress_index"] = (
            -8.78469475556
            + 1.61139411 * T
            + 2.33854883889 * RH
            - 0.14611605 * T * RH
            - 0.012308094 * T**2
            - 0.0164248277778 * RH**2
            + 0.002211732 * T**2 * RH
            + 0.00072546 * T * RH**2
            - 0.000003582 * T**2 * RH**2
        ).clip(0, 10) / 10 * 10  # normalise to 0-10 scale
        return df


class OpenAQClient:
    """
    Fetches air quality data from OpenAQ API.
    Free, open access — no API key required for basic use.
    https://openaq.org/
    """

    BASE_URL = "https://api.openaq.org/v2/measurements"

    def fetch(
        self,
        lat: float,
        lon: float,
        radius_m: int = 25000,
        days_back: int = 30,
    ) -> pd.DataFrame:
        """
        Fetch recent air quality measurements near a location.

        Args:
            lat, lon: Coordinates
            radius_m: Search radius in metres
            days_back: Number of days of historical data

        Returns:
            DataFrame with PM2.5 and PM10 measurements
        """
        date_from = (datetime.utcnow() - timedelta(days=days_back)).strftime(
            "%Y-%m-%dT00:00:00Z"
        )

        params = {
            "coordinates": f"{lat},{lon}",
            "radius": radius_m,
            "parameter": ["pm25", "pm10"],
            "date_from": date_from,
            "limit": 1000,
            "sort": "desc",
        }

        logger.info(f"Fetching OpenAQ data near ({lat:.3f}, {lon:.3f})...")
        try:
            response = requests.get(
                self.BASE_URL, params=params, timeout=30,
                headers={"User-Agent": "EREC-THRIVE/1.0 (ecoresetedge@gmail.com)"}
            )
            response.raise_for_status()
            results = response.json().get("results", [])

            if not results:
                logger.warning("No air quality data found for this location.")
                return pd.DataFrame()

            rows = []
            for r in results:
                rows.append({
                    "date": r["date"]["utc"],
                    "parameter": r["parameter"],
                    "value": r["value"],
                    "unit": r["unit"],
                    "location": r.get("location", "unknown"),
                })

            df = pd.DataFrame(rows)
            df["date"] = pd.to_datetime(df["date"])
            df = df[df["value"] >= 0]  # remove invalid readings

            # Pivot to wide format
            pivot = df.pivot_table(
                index="date", columns="parameter", values="value", aggfunc="mean"
            ).reset_index()
            pivot.columns.name = None

            logger.info(f"Fetched {len(pivot)} air quality records.")
            return pivot

        except requests.RequestException as e:
            logger.error(f"OpenAQ API error: {e}")
            return pd.DataFrame()

    def daily_summary(self, df: pd.DataFrame) -> pd.DataFrame:
        """Aggregate to daily mean air quality values."""
        df["date_only"] = pd.to_datetime(df["date"]).dt.date
        return df.groupby("date_only").mean(numeric_only=True).reset_index()


class ClimateDataPipeline:
    """
    Orchestrates climate data ingestion for all pilot districts.
    Produces a harmonised dataset ready for ML feature engineering.
    """

    def __init__(self):
        self.nasa = NASAPowerClient()
        self.openaq = OpenAQClient()

    def run(
        self,
        districts: list = None,
        days_back: int = 90,
        output_path: str = "../../data/processed/climate_data.csv",
    ) -> pd.DataFrame:
        """
        Full pipeline: fetch → harmonise → save.

        Args:
            districts: List of district dicts with name/lat/lon
            days_back: Historical window in days
            output_path: Where to save the harmonised dataset
        """
        if districts is None:
            districts = PILOT_DISTRICTS

        end_date = datetime.utcnow().strftime("%Y%m%d")
        start_date = (datetime.utcnow() - timedelta(days=days_back)).strftime("%Y%m%d")

        all_records = []

        for district in districts:
            logger.info(f"\nProcessing: {district['name']}")

            # Fetch climate data
            climate_df = self.nasa.fetch(
                district["lat"], district["lon"], start_date, end_date
            )
            if not climate_df.empty:
                climate_df = self.nasa.compute_heat_stress(climate_df)
                climate_df["district"] = district["name"]
                all_records.append(climate_df)

            time.sleep(1)  # respectful API rate limiting

        if not all_records:
            logger.error("No data fetched. Check API connectivity.")
            return pd.DataFrame()

        combined = pd.concat(all_records)
        combined = combined.reset_index()

        # Compute derived features
        combined = self._compute_rainfall_anomaly(combined)
        combined = self._compute_flood_risk(combined)

        # Save
        combined.to_csv(output_path, index=False)
        logger.info(f"\nPipeline complete. {len(combined)} records saved to {output_path}")
        return combined

    def _compute_rainfall_anomaly(self, df: pd.DataFrame) -> pd.DataFrame:
        """Compute % deviation from district-level mean rainfall."""
        district_means = df.groupby("district")["rainfall_mm"].transform("mean")
        df["rainfall_anomaly_pct"] = (
            (df["rainfall_mm"] - district_means) / district_means.replace(0, np.nan) * 100
        ).round(1)
        return df

    def _compute_flood_risk(self, df: pd.DataFrame) -> pd.DataFrame:
        """
        Simple flood risk score (0-10) based on cumulative rainfall
        and rainfall anomaly. In production: integrate DEM + drainage data.
        """
        rolling_rain = df.groupby("district")["rainfall_mm"].transform(
            lambda x: x.rolling(7, min_periods=1).sum()
        )
        anomaly_clipped = df["rainfall_anomaly_pct"].clip(-100, 200)

        df["flood_risk_score"] = (
            (rolling_rain / rolling_rain.max() * 5)
            + (anomaly_clipped / 200 * 5)
        ).clip(0, 10).round(2)
        return df


if __name__ == "__main__":
    print("EREC THRIVE — Climate Data Pipeline")
    print("=" * 50)
    pipeline = ClimateDataPipeline()
    df = pipeline.run(days_back=30)
    if not df.empty:
        print(f"\nData shape: {df.shape}")
        print(df[["district", "date", "temperature_2m_c", "rainfall_mm", "flood_risk_score"]].tail(10))
