<?php

namespace App\Console\Commands;

use App\Models\IngestionRun;
use App\Services\OpenAqService;
use Illuminate\Console\Command;

class SyncOpenAqCommand extends Command
{
    protected $signature = 'climate:sync-openaq';

    protected $description = 'Sync public OpenAQ monitoring stations and PM observations for Uganda';

    public function handle(OpenAqService $service): int
    {
        $run = IngestionRun::create(['source' => 'openaq', 'status' => 'running', 'started_at' => now()]);
        $result = $service->syncUganda();

        $run->update([
            'status' => $result['status'] === 'succeeded' ? 'succeeded' : $result['status'],
            'records_ingested' => $result['observations'] ?? 0,
            'finished_at' => now(),
            'error_message' => $result['message'] ?? null,
        ]);
        $this->line(sprintf('OpenAQ: %s; %d stations; %d observations.', $result['status'], $result['locations'], $result['observations']));

        return $result['status'] === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
