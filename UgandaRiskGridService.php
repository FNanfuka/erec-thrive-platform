<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UgandaRiskGridService
{
    public function getNationalRisk(): array
    {
        return Cache::remember('uganda:risk-grid:v3', now()->addMinutes(15), function () {
            $points = $this->grid();
            $latitudes = implode(',', array_column($points, 'latitude'));
            $longitudes = implode(',', array_column($points, 'longitude'));

            try {
                $response = Http::connectTimeout(5)
                    ->timeout(20)
                    ->retry(2, 250, fn ($exception) => $exception instanceof ConnectionException)
                    ->get('https://api.open-meteo.com/v1/forecast', [
                        'latitude' => $latitudes,
                        'longitude' => $longitudes,
                        'current' => 'apparent_temperature,temperature_2m',
                        'daily' => 'apparent_temperature_max,precipitation_sum',
                        'past_days' => 7,
                        'forecast_days' => 2,
                        'timezone' => 'UTC',
                    ])
                    ->throw();
            } catch (ConnectionException|RequestException $exception) {
                Log::warning('Uganda risk grid request failed.', ['message' => $exception->getMessage()]);

                return ['available' => false, 'cells' => [], 'trends' => []];
            }

            $payload = $response->json();
            $payload = isset($payload[0]) ? $payload : [$payload];

            if (! is_array($payload) || count($payload) !== count($points)) {
                return ['available' => false, 'cells' => [], 'trends' => []];
            }

            return $this->summarize($points, $payload);
        });
    }

    private function summarize(array $points, array $payload): array
    {
        $cells = [];
        $trendBuckets = [];

        foreach ($payload as $index => $data) {
            $current = $data['current'] ?? [];
            $daily = $data['daily'] ?? [];
            $temperature = $current['apparent_temperature'] ?? null;
            $today = Carbon::now('UTC')->toDateString();
            $todayIndex = array_search($today, $daily['time'] ?? [], true);
            $rain = $daily['precipitation_sum'][$todayIndex !== false ? $todayIndex : 0] ?? null;

            if (! is_numeric($temperature) || ! is_numeric($rain)) {
                continue;
            }

            $cells[] = [
                'latitude' => $points[$index]['latitude'],
                'longitude' => $points[$index]['longitude'],
                'temperature' => round((float) $temperature, 1),
                'rainfall' => round((float) $rain, 1),
                'heat_level' => $this->level((float) $temperature, [27, 32, 38]),
                'flood_level' => $this->level((float) $rain, [20, 50, 100]),
            ];

            foreach (($daily['time'] ?? []) as $day => $date) {
                $trendBuckets[$date]['temperature'][] = $daily['apparent_temperature_max'][$day] ?? null;
                $trendBuckets[$date]['rainfall'][] = $daily['precipitation_sum'][$day] ?? null;
                $trendBuckets[$date]['is_prediction'] = $date > $today;
            }
        }

        $trends = [];
        foreach ($trendBuckets as $date => $values) {
            $temperatures = array_filter($values['temperature'], 'is_numeric');
            $rainfalls = array_filter($values['rainfall'], 'is_numeric');
            $trends[] = [
                'date' => $date,
                'temperature' => $temperatures ? round(array_sum($temperatures) / count($temperatures), 1) : null,
                'rainfall' => $rainfalls ? round(array_sum($rainfalls) / count($rainfalls), 1) : null,
                'is_prediction' => $values['is_prediction'] ?? false,
            ];
        }

        return ['available' => count($cells) > 0, 'cells' => $cells, 'trends' => $trends];
    }

    private function level(float $value, array $thresholds): string
    {
        return match (true) {
            $value < $thresholds[0] => 'low',
            $value < $thresholds[1] => 'watch',
            $value < $thresholds[2] => 'warning',
            default => 'severe',
        };
    }

    private function grid(): array
    {
        $points = [];
        foreach (range(-1, 4, 1) as $latitude) {
            foreach (range(30, 35, 1) as $longitude) {
                $points[] = ['latitude' => (float) $latitude, 'longitude' => (float) $longitude];
            }
        }

        return $points;
    }
}
