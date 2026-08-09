<?php

namespace App\Console\Commands;

use App\Models\ClimateLocation;
use App\Models\ClimateRainfallBaseline;
use App\Models\IngestionRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class ImportRainfallBaselinesCommand extends Command
{
    protected $signature = 'climate:import-baselines {path : CSV with external_id,month,mean_mm,stddev_mm,sample_count columns}';

    protected $description = 'Import monthly CHIRPS rainfall climatology baselines';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('The baseline CSV file does not exist or cannot be read.');

            return self::FAILURE;
        }

        $run = IngestionRun::create(['source' => 'chirps-baseline', 'status' => 'running', 'started_at' => now(), 'metadata' => ['path' => basename($path)]]);

        try {
            $file = new \SplFileObject($path);
            $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $file->fgetcsv());
            $required = ['external_id', 'month', 'mean_mm', 'stddev_mm', 'sample_count'];
            if (array_diff($required, $headers)) {
                throw new \InvalidArgumentException('CSV must contain external_id,month,mean_mm,stddev_mm,sample_count columns.');
            }

            $count = 0;
            foreach ($file as $row) {
                if (! is_array($row) || ! array_filter($row, fn ($value) => $value !== null && $value !== '')) {
                    continue;
                }
                $record = array_combine($headers, array_pad($row, count($headers), null));
                $location = ClimateLocation::where('external_id', trim($record['external_id']))->first();
                if (! $location || ! is_numeric($record['month']) || (int) $record['month'] < 1 || (int) $record['month'] > 12 || ! is_numeric($record['mean_mm']) || ! is_numeric($record['sample_count'])) {
                    throw new \InvalidArgumentException('Every row needs an existing district and valid baseline values.');
                }

                ClimateRainfallBaseline::updateOrCreate([
                    'climate_location_id' => $location->id,
                    'month' => (int) $record['month'],
                    'source' => 'chirps-v3',
                ], [
                    'mean_mm' => (float) $record['mean_mm'],
                    'stddev_mm' => is_numeric($record['stddev_mm'] ?? null) ? (float) $record['stddev_mm'] : null,
                    'sample_count' => (int) $record['sample_count'],
                    'period_start' => ! empty($record['period_start']) ? Carbon::parse($record['period_start']) : null,
                    'period_end' => ! empty($record['period_end']) ? Carbon::parse($record['period_end']) : null,
                ]);
                $count++;
            }

            $run->update(['status' => 'succeeded', 'records_ingested' => $count, 'finished_at' => now()]);
            $this->info("Imported {$count} rainfall baselines.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => $exception->getMessage()]);
            $this->error('Baseline import failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
