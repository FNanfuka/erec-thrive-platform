<?php

namespace App\Services;

use App\Models\ClimateLocation;
use App\Models\ClimateObservation;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAqService
{
    private const BASE_URL = 'https://api.openaq.org/v3';

    public function syncUganda(): array
    {
        $apiKey = config('services.openaq.key');
        if (! $apiKey) {
            return ['status' => 'not_configured', 'locations' => 0, 'observations' => 0];
        }

        $client = Http::withHeaders(['X-API-Key' => $apiKey])
            ->connectTimeout(10)
            ->timeout(30)
            ->retry(2, 500, fn ($exception) => $exception instanceof ConnectionException);

        try {
            $locations = $client->get(self::BASE_URL.'/locations', [
                'bbox' => '29.5,-1.5,35.1,4.3',
                'limit' => 1000,
                'page' => 1,
            ])->throw()->json('results') ?? [];
            $locationCount = 0;
            $observationCount = 0;

            foreach ($locations as $remote) {
                $coordinates = $remote['coordinates'] ?? [];
                if (! is_numeric($coordinates['latitude'] ?? null) || ! is_numeric($coordinates['longitude'] ?? null)) {
                    continue;
                }

                $externalId = 'openaq:'.$remote['id'];
                $location = ClimateLocation::where('external_id', $externalId)->first()
                    ?? ClimateLocation::where('type', 'air-monitor')
                        ->where('latitude', $coordinates['latitude'])
                        ->where('longitude', $coordinates['longitude'])
                        ->first()
                    ?? new ClimateLocation;
                $location->fill([
                    'type' => 'air-monitor',
                    'external_id' => $externalId,
                    'name' => $remote['name'] ?? 'OpenAQ monitor '.$remote['id'],
                    'country_code' => 'UG',
                    'admin_level' => 'monitoring station',
                    'latitude' => $coordinates['latitude'],
                    'longitude' => $coordinates['longitude'],
                    'is_active' => true,
                    'metadata' => ['source' => 'OpenAQ v3', 'openaq_id' => $remote['id']],
                ])->save();
                $locationCount++;
                $sensors = collect($remote['sensors'] ?? [])->keyBy('id');
                $latest = $client->get(self::BASE_URL.'/locations/'.$remote['id'].'/latest', ['limit' => 100])->throw()->json('results') ?? [];

                foreach ($latest as $measurement) {
                    $sensor = $sensors->get($measurement['sensorsId'] ?? null);
                    $parameter = strtolower((string) data_get($sensor, 'parameter.name', data_get($sensor, 'parameter.displayName', '')));
                    $variable = match ($parameter) {
                        'pm25', 'pm2.5', 'pm2_5' => 'pm2_5',
                        'pm10' => 'pm10',
                        default => null,
                    };
                    if (! $variable || ! is_numeric($measurement['value'] ?? null) || empty($measurement['datetime']['utc'])) {
                        continue;
                    }

                    ClimateObservation::updateOrCreate([
                        'climate_location_id' => $location->id,
                        'source' => 'openaq',
                        'variable' => $variable,
                        'observed_at' => Carbon::parse($measurement['datetime']['utc'])->toDateTimeString(),
                    ], [
                        'value' => (float) $measurement['value'],
                        'unit' => data_get($sensor, 'parameter.units'),
                        'quality_flag' => 'observed',
                        'metadata' => ['sensor_id' => $measurement['sensorsId'], 'openaq_location_id' => $remote['id']],
                    ]);
                    $observationCount++;
                }
            }

            return ['status' => 'succeeded', 'locations' => $locationCount, 'observations' => $observationCount];
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('OpenAQ sync failed.', ['message' => $exception->getMessage()]);

            return ['status' => 'failed', 'locations' => 0, 'observations' => 0, 'message' => $exception->getMessage()];
        }
    }
}
