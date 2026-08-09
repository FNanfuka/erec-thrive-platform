<?php

namespace App\Console\Commands;

use App\Jobs\SubmitChirpsJob;
use App\Models\ClimateLocation;
use App\Models\ClimateProviderJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class QueueChirpsCommand extends Command
{
    protected $signature = 'climate:queue-chirps {--begin= : Start date, defaults to two days ago} {--end= : End date, defaults to yesterday}';

    protected $description = 'Queue asynchronous CHIRPS rainfall requests for all mapped Uganda districts';

    public function handle(): int
    {
        $begin = $this->option('begin') ?: now()->subDays(2)->toDateString();
        $end = $this->option('end') ?: now()->subDay()->toDateString();
        $periodStart = Carbon::parse($begin)->startOfDay()->toDateTimeString();
        $periodEnd = Carbon::parse($end)->startOfDay()->toDateTimeString();
        $queued = 0;

        ClimateLocation::query()
            ->where('type', 'district')
            ->where('is_active', true)
            ->whereNotNull('metadata')
            ->chunkById(25, function ($districts) use ($periodStart, $periodEnd, &$queued) {
                foreach ($districts as $district) {
                    $geometry = $district->metadata['geometry'] ?? null;
                    if (! $geometry) {
                        continue;
                    }

                    $job = ClimateProviderJob::updateOrCreate([
                        'climate_location_id' => $district->id,
                        'provider' => 'chirps-v3',
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                    ], [
                        'metadata' => ['source' => 'ClimateSERV'],
                    ]);

                    if ($job->wasRecentlyCreated || in_array($job->status, ['failed', 'expired'], true)) {
                        $job->update(['status' => 'queued', 'error_message' => null]);
                        SubmitChirpsJob::dispatch($job->id);
                        $queued++;
                    }
                }
            });

        $this->info("Queued {$queued} CHIRPS district requests.");

        return self::SUCCESS;
    }
}
