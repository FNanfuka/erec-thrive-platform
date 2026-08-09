<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('climate:sync-uganda-districts')->weekly()->withoutOverlapping();
Schedule::command('climate:queue-chirps')->daily()->withoutOverlapping();
Schedule::command('climate:sync-openaq')->daily()->withoutOverlapping();
Schedule::command('climate:assess-vulnerability')->daily()->withoutOverlapping();
Schedule::command('climate:sync-osm-facilities')->weekly()->withoutOverlapping();
Schedule::command('climate:generate-alerts')->everyFifteenMinutes()->withoutOverlapping();
