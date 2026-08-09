<?php

namespace App\Console\Commands;

use App\Models\ClimateLocation;
use App\Models\ClimateObservation;
use App\Models\IngestionRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class ImportChirpsRainfallCommand extends Command
{
    protected $signature = 'climate:import-chirps {path : CSV with external_id,observed_at,rainfall_mm,baseline_mm columns}';

    protected $description = 'Import CHIRPS rainfall observations and district baselines';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The CHIRPS CSV file does not exist or cannot be read.');

            return self::FAILURE;
        }

        $run = IngestionRun::create(['source' => 'chirps-v3', 'status' => 'running', 'started_at' => now(), 'metadata' => ['path' => basename($path)]]);

        try {
            $file = new \SplFileObject($path);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $file->fgetcsv());
            $required = ['external_id', 'observed_at', 'rainfall_mm', 'baseline_mm'];
            if (array_diff($required, $headers)) {
                throw new \InvalidArgumentException('CSV must contain external_id,observed_at,rainfall_mm,baseline_mm columns.');
            }

            $count = 0;
            foreach ($file as $row) {
                if (! is_array($row) || ! array_filter($row, fn ($value) => $value !== null && $value !== '')) {
                    continue;
                }
                $record = array_combine($headers, array_pad($row, count($headers), null));
                $location = ClimateLocation::where('external_id', trim($record['external_id']))->first();
                if (! $location || ! is_numeric($record['rainfall_mm']) || ! is_numeric($record['baseline_mm'])) {
                    throw new \InvalidArgumentException('Every row needs an existing location and numeric rainfall values.');
                }

                ClimateObservation::updateOrCreate([
                    'climate_location_id' => $location->id,
                    'source' => 'chirps-v3',
                    'variable' => 'rainfall_mm',
                    'observed_at' => Carbon::parse($record['observed_at']),
                ], [
                    'value' => (float) $record['rainfall_mm'],
                    'unit' => 'mm',
                    'quality_flag' => 'observed',
                    'metadata' => ['baseline_mm' => (float) $record['baseline_mm']],
                ]);
                $count++;
            }

            $run->update(['status' => 'succeeded', 'records_ingested' => $count, 'finished_at' => now()]);
            $this->info("Imported {$count} CHIRPS observations.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => $exception->getMessage()]);
            $this->error('CHIRPS import failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
