<?php

namespace App\Jobs;

use App\Models\ClimateProviderJob;
use App\Services\ChirpsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitChirpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $providerJobId) {}

    public function handle(ChirpsClient $client): void
    {
        $job = ClimateProviderJob::with('location')->findOrFail($this->providerJobId);
        $geometry = $job->location->metadata['geometry'] ?? null;
        if (! $geometry) {
            $job->update(['status' => 'failed', 'error_message' => 'District geometry is missing.']);

            return;
        }

        $job->increment('attempts');
        $externalId = $client->submit($geometry, $job->period_start->toDateString(), $job->period_end->toDateString());
        $job->update(['external_job_id' => $externalId, 'status' => 'processing']);
        PollChirpsJob::dispatch($job->id)->delay(now()->addSeconds(15));
    }
}
