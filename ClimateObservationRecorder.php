<?php

namespace App\Services;

use App\Models\ClimateLocation;
use App\Models\ClimateObservation;
use Carbon\Carbon;

class ClimateObservationRecorder
{
    public function recordWeather(float $latitude, float $longitude, array $data): void
    {
        $current = $data['current'] ?? [];
        $time = $current['time'] ?? null;

        if (! is_array($current) || ! $time) {
            return;
        }

        $location = $this->location($latitude, $longitude);
        $units = $data['current_units'] ?? [];

        foreach ([
            'temperature_2m', 'relative_humidity_2m', 'apparent_temperature',
            'wind_speed_10m', 'weather_code',
        ] as $variable) {
            if (isset($current[$variable]) && is_numeric($current[$variable])) {
                $this->upsert($location, 'open-meteo', $variable, $current[$variable], $units[$variable] ?? null, $time);
            }
        }
    }

    public function recordAirQuality(float $latitude, float $longitude, array $data): void
    {
        $current = $data['current'] ?? [];
        $time = $current['time'] ?? null;

        if (! is_array($current) || ! $time) {
            return;
        }

        $location = $this->location($latitude, $longitude);
        $units = $data['current_units'] ?? [];

        foreach ([
            'european_aqi', 'us_aqi', 'pm10', 'pm2_5', 'ozone',
            'nitrogen_dioxide', 'sulphur_dioxide', 'carbon_monoxide',
        ] as $variable) {
            if (isset($current[$variable]) && is_numeric($current[$variable])) {
                $this->upsert($location, 'open-meteo-air-quality', $variable, $current[$variable], $units[$variable] ?? null, $time);
            }
        }
    }

    private function location(float $latitude, float $longitude): ClimateLocation
    {
        return ClimateLocation::firstOrCreate(
            [
                'type' => 'point',
                'latitude' => round($latitude, 6),
                'longitude' => round($longitude, 6),
            ],
            ['name' => sprintf('Location %.3f, %.3f', $latitude, $longitude)]
        );
    }

    private function upsert(ClimateLocation $location, string $source, string $variable, $value, ?string $unit, string $time): void
    {
        ClimateObservation::updateOrCreate(
            [
                'climate_location_id' => $location->id,
                'source' => $source,
                'variable' => $variable,
                'observed_at' => Carbon::parse($time),
            ],
            [
                'value' => $value,
                'unit' => $unit,
                'quality_flag' => 'observed',
            ]
        );
    }
}
