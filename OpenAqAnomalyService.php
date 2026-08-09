<?php

namespace App\Services;

use App\Models\ClimateLocation;

class OpenAqAnomalyService
{
    public function summarize(): array
    {
        $monitors = ClimateLocation::where('type', 'air-monitor')->where('is_active', true)->get();
        $results = $monitors->map(function (ClimateLocation $monitor) {
            $observations = $monitor->observations()
                ->where('source', 'openaq')
                ->where('variable', 'pm2_5')
                ->latest('observed_at')
                ->limit(31)
                ->get();
            $current = $observations->first();
            $baselineValues = $observations->skip(1)->pluck('value')->filter(fn ($value) => is_numeric($value));

            if (! $current || $baselineValues->count() < 7) {
                return ['name' => $monitor->name, 'status' => 'awaiting_baseline'];
            }

            $baseline = $baselineValues->average();
            $anomaly = $baseline > 0 ? (($current->value - $baseline) / $baseline) * 100 : 0;

            return [
                'name' => $monitor->name,
                'status' => 'available',
                'pm2_5' => round($current->value, 2),
                'baseline_pm2_5' => round($baseline, 2),
                'anomaly_percent' => round($anomaly, 1),
                'level' => match (true) {
                    $anomaly < 25 => 'low',
                    $anomaly < 50 => 'watch',
                    $anomaly < 100 => 'warning',
                    default => 'severe',
                },
                'observed_at' => $current->observed_at?->toIso8601String(),
            ];
        })->values();

        return [
            'available' => $results->contains(fn ($result) => $result['status'] === 'available'),
            'monitors' => $results,
        ];
    }
}
