<?php

namespace App\Console\Commands;

use App\Models\ClimateLocation;
use App\Models\IngestionRun;
use Illuminate\Console\Command;
use Throwable;

class ImportClimateLocationsCommand extends Command
{
    protected $signature = 'climate:import-locations {path : CSV file with name,type,latitude,longitude columns}';

    protected $description = 'Import approved district and facility monitoring locations from CSV';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The CSV file does not exist or cannot be read.');

            return self::FAILURE;
        }

        $run = IngestionRun::create([
            'source' => 'location-import',
            'status' => 'running',
            'started_at' => now(),
            'metadata' => ['path' => basename($path)],
        ]);

        try {
            $file = new \SplFileObject($path);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $file->fgetcsv());
            $required = ['name', 'type', 'latitude', 'longitude'];

            if (array_diff($required, $headers)) {
                throw new \InvalidArgumentException('CSV must contain name,type,latitude,longitude columns.');
            }

            $count = 0;
            foreach ($file as $row) {
                if (! is_array($row) || count(array_filter($row, fn ($value) => $value !== null && $value !== '')) === 0) {
                    continue;
                }

                $record = array_combine($headers, array_pad($row, count($headers), null));
                if (! is_numeric($record['latitude'] ?? null) || ! is_numeric($record['longitude'] ?? null)) {
                    throw new \InvalidArgumentException('Every location must have numeric latitude and longitude.');
                }

                $attributes = [
                    'name' => trim($record['name']),
                    'type' => trim($record['type']),
                    'country_code' => $record['country_code'] ?? null,
                    'admin_level' => $record['admin_level'] ?? null,
                    'external_id' => $record['external_id'] ?? null,
                    'latitude' => (float) $record['latitude'],
                    'longitude' => (float) $record['longitude'],
                    'elevation_m' => is_numeric($record['elevation_m'] ?? null) ? (float) $record['elevation_m'] : null,
                    'drainage_score' => is_numeric($record['drainage_score'] ?? null) ? (float) $record['drainage_score'] : null,
                    'is_active' => filter_var($record['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
                ];
                if (! empty($record['metadata'])) {
                    $attributes['metadata'] = json_decode($record['metadata'], true, 512, JSON_THROW_ON_ERROR);
                }
                $identity = $attributes['external_id']
                    ? ['external_id' => $attributes['external_id']]
                    : ['type' => $attributes['type'], 'latitude' => $attributes['latitude'], 'longitude' => $attributes['longitude']];

                ClimateLocation::updateOrCreate($identity, $attributes);
                $count++;
            }

            $run->update(['status' => 'succeeded', 'records_ingested' => $count, 'finished_at' => now()]);
            $this->info("Imported {$count} monitoring locations.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => $exception->getMessage()]);
            $this->error('Location import failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
