<?php

namespace App\Console\Commands;

use App\Services\AlertEngineService;
use Illuminate\Console\Command;

class GenerateClimateAlertsCommand extends Command
{
    protected $signature = 'climate:generate-alerts';

    protected $description = 'Generate tiered climate-health alerts from available evidence';

    public function handle(AlertEngineService $engine): int
    {
        $this->info('Generated '.$engine->generate().' active climate alerts.');

        return self::SUCCESS;
    }
}
