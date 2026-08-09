<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirQualityService
{
    public function __construct(private ClimateObservationRecorder $recorder) {}

    public function getAirQuality($latitude, $longitude): array
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
                ->get('https://air-quality-api.open-meteo.com/v1/air-quality', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'european_aqi,us_aqi,pm10,pm2_5,ozone,nitrogen_dioxide,sulphur_dioxide,carbon_monoxide',
                    'hourly' => 'european_aqi,us_aqi,pm2_5',
                    'forecast_days' => 3,
                    'timezone' => 'auto',
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Open-Meteo air quality request failed.', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'message' => $exception->getMessage(),
            ]);

            return ['current' => null];
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['current']) || ! is_array($data['current'])) {
            return ['current' => null];
        }

        Cache::put($cacheKey, $data, now()->addMinutes(15));
        $this->recorder->recordAirQuality((float) $latitude, (float) $longitude, $data);

        return $data;
    }

    public function aqiDescription($aqi): array
    {
        if (! is_numeric($aqi)) {
            return ['label' => 'Unavailable', 'class' => 'secondary'];
        }

        return match (true) {
            $aqi <= 50 => ['label' => 'Good', 'class' => 'success'],
            $aqi <= 100 => ['label' => 'Moderate', 'class' => 'warning'],
            $aqi <= 150 => ['label' => 'Unhealthy for sensitive groups', 'class' => 'orange'],
            $aqi <= 200 => ['label' => 'Unhealthy', 'class' => 'danger'],
            $aqi <= 300 => ['label' => 'Very Unhealthy', 'class' => 'dark'],
            default => ['label' => 'Hazardous', 'class' => 'dark'],
        };
    }

    public function aqiHealthMessage($aqi): string
    {
        if (! is_numeric($aqi)) return 'Air-quality health guidance is unavailable.';

        return match (true) {
            $aqi <= 50 => 'Air quality is satisfactory and poses little or no risk for most people.',
            $aqi <= 100 => 'Air quality is acceptable, but unusually sensitive people may experience symptoms.',
            $aqi <= 150 => 'Sensitive groups should reduce prolonged outdoor activity and monitor symptoms.',
            $aqi <= 200 => 'Health effects may be felt by sensitive groups. Reduce outdoor exposure and improve indoor air.',
            $aqi <= 300 => 'Health alert: everyone may experience more serious effects. Reschedule strenuous outdoor activity.',
            default => 'Health emergency conditions: avoid outdoor activity and follow local public-health guidance.',
        };
    }

    private function cacheKey($latitude, $longitude): string
    {
        return 'air-quality:'.number_format((float) $latitude, 3, '.', '').':'.number_format((float) $longitude, 3, '.', '');
    }
}
