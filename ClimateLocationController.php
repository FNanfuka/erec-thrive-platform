<?php

namespace App\Http\Controllers;

use App\Models\ClimateAlert;
use App\Models\ClimateAlertAction;
use App\Models\ClimateHealthOutcome;
use App\Models\ClimateLocation;
use App\Models\ClimateObservation;
use App\Models\ClimateProviderJob;
use App\Models\VulnerabilityAssessment;
use App\Services\AirQualityService;
use App\Services\DistrictFloodRiskService;
use App\Services\OpenAqAnomalyService;
use App\Services\UgandaRiskGridService;
use App\Services\VulnerabilityScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ClimateLocationController extends Controller
{
    public function nationalRisk(UgandaRiskGridService $riskGrid): JsonResponse
    {
        return response()->json($riskGrid->getNationalRisk());
    }

    public function districtRisk(DistrictFloodRiskService $floodRisk): JsonResponse
    {
        return response()->json($floodRisk->getDistrictRisk());
    }

    public function pipelineStatus(): JsonResponse
    {
        $facilities = ClimateLocation::where('type', 'facility')->where('is_active', true);

        return response()->json([
            'districts' => ClimateLocation::where('type', 'district')->where('is_active', true)->count(),
            'facilities' => (clone $facilities)->count(),
            'facility_registry' => [
                'total' => (clone $facilities)->count(),
                'candidate' => (clone $facilities)->whereJsonContains('metadata->candidate_registry', true)->count(),
                'verified' => (clone $facilities)->where(function ($query) {
                    $query->whereNull('metadata->candidate_registry')
                        ->orWhere('metadata->candidate_registry', false);
                })->count(),
            ],
            'chirps' => ClimateProviderJob::where('provider', 'chirps-v3')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'updated_at' => now()->toIso8601String(),
            'openaq' => config('services.openaq.key') ? 'configured' : 'not_configured',
        ]);
    }

    public function airQualityRisk(OpenAqAnomalyService $anomalies): JsonResponse
    {
        return response()->json($anomalies->summarize());
    }

    public function airQualityMap(AirQualityService $airQuality): JsonResponse
    {
        $locations = [
            ['name' => 'Kampala', 'latitude' => 0.3476, 'longitude' => 32.5825],
            ['name' => 'Jinja', 'latitude' => 0.4478, 'longitude' => 33.2026],
            ['name' => 'Mbarara', 'latitude' => -0.6072, 'longitude' => 30.6545],
            ['name' => 'Fort Portal', 'latitude' => 0.6710, 'longitude' => 30.2750],
            ['name' => 'Gulu', 'latitude' => 2.7724, 'longitude' => 32.2881],
            ['name' => 'Arua', 'latitude' => 3.0303, 'longitude' => 30.9100],
            ['name' => 'Soroti', 'latitude' => 1.7150, 'longitude' => 33.6111],
            ['name' => 'Buliisa', 'latitude' => 2.1177, 'longitude' => 31.4116],
        ];

        $points = collect($locations)->map(function (array $location) use ($airQuality) {
            $data = $airQuality->getAirQuality($location['latitude'], $location['longitude']);
            $current = $data['current'] ?? [];

            return $location + [
                'aqi' => is_numeric($current['european_aqi'] ?? null) ? round((float) $current['european_aqi'], 1) : null,
                'pm2_5' => is_numeric($current['pm2_5'] ?? null) ? round((float) $current['pm2_5'], 1) : null,
                'label' => $airQuality->aqiDescription($current['european_aqi'] ?? null)['label'],
            ];
        })->values();

        return response()->json(['available' => $points->contains(fn (array $point) => $point['aqi'] !== null), 'source' => 'Open-Meteo Air Quality', 'points' => $points]);
    }

    public function vulnerability(VulnerabilityScoringService $scoring): JsonResponse
    {
        $facilities = ClimateLocation::where('type', 'facility')
            ->where('is_active', true)
            ->get()
            ->map(function ($facility) use ($scoring) {
                try {
                    $assessment = $scoring->assessFacility($facility);
                } catch (\Throwable $exception) {
                    report($exception);

                    return [
                        'name' => $facility->name,
                        'latitude' => $facility->latitude,
                        'longitude' => $facility->longitude,
                        'source' => data_get($facility->metadata, 'source', 'Unknown'),
                        'registry_status' => data_get($facility->metadata, 'candidate_registry') === true ? 'candidate' : 'verified',
                        'score' => null,
                        'risk_level' => 'incomplete',
                        'components' => [],
                        'missing_inputs' => ['assessment_unavailable'],
                        'recommended_actions' => ['Verify facility registry and vulnerability inputs before operational use.'],
                    ];
                }

                return [
                    'name' => $facility->name,
                    'latitude' => $facility->latitude,
                    'longitude' => $facility->longitude,
                    'source' => data_get($facility->metadata, 'source', 'Unknown'),
                    'registry_status' => data_get($facility->metadata, 'candidate_registry') === true ? 'candidate' : 'verified',
                    'score' => $assessment->score,
                    'risk_level' => $assessment->risk_level,
                    'components' => $assessment->components,
                    'missing_inputs' => $assessment->missing_inputs,
                    'recommended_actions' => $this->facilityActions($assessment->risk_level, $assessment->missing_inputs),
                ];
            })->values();

        return response()->json(['facilities' => $facilities]);
    }

    private function facilityActions(string $riskLevel, array $missingInputs): array
    {
        if ($missingInputs !== []) {
            return ['Verify missing facility inputs with the facility focal point.'];
        }

        return match ($riskLevel) {
            'severe' => ['Activate facility continuity and surge plans.', 'Confirm water, power, medicines and referral capacity.', 'Prioritize continuity of child health services.'],
            'warning' => ['Review access, staffing and essential supplies.', 'Confirm protective measures for children and sensitive groups.'],
            'watch' => ['Monitor local signals and check the facility preparedness checklist.'],
            default => ['Continue routine preparedness checks and keep action contacts current.'],
        };
    }

    public function alerts(): JsonResponse
    {
        return response()->json(['alerts' => ClimateAlert::with(['location', 'action'])->where('status', 'active')->latest('last_seen_at')->get()]);
    }

    public function updateAlertAction(Request $request, ClimateAlert $alert): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:acknowledged,in_progress,completed'],
            'actor_name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $action = ClimateAlertAction::updateOrCreate(
            ['climate_alert_id' => $alert->id],
            [
                'status' => $data['status'],
                'actor_name' => trim($data['actor_name']),
                'notes' => $data['notes'] ?? null,
                'acknowledged_at' => $data['status'] === 'acknowledged'
                    ? now()
                    : ($alert->action?->acknowledged_at ?? now()),
                'completed_at' => $data['status'] === 'completed' ? now() : null,
            ]
        );

        return response()->json(['action' => $action->fresh()]);
    }

    public function storeHealthOutcome(Request $request): JsonResponse
    {
        $data = $request->validate([
            'climate_location_id' => ['required', 'integer', 'exists:climate_locations,id'],
            'indicator' => ['required', 'string', 'max:120'],
            'age_group' => ['required', 'in:under_5,5_14,all_children,all_ages'],
            'period_start' => ['required', 'date', 'before_or_equal:period_end'],
            'period_end' => ['required', 'date', 'before_or_equal:today'],
            'value' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:50'],
            'source' => ['required', 'string', 'max:120'],
            'quality_flag' => ['nullable', 'in:reported,verified,estimated'],
            'metadata' => ['nullable', 'array'],
        ]);

        $location = ClimateLocation::whereKey($data['climate_location_id'])->where('is_active', true)->first();
        if ($location === null) {
            return response()->json(['message' => 'Health outcomes can only be linked to active monitoring locations.'], 422);
        }

        $outcome = ClimateHealthOutcome::create([
            ...$data,
            'quality_flag' => $data['quality_flag'] ?? 'reported',
        ]);

        return response()->json(['outcome' => $outcome->load('location')], 201);
    }

    public function pilotReport(Request $request): JsonResponse
    {
        $days = min(365, max(7, (int) $request->query('days', 30)));
        $from = now()->subDays($days);
        $alerts = ClimateAlert::with('action')->where('created_at', '>=', $from)->latest()->get();
        $actionAlerts = $alerts->filter(fn (ClimateAlert $alert) => $alert->action !== null);
        $completedActions = $actionAlerts->filter(fn (ClimateAlert $alert) => $alert->action?->status === 'completed')->count();
        $facilities = ClimateLocation::where('type', 'facility')->where('is_active', true)->get();
        $facilityIds = $facilities->pluck('id');
        $assessments = $facilityIds->isEmpty() ? collect() : VulnerabilityAssessment::whereIn('climate_location_id', $facilityIds)->get()->keyBy('climate_location_id');
        $observations = ClimateObservation::where('observed_at', '>=', $from)->get();
        $healthOutcomes = ClimateHealthOutcome::where('period_end', '>=', $from->toDateString())
            ->where('period_start', '<=', now()->toDateString())
            ->get();

        $sources = $observations->groupBy('source')->map(function ($rows, $source) {
            $latest = $rows->sortByDesc('observed_at')->first()?->observed_at;
            $hours = $latest ? now()->diffInHours($latest) : null;

            return ['source' => $source, 'observations' => $rows->count(), 'latest_observed_at' => $latest?->toIso8601String(), 'freshness_hours' => $hours, 'status' => $hours === null ? 'unavailable' : ($hours <= 24 ? 'fresh' : ($hours <= 72 ? 'stale' : 'outdated'))];
        })->values();

        $hazards = $alerts->groupBy('hazard')->map(function ($rows, $hazard) {
            return ['hazard' => $hazard, 'total' => $rows->count(), 'active' => $rows->where('status', 'active')->count(), 'resolved' => $rows->where('status', 'resolved')->count(), 'actions_completed' => $rows->filter(fn (ClimateAlert $alert) => $alert->action?->status === 'completed')->count()];
        })->values();

        $incompleteFacilities = $facilities->filter(fn (ClimateLocation $facility) => $assessments->get($facility->id)?->risk_level === 'incomplete')->count();
        $highRiskFacilities = $assessments->whereIn('risk_level', ['warning', 'severe'])->count();
        $facilityReadiness = $facilities->count() > 0 ? round((($facilities->count() - $incompleteFacilities) / $facilities->count()) * 100, 1) : 0;

        return response()->json([
            'period' => ['days' => $days, 'from' => $from->toDateString(), 'to' => now()->toDateString(), 'generated_at' => now()->toIso8601String()],
            'overview' => [
                'alerts_total' => $alerts->count(), 'alerts_active' => $alerts->where('status', 'active')->count(), 'alerts_resolved' => $alerts->where('status', 'resolved')->count(),
                'actions_recorded' => $actionAlerts->count(), 'actions_completed' => $completedActions,
                'action_completion_rate' => $actionAlerts->count() > 0 ? round(($completedActions / $actionAlerts->count()) * 100, 1) : 0,
                'response_coverage' => $alerts->count() > 0 ? round(($actionAlerts->count() / $alerts->count()) * 100, 1) : 0,
            ],
            'hazards' => $hazards,
            'facilities' => [
                'total' => $facilities->count(),
                'verified' => $facilities->reject(fn (ClimateLocation $facility) => data_get($facility->metadata, 'candidate_registry') === true)->count(),
                'candidate' => $facilities->filter(fn (ClimateLocation $facility) => data_get($facility->metadata, 'candidate_registry') === true)->count(),
                'incomplete_assessments' => $incompleteFacilities, 'high_risk' => $highRiskFacilities, 'readiness_rate' => $facilityReadiness,
            ],
            'data_sources' => $sources,
            'evidence_gaps' => [
                ['title' => 'Health outcome linkage', 'detail' => $healthOutcomes->isEmpty() ? 'No aggregate child-health outcome feed is connected yet.' : $healthOutcomes->count().' aggregate outcome record(s) are linked; connect a validated DHIS2 or facility feed next.'],
                ['title' => 'Action outcome sample', 'detail' => 'Alert actions are captured; pilot teams still need to record baseline and follow-up outcomes.'],
                ['title' => 'Facility registry completeness', 'detail' => $incompleteFacilities.' active facility assessment(s) still need required inputs.'],
                ['title' => 'Local validation', 'detail' => 'Risk scores are screening signals and require district and facility validation during the pilot.'],
            ],
            'health_outcomes' => [
                'status' => $healthOutcomes->isEmpty() ? 'not_connected' : 'connected',
                'records' => $healthOutcomes->count(),
                'indicators' => $healthOutcomes->pluck('indicator')->unique()->values(),
                'locations' => $healthOutcomes->pluck('climate_location_id')->unique()->count(),
                'latest_period_end' => $healthOutcomes->max('period_end')?->toDateString(),
                'sources' => $healthOutcomes->pluck('source')->unique()->values(),
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $locations = ClimateLocation::query()
            ->where('is_active', true)
            ->with(['observations' => fn ($query) => $query->latest('observed_at')->limit(20)])
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (ClimateLocation $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type,
                'admin_level' => $location->admin_level,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'observations' => $location->observations->map(fn ($observation) => [
                    'source' => $observation->source,
                    'variable' => $observation->variable,
                    'value' => $observation->value,
                    'unit' => $observation->unit,
                    'observed_at' => $observation->observed_at?->toIso8601String(),
                ])->values(),
            ]);

        return response()->json(['data' => $locations]);
    }

    public function searchLocations(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) return response()->json(['data' => []]);

        $local = ClimateLocation::where('is_active', true)->where(function ($builder) use ($query) {
            $builder->where('name', 'like', '%'.$query.'%')->orWhere('external_id', 'like', '%'.$query.'%');
        })->limit(8)->get()->map(fn (ClimateLocation $location) => ['name' => $location->name, 'subtitle' => ucfirst($location->type).' · THRIVE monitored location', 'type' => $location->type, 'latitude' => $location->latitude, 'longitude' => $location->longitude]);

        $key = 'geocode:'.md5(strtolower($query));
        $remote = Cache::remember($key, now()->addHours(12), function () use ($query) {
            try {
                return Http::connectTimeout(4)->timeout(8)->get('https://geocoding-api.open-meteo.com/v1/search', ['name' => $query, 'count' => 8, 'language' => 'en', 'format' => 'json'])->json('results', []);
            } catch (\Throwable) {
                return [];
            }
        });
        $places = collect($remote)->map(fn (array $place) => ['name' => $place['name'] ?? 'Unknown place', 'subtitle' => collect([$place['admin1'] ?? null, $place['country'] ?? null])->filter()->implode(', '), 'type' => 'place', 'latitude' => (float) $place['latitude'], 'longitude' => (float) $place['longitude']]);

        return response()->json(['data' => $local->concat($places)->unique(fn (array $place) => $place['name'].'|'.$place['latitude'].'|'.$place['longitude'])->values()]);
    }
}
