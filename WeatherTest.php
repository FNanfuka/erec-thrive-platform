<?php

use App\Models\ClimateAlert;
use App\Models\ClimateAlertAction;
use App\Models\ClimateHealthOutcome;
use App\Models\ClimateLocation;
use App\Models\ClimateObservation;
use App\Models\VulnerabilityAssessment;
use App\Services\AirQualityService;
use App\Services\ClimateHealthAssessmentService;
use App\Services\ChildClimateRiskService;
use App\Services\WeatherService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('the weather dashboard handles missing current data gracefully', function () {
    $service = Mockery::mock(WeatherService::class);
    $service->shouldReceive('getWeather')->once()->andReturn([
        'current' => null,
    ]);
    $service->shouldReceive('weatherSummary')->once()->andReturn(['label' => 'Weather unavailable', 'icon' => '•', 'rain' => false]);

    $this->app->instance(WeatherService::class, $service);

    $airQualityService = Mockery::mock(AirQualityService::class);
    $airQualityService->shouldReceive('getAirQuality')->once()->andReturn(['current' => null]);
    $airQualityService->shouldReceive('aqiDescription')->once()->andReturn([
        'label' => 'Unavailable',
        'class' => 'secondary',
    ]);
    $airQualityService->shouldReceive('aqiHealthMessage')->once()->andReturn('Air-quality health guidance is unavailable.');
    $this->app->instance(AirQualityService::class, $airQualityService);

    $response = $this->post('/weather', [
        'latitude' => '52.52',
        'longitude' => '13.41',
    ]);

    $response->assertStatus(200);
    $response->assertSee('Unable to load weather data right now.');
});

test('the weather search requires valid coordinates', function () {
    $response = $this->post('/weather', [
        'latitude' => null,
        'longitude' => null,
    ]);

    $response->assertSessionHasErrors(['latitude', 'longitude']);
});

test('the weather service handles an unavailable weather provider gracefully', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $data = app(WeatherService::class)->getWeather('52.52', '13.41');

    expect($data)->toBe(['current' => null]);
});

test('air quality requests a three day forecast and records current data', function () {
    Cache::forget('air-quality:1.111:2.222');
    Http::fake([
        'https://air-quality-api.open-meteo.com/*' => Http::response([
            'current' => [
                'time' => '2026-08-07T10:00',
                'european_aqi' => 48,
                'pm2_5' => 18.2,
            ],
            'hourly' => [
                'time' => ['2026-08-07T10:00', '2026-08-07T11:00'],
                'european_aqi' => [48, 62],
                'pm2_5' => [18.2, 27.4],
            ],
        ]),
    ]);

    $data = app(AirQualityService::class)->getAirQuality(1.111, 2.222);

    expect($data['hourly']['european_aqi'])->toBe([48, 62]);
    Http::assertSent(fn ($request) => $request->data()['forecast_days'] === 3);
    expect(ClimateObservation::where('source', 'open-meteo-air-quality')->count())->toBe(2);
});

test('climate health assessment returns actionable air forecast guidance', function () {
    $signals = app(ClimateHealthAssessmentService::class)->assess([
        'current' => ['apparent_temperature' => 33],
        'daily' => ['precipitation_sum' => [10]],
    ], [
        'current' => ['european_aqi' => 65],
        'hourly' => [
            'time' => ['2026-08-07T10:00'],
            'european_aqi' => [65],
            'pm2_5' => [31.2],
        ],
    ]);

    expect($signals['air']['actions'])->not->toBeEmpty();
    expect($signals['air']['audience'])->toContain('Schools');
    expect($signals['air_forecast']['peak']['european_aqi'])->toBe(65.0);
    expect($signals['activities']['activities'])->toHaveCount(4);
    expect($signals['pests']['pests'])->toHaveCount(3);
});

test('weather responses are cached and recorded once', function () {
    Cache::forget('weather:1.111:2.222');
    Http::fake([
        'https://api.open-meteo.com/*' => Http::response([
            'current' => [
                'time' => '2026-08-07T10:00',
                'temperature_2m' => 25,
                'relative_humidity_2m' => 60,
                'apparent_temperature' => 26,
                'wind_speed_10m' => 8,
                'weather_code' => 1,
            ],
            'current_units' => ['temperature_2m' => '°C'],
        ]),
    ]);

    $service = app(WeatherService::class);
    $service->getWeather(1.111, 2.222);
    $service->getWeather(1.111, 2.222);

    Http::assertSentCount(1);
    expect(ClimateObservation::where('source', 'open-meteo')->count())->toBe(5);
});

test('the monitoring location endpoint returns active map points', function () {
    ClimateLocation::create([
        'name' => 'Kampala District',
        'type' => 'district',
        'country_code' => 'UG',
        'admin_level' => 'district',
        'external_id' => 'UG-DIST-KLA',
        'latitude' => 0.3476,
        'longitude' => 32.5825,
        'is_active' => true,
    ]);

    ClimateLocation::create([
        'name' => 'Inactive test location',
        'type' => 'facility',
        'latitude' => 1,
        'longitude' => 2,
        'is_active' => false,
    ]);

    $response = $this->getJson('/api/climate-locations');

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Kampala District')
        ->assertJsonCount(1, 'data');
});

test('the national risk endpoint fails safely when the provider is unavailable', function () {
    Cache::forget('uganda:risk-grid:v1');
    Http::fake(fn () => throw new ConnectionException('National request timed out'));

    $response = $this->getJson('/api/national-risk');

    $response->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('cells', [])
        ->assertJsonPath('trends', []);
});

test('the district risk endpoint reports when CHIRPS data is not loaded', function () {
    $response = $this->getJson('/api/district-risk');

    $response->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('source', 'CHIRPS v3');
});

test('the pipeline status endpoint reports national coverage', function () {
    $response = $this->getJson('/api/pipeline-status');

    $response->assertOk()
        ->assertJsonPath('districts', 0)
        ->assertJsonPath('facilities', 0);
});

test('district flood risk remains unavailable without a rainfall baseline', function () {
    $location = ClimateLocation::create([
        'name' => 'Test District', 'type' => 'district', 'external_id' => 'UG-TEST',
        'latitude' => 1, 'longitude' => 2, 'is_active' => true,
    ]);
    $location->observations()->create([
        'source' => 'chirps-v3', 'variable' => 'rainfall_mm', 'value' => 80,
        'unit' => 'mm', 'observed_at' => '2026-08-07', 'quality_flag' => 'observed',
    ]);

    $response = $this->getJson('/api/district-risk');

    $response->assertJsonPath('available', false)
        ->assertJsonPath('districts.0.status', 'awaiting_baseline');
});

test('air quality risk remains unavailable without OpenAQ observations', function () {
    $response = $this->getJson('/api/air-quality-risk');

    $response->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('monitors', []);
});

test('vulnerability endpoint returns incomplete facilities without required inputs', function () {
    ClimateLocation::create([
        'name' => 'Test Health Centre', 'type' => 'facility', 'external_id' => 'FAC-TEST',
        'latitude' => 1, 'longitude' => 2, 'is_active' => true,
    ]);

    $response = $this->getJson('/api/vulnerability');

    $response->assertOk()
        ->assertJsonPath('facilities.0.risk_level', 'incomplete')
        ->assertJsonPath('facilities.0.missing_inputs.0', 'children_served');
});

test('alerts endpoint returns only active alerts', function () {
    ClimateAlert::create([
        'alert_key' => 'test-alert', 'hazard' => 'flood', 'severity' => 'amber',
        'status' => 'active', 'title' => 'Test alert', 'summary' => 'Test summary',
        'recommended_actions' => ['Check local guidance'], 'source' => 'test',
        'triggered_at' => now(), 'last_seen_at' => now(),
    ]);

    $response = $this->getJson('/api/alerts');

    $response->assertOk()->assertJsonPath('alerts.0.title', 'Test alert');
});

test('a responder can acknowledge and complete an alert action', function () {
    $alert = ClimateAlert::create([
        'alert_key' => 'action-alert', 'hazard' => 'heat', 'severity' => 'amber',
        'status' => 'active', 'title' => 'Heat alert', 'summary' => 'Take action',
        'recommended_actions' => ['Provide shade'], 'source' => 'test',
        'triggered_at' => now(), 'last_seen_at' => now(),
    ]);

    $this->postJson('/api/alerts/'.$alert->id.'/action', [
        'status' => 'in_progress', 'actor_name' => 'Facility focal point', 'notes' => 'Shade confirmed.',
    ])->assertOk()->assertJsonPath('action.status', 'in_progress');

    $this->postJson('/api/alerts/'.$alert->id.'/action', [
        'status' => 'completed', 'actor_name' => 'Facility focal point', 'notes' => 'Outdoor activity moved indoors.',
    ])->assertOk()->assertJsonPath('action.status', 'completed');

    expect(ClimateAlertAction::where('climate_alert_id', $alert->id)->value('completed_at'))->not->toBeNull();
    $this->getJson('/api/alerts')->assertJsonPath('alerts.0.action.status', 'completed');
});

test('pilot report summarizes aggregate response and readiness evidence', function () {
    ClimateAlert::create([
        'alert_key' => 'report-heat', 'hazard' => 'heat', 'severity' => 'amber', 'status' => 'active',
        'title' => 'Heat alert', 'summary' => 'Report test', 'recommended_actions' => ['Check shade'],
        'source' => 'test', 'triggered_at' => now(), 'last_seen_at' => now(),
    ]);
    $completed = ClimateAlert::create([
        'alert_key' => 'report-flood', 'hazard' => 'flood', 'severity' => 'red', 'status' => 'resolved',
        'title' => 'Flood alert', 'summary' => 'Report test', 'recommended_actions' => ['Check access'],
        'source' => 'test', 'triggered_at' => now(), 'last_seen_at' => now(), 'resolved_at' => now(),
    ]);
    ClimateAlertAction::create([
        'climate_alert_id' => $completed->id, 'status' => 'completed', 'actor_name' => 'Pilot team',
        'acknowledged_at' => now(), 'completed_at' => now(),
    ]);
    $facility = ClimateLocation::create([
        'name' => 'Report Health Centre', 'type' => 'facility', 'latitude' => 0.3, 'longitude' => 32.5,
        'metadata' => ['candidate_registry' => true], 'is_active' => true,
    ]);
    VulnerabilityAssessment::create([
        'climate_location_id' => $facility->id, 'risk_level' => 'incomplete', 'missing_inputs' => ['children_served'],
        'assessed_at' => now(),
    ]);
    ClimateObservation::create([
        'climate_location_id' => $facility->id, 'source' => 'test-weather', 'variable' => 'temperature',
        'value' => 30, 'unit' => '°C', 'observed_at' => now(),
    ]);

    $this->getJson('/api/reports/pilot?days=30')
        ->assertOk()
        ->assertJsonPath('overview.alerts_total', 2)
        ->assertJsonPath('overview.actions_completed', 1)
        ->assertJsonPath('facilities.candidate', 1)
        ->assertJsonPath('facilities.incomplete_assessments', 1)
        ->assertJsonPath('data_sources.0.source', 'test-weather');
});

test('pilot report page is available from the dashboard', function () {
    $this->get('/dashboard/reports')->assertOk()->assertSee('Pilot evidence report');
});

test('aggregate child health outcomes can be linked to an active location', function () {
    $facility = ClimateLocation::create([
        'name' => 'Pilot Health Centre', 'type' => 'facility', 'latitude' => 0.3, 'longitude' => 32.5, 'is_active' => true,
    ]);

    $response = $this->postJson('/api/health-outcomes', [
        'climate_location_id' => $facility->id,
        'indicator' => 'acute_respiratory_infections',
        'age_group' => 'under_5',
        'period_start' => now()->subDays(7)->toDateString(),
        'period_end' => now()->toDateString(),
        'value' => 24,
        'unit' => 'reported_cases',
        'source' => 'pilot_register',
        'quality_flag' => 'reported',
    ]);

    $response->assertCreated()->assertJsonPath('outcome.indicator', 'acute_respiratory_infections');
    expect(ClimateHealthOutcome::count())->toBe(1);
    $this->getJson('/api/reports/pilot?days=30')->assertJsonPath('health_outcomes.status', 'connected')->assertJsonPath('health_outcomes.records', 1);
});

test('health outcome ingestion rejects child-identifying fields and invalid periods', function () {
    $facility = ClimateLocation::create([
        'name' => 'Pilot Health Centre', 'type' => 'facility', 'latitude' => 0.3, 'longitude' => 32.5, 'is_active' => true,
    ]);

    $this->postJson('/api/health-outcomes', [
        'climate_location_id' => $facility->id, 'indicator' => 'malaria_cases', 'age_group' => 'under_5',
        'period_start' => '2026-08-08', 'period_end' => '2026-08-01', 'value' => 4, 'unit' => 'cases', 'source' => 'register',
        'child_name' => 'Should not be accepted',
    ])->assertUnprocessable()->assertJsonValidationErrors(['period_start']);

    expect(ClimateHealthOutcome::count())->toBe(0);
});

test('child climate risk detects valley relief and combined flood drivers', function () {
    Cache::forget('terrain-profile:11.111:22.222');
    Http::fake([
        'https://api.open-meteo.com/v1/elevation*' => Http::response(['elevation' => [100, 160, 155, 170, 165, 180, 175, 165, 170]]),
    ]);

    $risk = app(ChildClimateRiskService::class)->assess([
        'elevation' => 100,
        'current' => ['temperature_2m' => 28, 'apparent_temperature' => 33, 'relative_humidity_2m' => 80],
        'daily' => ['precipitation_sum' => [80], 'precipitation_probability_max' => [90]],
    ], [
        'current' => ['us_aqi' => 130, 'pm2_5' => 42],
    ], 11.111, 22.222);

    expect($risk['location']['valley_like'])->toBeTrue();
    expect($risk['location']['terrain'])->toBe('Valley-like terrain');
    expect($risk['pathways'][0]['risk']['label'])->toBe('Warning');
});

test('DHIS2 connector reports safe disabled status when not configured', function () {
    $this->getJson('/api/dhis2/status')
        ->assertOk()
        ->assertJsonPath('configured', false)
        ->assertJsonPath('indicators_configured', 0);

    $this->postJson('/api/dhis2/sync', ['period' => '2026-07'])
        ->assertOk()
        ->assertJsonPath('status', 'disabled');
});
