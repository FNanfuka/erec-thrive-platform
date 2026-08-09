<?php

namespace App\Jobs;

use App\Models\ClimateObservation;
use App\Models\ClimateProviderJob;
use App\Services\ChirpsClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PollChirpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;

    public function __construct(public int $providerJobId) {}

    public function handle(ChirpsClient $client): void
    {
        $job = ClimateProviderJob::findOrFail($this->providerJobId);
        if ($job->status === 'complete') {
            return;
        }

        $progress = $client->progress($job->external_job_id);
        if ($progress < 100) {
            $this->release(30);

            return;
        }

        foreach ($client->data($job->external_job_id) as $granule) {
            $value = $granule['value']['avg'] ?? null;
            $date = $granule['date'] ?? null;
            if (! is_numeric($value) || ! $date) {
                continue;
            }

            ClimateObservation::updateOrCreate([
                'climate_location_id' => $job->climate_location_id,
                'source' => 'chirps-v3',
                'variable' => 'rainfall_mm',
                'observed_at' => Carbon::createFromFormat('j/n/y', $date)->startOfDay(),
            ], [
                'value' => (float) $value,
                'unit' => 'mm',
                'quality_flag' => 'observed',
                'metadata' => ['provider_job_id' => $job->id],
            ]);
        }

        $job->update(['status' => 'complete']);
    }
}
