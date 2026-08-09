<?php

namespace App\Services;

use App\Models\ClimateAlert;
use App\Models\ClimateLocation;

class AlertEngineService
{
    public function __construct(private UgandaRiskGridService $nationalRisk, private DistrictFloodRiskService $districtRisk, private OpenAqAnomalyService $airQuality, private VulnerabilityScoringService $vulnerability) {}

    public function generate(): int
    {
        $seen = [];
        $national = $this->nationalRisk->getNationalRisk();
        $severeCells = collect($national['cells'] ?? [])->whereIn('flood_level', ['warning', 'severe']);
        $heatCells = collect($national['cells'] ?? [])->whereIn('heat_level', ['warning', 'severe']);
        if ($heatCells->isNotEmpty()) {
            $this->upsert('national-heat', null, 'heat', $heatCells->contains('heat_level', 'severe') ? 'red' : 'amber', 'National heat-health signal', 'Several national screening cells show elevated apparent-temperature risk.', ['Confirm water and shade at schools and facilities.', 'Move children’s activities to cooler hours or indoors.', 'Review heat-health and surge-continuity plans.'], 'Open-Meteo national grid', $seen, ['audience' => 'Schools, communities and health facilities', 'action_window' => 'Next 24 hours', 'confidence' => 'moderate']);
        }
        if ($severeCells->isNotEmpty()) {
            $this->upsert('national-heavy-rain', null, 'flood', $severeCells->contains('flood_level', 'severe') ? 'red' : 'amber', 'National heavy-rain screening', 'Several national screening cells show elevated heavy-rain potential.', ['Review district readiness and local flood guidance.', 'Check continuity plans for schools and health facilities.'], 'Open-Meteo national grid', $seen, ['audience' => 'Schools, communities and health facilities', 'action_window' => 'Next 24 hours', 'confidence' => 'moderate']);
        }
        foreach ($this->districtRisk->getDistrictRisk()['districts'] as $district) {
            if (! in_array($district['risk_level'] ?? null, ['warning', 'severe'], true)) {
                continue;
            }
            $location = ClimateLocation::where('external_id', $district['external_id'])->first();
            $this->upsert('flood-'.$district['external_id'], $location, 'flood', $district['risk_level'] === 'severe' ? 'red' : 'amber', 'District flood-risk signal', "Rainfall anomaly detected for {$district['name']}.", ['Check drainage and access routes.', 'Protect medicines, records and essential equipment.'], 'CHIRPS v3', $seen);
        }
        foreach ($this->airQuality->summarize()['monitors'] as $monitor) {
            if (! in_array($monitor['level'] ?? null, ['warning', 'severe'], true)) {
                continue;
            }
            $location = ClimateLocation::where('name', $monitor['name'])->where('type', 'air-monitor')->first();
            $this->upsert('air-'.$monitor['name'], $location, 'air-quality', $monitor['level'] === 'severe' ? 'red' : 'amber', 'Air-quality anomaly signal', "PM2.5 is above the recent baseline near {$monitor['name']}.", ['Reduce prolonged outdoor exposure for sensitive groups.', 'Share trusted protective guidance with schools and communities.', 'Monitor respiratory complaints and follow clinical guidance.'], 'OpenAQ', $seen, ['audience' => 'Schools, communities and health facilities', 'action_window' => 'Now through the next 24 hours', 'confidence' => 'moderate', 'monitor' => $monitor['name']]);
        }
        foreach (ClimateLocation::where('type', 'facility')->where('is_active', true)->get() as $facility) {
            $assessment = $this->vulnerability->assessFacility($facility);
            if (! in_array($assessment->risk_level, ['warning', 'severe'], true)) {
                continue;
            }
            $this->upsert('facility-'.$facility->id, $facility, 'facility-vulnerability', $assessment->risk_level === 'severe' ? 'red' : 'amber', 'Facility vulnerability signal', "Screening vulnerability is elevated for {$facility->name}.", ['Verify missing inputs with the facility focal point.', 'Review continuity and surge plans.'], 'THRIVE screening-v1', $seen);
        }
        ClimateAlert::where('status', 'active')->whereNotIn('alert_key', $seen)->update(['status' => 'resolved', 'resolved_at' => now()]);

        return count($seen);
    }

    private function upsert(string $key, ?ClimateLocation $location, string $hazard, string $severity, string $title, string $summary, array $actions, string $source, array &$seen, array $metadata = []): void
    {
        $seen[] = $key;
        ClimateAlert::updateOrCreate(['alert_key' => $key], ['climate_location_id' => $location?->id, 'hazard' => $hazard, 'severity' => $severity, 'status' => 'active', 'title' => $title, 'summary' => $summary, 'recommended_actions' => $actions, 'source' => $source, 'triggered_at' => now(), 'last_seen_at' => now(), 'metadata' => $metadata]);
    }
}
