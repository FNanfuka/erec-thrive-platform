<?php

namespace App\Services;

use App\Models\ClimateLocation;
use App\Models\ClimateRainfallBaseline;

class DistrictFloodRiskService
{
    public function getDistrictRisk(): array
    {
        $districts = ClimateLocation::query()
            ->where('type', 'district')
            ->where('is_active', true)
            ->get();

        $results = $districts->map(function (ClimateLocation $district) {
            $observation = $district->observations()
                ->where('source', 'chirps-v3')
                ->where('variable', 'rainfall_mm')
                ->latest('observed_at')
                ->first();

            if (! $observation) {
                return [
                    'external_id' => $district->external_id,
                    'name' => $district->name,
                    'status' => 'unavailable',
                ];
            }

            $baseline = (float) ($observation->metadata['baseline_mm'] ?? 0);
            if ($baseline <= 0) {
                $baseline = (float) (ClimateRainfallBaseline::query()
                    ->where('climate_location_id', $district->id)
                    ->where('source', 'chirps-v3')
                    ->where('month', $observation->observed_at->month)
                    ->value('mean_mm') ?? 0);
            }
            $rainfall = (float) $observation->value;
            if ($baseline <= 0) {
                return [
                    'external_id' => $district->external_id,
                    'name' => $district->name,
                    'status' => 'awaiting_baseline',
                    'rainfall_mm' => round($rainfall, 1),
                    'observed_at' => $observation->observed_at?->toDateString(),
                ];
            }
            $anomaly = $baseline > 0 ? (($rainfall - $baseline) / $baseline) * 100 : null;
            $score = min(100, max(0, ($anomaly ?? 0) + (($district->drainage_score ?? 0) * 30)));

            return [
                'external_id' => $district->external_id,
                'name' => $district->name,
                'status' => 'available',
                'rainfall_mm' => round($rainfall, 1),
                'baseline_mm' => round($baseline, 1),
                'anomaly_percent' => $anomaly === null ? null : round($anomaly, 1),
                'risk_score' => round($score, 1),
                'risk_level' => $this->riskLevel($score),
                'observed_at' => $observation->observed_at?->toDateString(),
            ];
        })->values();

        return [
            'available' => $results->contains(fn ($district) => $district['status'] === 'available'),
            'source' => 'CHIRPS v3',
            'districts' => $results,
        ];
    }

    private function riskLevel(float $score): string
    {
        return match (true) {
            $score < 20 => 'low',
            $score < 50 => 'watch',
            $score < 80 => 'warning',
            default => 'severe',
        };
    }
}
