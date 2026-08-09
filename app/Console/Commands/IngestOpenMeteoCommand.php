<?php

namespace App\Console\Commands;

use App\Models\IngestionRun;
use App\Services\AirQualityService;
use App\Services\WeatherService;
use Illuminate\Console\Command;
use Throwable;

class IngestOpenMeteoCommand extends Command
{
    protected $signature = 'climate:ingest-open-meteo
        {--latitude= : Latitude of the monitoring location}
        {--longitude= : Longitude of the monitoring location}';

    protected $description = 'Ingest cached weather and air-quality observations for a monitoring location';

    public function handle(WeatherService $weatherService, AirQualityService $airQualityService): int
    {
        $latitude = $this->option('latitude');
        $longitude = $this->option('longitude');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            $this->error('Provide numeric --latitude and --longitude values.');

            return self::FAILURE;
        }

        $run = IngestionRun::create([
            'source' => 'open-meteo',
            'status' => 'running',
            'started_at' => now(),
            'metadata' => ['latitude' => (float) $latitude, 'longitude' => (float) $longitude],
        ]);

        try {
            $weather = $weatherService->getWeather((float) $latitude, (float) $longitude);
            $airQuality = $airQualityService->getAirQuality((float) $latitude, (float) $longitude);
            $records = $this->countNumericValues($weather['current'] ?? [])
                + $this->countNumericValues($airQuality['current'] ?? []);

            $run->update([
                'status' => 'succeeded',
                'records_ingested' => $records,
                'finished_at' => now(),
            ]);
            $this->info("Ingestion completed: {$records} values processed.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
            $this->error('Ingestion failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function countNumericValues(array $values): int
    {
        return collect($values)->filter(fn ($value) => is_numeric($value))->count();
    }
}
