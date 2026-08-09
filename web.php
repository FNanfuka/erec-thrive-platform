<?php

use App\Http\Controllers\ClimateLocationController;
use App\Http\Controllers\Dhis2Controller;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WeatherController::class, 'index'])
    ->name('home');

Route::view('/dashboard', 'dashboard.overview')->name('dashboard.overview');
Route::view('/dashboard/map', 'dashboard.map')->name('dashboard.map');
Route::view('/dashboard/alerts', 'dashboard.alerts')->name('dashboard.alerts');
Route::view('/dashboard/facilities', 'dashboard.facilities')->name('dashboard.facilities');
Route::view('/dashboard/reports', 'dashboard.reports')->name('dashboard.reports');
Route::view('/dashboard/pipeline', 'dashboard.pipeline')->name('dashboard.pipeline');
Route::view('/dashboard/support', 'dashboard.support')->name('dashboard.support');

Route::post('/weather', [WeatherController::class, 'weather'])
    ->name('weather.search');

Route::get('/api/climate-locations', [ClimateLocationController::class, 'index'])
    ->name('climate.locations');

Route::get('/api/location-search', [ClimateLocationController::class, 'searchLocations'])
    ->name('climate.location-search');

Route::get('/api/national-risk', [ClimateLocationController::class, 'nationalRisk'])
    ->name('climate.national-risk');

Route::get('/api/district-risk', [ClimateLocationController::class, 'districtRisk'])
    ->name('climate.district-risk');

Route::get('/api/pipeline-status', [ClimateLocationController::class, 'pipelineStatus'])
    ->name('climate.pipeline-status');

Route::get('/api/air-quality-risk', [ClimateLocationController::class, 'airQualityRisk'])
    ->name('climate.air-quality-risk');

Route::get('/api/air-quality-map', [ClimateLocationController::class, 'airQualityMap'])
    ->name('climate.air-quality-map');

Route::get('/api/vulnerability', [ClimateLocationController::class, 'vulnerability'])
    ->name('climate.vulnerability');

Route::get('/api/alerts', [ClimateLocationController::class, 'alerts'])
    ->name('climate.alerts');

Route::get('/api/reports/pilot', [ClimateLocationController::class, 'pilotReport'])
    ->name('climate.pilot-report');

Route::post('/api/health-outcomes', [ClimateLocationController::class, 'storeHealthOutcome'])
    ->name('climate.health-outcomes.store');

Route::get('/api/dhis2/status', [Dhis2Controller::class, 'status'])
    ->name('climate.dhis2-status');

Route::post('/api/dhis2/sync', [Dhis2Controller::class, 'sync'])
    ->name('climate.dhis2-sync');

Route::post('/api/alerts/{alert}/action', [ClimateLocationController::class, 'updateAlertAction'])
    ->name('climate.alert-action');
