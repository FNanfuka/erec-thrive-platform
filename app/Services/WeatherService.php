<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    public function __construct(private ClimateObservationRecorder $recorder) {}

    public function getWeather($latitude, $longitude)
    {
        $cacheKey = $this->cacheKey($latitude, $longitude);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(15)
                ->retry(2, 250, function ($exception) {
                    return $exception instanceof ConnectionException;
                })
                ->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,wind_speed_10m,wind_gusts_10m,cloud_cover,weather_code',
                    'hourly' => 'temperature_2m,precipitation_probability,precipitation,wind_speed_10m',
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,precipitation_probability_max,uv_index_max',
                    'timezone' => 'auto',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Open-Meteo request failed.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'message' => $exception->getMessage(),
            ]);

            return ['current' => null];
        }

        $data = $response->json();

        if (! is_array($data)) {
            return [];
        }

        if (! isset($data['current']) || ! is_array($data['current'])) {
            return ['current' => null];
        }

        Cache::put($cacheKey, $data, now()->addMinutes(10));
        $this->recorder->recordWeather((float) $latitude, (float) $longitude, $data);

        return $data;

    }

    public function weatherSummary($code): array
    {
        if (! is_numeric($code)) return ['label' => 'Weather unavailable', 'icon' => '•', 'rain' => false];

        return match ((int) $code) {
            0 => ['label' => 'Clear skies', 'icon' => '☀', 'rain' => false],
            1, 2 => ['label' => 'Partly cloudy', 'icon' => '◐', 'rain' => false],
            3 => ['label' => 'Cloudy', 'icon' => '☁', 'rain' => false],
            45, 48 => ['label' => 'Foggy', 'icon' => '≋', 'rain' => false],
            51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82 => ['label' => 'Rain showers', 'icon' => '☂', 'rain' => true],
            71, 73, 75, 77, 85, 86 => ['label' => 'Wintry precipitation', 'icon' => '❄', 'rain' => true],
            95, 96, 99 => ['label' => 'Thunderstorms', 'icon' => 'ϟ', 'rain' => true],
            default => ['label' => 'Variable conditions', 'icon' => '◌', 'rain' => false],
        };
    }

    private function cacheKey($latitude, $longitude): string
    {
        return 'weather:'.number_format((float) $latitude, 3, '.', '').':'.number_format((float) $longitude, 3, '.', '');
    }
}
