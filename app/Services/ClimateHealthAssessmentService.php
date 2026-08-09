<?php

namespace App\Services;

class ClimateHealthAssessmentService
{
    public function assess(array $weather, array $airQuality): array
    {
        $currentWeather = $weather['current'] ?? [];
        $currentAir = $airQuality['current'] ?? [];

        return [
            'heat' => $this->heatSignal($currentWeather['apparent_temperature'] ?? null),
            'air' => $this->airSignal($currentAir['european_aqi'] ?? null),
            'air_forecast' => $this->airForecast($airQuality),
            'flood' => $this->precipitationSignal($weather['daily']['precipitation_sum'][0] ?? null),
            'activities' => $this->activityOutlook($currentWeather, $currentAir, $weather['daily'] ?? []),
            'pests' => $this->pestOutlook($currentWeather, $weather['daily'] ?? []),
            'disease' => [
                'label' => 'Data preparation',
                'status' => 'secondary',
                'summary' => 'DHIS2 or validated historical health data is required before disease forecasting is activated.',
                'scope' => 'Planned',
            ],
        ];
    }

    private function heatSignal($apparentTemperature): array
    {
        if (! is_numeric($apparentTemperature)) {
            return $this->unavailable('Heat screening unavailable.');
        }

        return match (true) {
            $apparentTemperature < 27 => ['label' => 'Low', 'status' => 'success', 'summary' => 'No immediate heat stress signal.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Continue routine hydration and shade access.'], 'audience' => 'Schools and health facilities'],
            $apparentTemperature < 32 => ['label' => 'Watch', 'status' => 'info', 'summary' => 'Plan hydration and shade for children and outdoor workers.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Confirm drinking water and shaded areas.', 'Limit prolonged outdoor activity during the hottest hours.'], 'audience' => 'Schools and health facilities'],
            $apparentTemperature < 38 => ['label' => 'Warning', 'status' => 'warning', 'summary' => 'Increase heat precautions and monitor vulnerable groups.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Move activities to cooler hours or indoors.', 'Check on infants, children, older people and outdoor workers.', 'Review facility heat-continuity plans.'], 'audience' => 'Schools, communities and health facilities'],
            default => ['label' => 'Severe', 'status' => 'danger', 'summary' => 'Prioritize heat-health action and local clinical guidance.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Activate the heat-health response plan.', 'Suspend non-essential outdoor activities for children.', 'Prepare hydration, cooling and clinical referral capacity.'], 'audience' => 'Schools, communities and health facilities'],
        };
    }

    private function airSignal($aqi): array
    {
        if (! is_numeric($aqi)) {
            return $this->unavailable('Air-quality screening unavailable.');
        }

        return match (true) {
            $aqi <= 20 => ['label' => 'Good', 'status' => 'success', 'summary' => 'Low air-quality concern.', 'scope' => 'Current AQI', 'confidence' => 'moderate', 'actions' => ['Continue routine activities and ventilation.'], 'audience' => 'Schools and health facilities'],
            $aqi <= 40 => ['label' => 'Fair', 'status' => 'info', 'summary' => 'Sensitive people should stay informed.', 'scope' => 'Current AQI', 'confidence' => 'moderate', 'actions' => ['Keep sensitive groups informed and review local conditions.'], 'audience' => 'Schools and health facilities'],
            $aqi <= 60 => ['label' => 'Moderate', 'status' => 'warning', 'summary' => 'Consider reducing prolonged outdoor exposure for sensitive groups.', 'scope' => 'Current AQI', 'confidence' => 'moderate', 'actions' => ['Reduce prolonged outdoor activity for children with respiratory conditions.', 'Check ventilation and keep protective guidance ready.'], 'audience' => 'Schools and health facilities'],
            default => ['label' => 'Elevated', 'status' => 'danger', 'summary' => 'Prioritize protective guidance for children and sensitive groups.', 'scope' => 'Current AQI', 'confidence' => 'moderate', 'actions' => ['Move children’s activities indoors where possible.', 'Advise sensitive people to reduce outdoor exposure.', 'Monitor respiratory complaints and follow clinical guidance.'], 'audience' => 'Schools, communities and health facilities'],
        };
    }

    private function precipitationSignal($precipitation): array
    {
        if (! is_numeric($precipitation)) {
            return $this->unavailable('Precipitation screening unavailable.');
        }

        return match (true) {
            $precipitation < 20 => ['label' => 'Low', 'status' => 'success', 'summary' => 'No heavy-rain signal in the next daily forecast.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Continue routine access and drainage checks.'], 'audience' => 'Communities and health facilities'],
            $precipitation < 50 => ['label' => 'Watch', 'status' => 'info', 'summary' => 'Monitor drainage and local rainfall updates.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Check drainage and access routes.', 'Monitor local rainfall updates.'], 'audience' => 'Communities and health facilities'],
            $precipitation < 100 => ['label' => 'Warning', 'status' => 'warning', 'summary' => 'Heavy rainfall may disrupt services or travel.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Protect medicines, records and essential equipment.', 'Review staff, transport and referral continuity.'], 'audience' => 'Health facilities and local authorities'],
            default => ['label' => 'Severe', 'status' => 'danger', 'summary' => 'Check local flood guidance and protect essential services.', 'scope' => 'Screening', 'confidence' => 'moderate', 'actions' => ['Activate flood-continuity plans.', 'Protect medicines, records and essential equipment.', 'Coordinate safe routes and referral capacity.'], 'audience' => 'Health facilities and local authorities'],
        };
    }

    private function airForecast(array $airQuality): array
    {
        $hourly = $airQuality['hourly'] ?? [];
        $times = $hourly['time'] ?? [];
        $aqi = $hourly['european_aqi'] ?? [];
        $pm25 = $hourly['pm2_5'] ?? [];
        $rows = [];

        foreach (array_slice($times, 0, 24) as $index => $time) {
            if (! is_numeric($aqi[$index] ?? null) && ! is_numeric($pm25[$index] ?? null)) {
                continue;
            }
            $rows[] = [
                'time' => $time,
                'european_aqi' => is_numeric($aqi[$index] ?? null) ? round((float) $aqi[$index], 1) : null,
                'pm2_5' => is_numeric($pm25[$index] ?? null) ? round((float) $pm25[$index], 1) : null,
            ];
        }

        $peak = collect($rows)->sortByDesc(fn ($row) => $row['european_aqi'] ?? -1)->first();

        return [
            'available' => $rows !== [],
            'hours' => $rows,
            'peak' => $peak,
            'source' => 'Open-Meteo Air Quality',
            'confidence' => $rows !== [] ? 'moderate' : 'unavailable',
        ];
    }

    private function activityOutlook(array $weather, array $air, array $daily): array
    {
        $apparent = is_numeric($weather['apparent_temperature'] ?? null) ? (float) $weather['apparent_temperature'] : null;
        $aqi = is_numeric($air['european_aqi'] ?? null) ? (float) $air['european_aqi'] : null;
        $rain = is_numeric($daily['precipitation_sum'][0] ?? null) ? (float) $daily['precipitation_sum'][0] : null;
        $wind = is_numeric($weather['wind_speed_10m'] ?? null) ? (float) $weather['wind_speed_10m'] : null;
        $uv = is_numeric($daily['uv_index_max'][0] ?? null) ? (float) $daily['uv_index_max'][0] : null;

        $heatRisk = $apparent === null ? 0 : ($apparent >= 38 ? 3 : ($apparent >= 32 ? 2 : ($apparent >= 27 ? 1 : 0)));
        $airRisk = $aqi === null ? 0 : ($aqi > 60 ? 3 : ($aqi > 40 ? 2 : ($aqi > 20 ? 1 : 0)));
        $rainRisk = $rain === null ? 0 : ($rain >= 100 ? 3 : ($rain >= 50 ? 2 : ($rain >= 20 ? 1 : 0)));
        $windRisk = $wind === null ? 0 : ($wind >= 45 ? 2 : ($wind >= 30 ? 1 : 0));
        $uvRisk = $uv === null ? 0 : ($uv >= 8 ? 2 : ($uv >= 5 ? 1 : 0));
        $overall = max($heatRisk, $airRisk, $rainRisk, $windRisk, $uvRisk);

        $rating = match ($overall) {
            3 => ['label' => 'High risk', 'status' => 'danger', 'summary' => 'Weather or air conditions require protective planning before outdoor activity.'],
            2 => ['label' => 'Use caution', 'status' => 'warning', 'summary' => 'Outdoor activity is possible with timing, hydration and protective measures.'],
            1 => ['label' => 'Fair', 'status' => 'info', 'summary' => 'Conditions are generally usable; protect sensitive groups and monitor changes.'],
            default => ['label' => 'Favourable', 'status' => 'success', 'summary' => 'No major environmental barrier to routine activities is detected.'],
        };

        return [
            'overall' => $rating,
            'confidence' => $apparent !== null || $aqi !== null ? 'moderate' : 'unavailable',
            'activities' => [
                ['key' => 'school_play', 'title' => 'School play and outdoor learning', 'score' => max($heatRisk, $airRisk, $uvRisk), 'actions' => $this->activityActions(max($heatRisk, $airRisk, $uvRisk), 'children'), 'best_window' => $heatRisk >= 2 ? 'Use cooler morning or late-afternoon hours.' : 'Routine daytime activity is reasonable with water and shade.'],
                ['key' => 'community_mobility', 'title' => 'Community movement and access', 'score' => max($rainRisk, $windRisk), 'actions' => $this->activityActions(max($rainRisk, $windRisk), 'mobility'), 'best_window' => $rainRisk >= 2 ? 'Check routes before travel and avoid flooded crossings.' : 'Routine movement is reasonable; monitor local showers.'],
                ['key' => 'outdoor_work', 'title' => 'Outdoor work and field teams', 'score' => max($heatRisk, $rainRisk, $windRisk), 'actions' => $this->activityActions(max($heatRisk, $rainRisk, $windRisk), 'workers'), 'best_window' => $heatRisk >= 2 ? 'Schedule demanding work during cooler hours.' : 'Use routine rest, water and weather checks.'],
                ['key' => 'sensitive_health', 'title' => 'Children with respiratory or other sensitivities', 'score' => $airRisk, 'actions' => $this->activityActions($airRisk, 'sensitive'), 'best_window' => $airRisk >= 2 ? 'Prefer well-ventilated indoor spaces and reduce prolonged exposure.' : 'Routine activity is reasonable while monitoring symptoms.'],
            ],
            'drivers' => ['apparent_temperature' => $apparent, 'aqi' => $aqi, 'rainfall_mm' => $rain, 'wind_kmh' => $wind, 'uv_index' => $uv],
            'scope' => 'Screening guidance, not a diagnosis or emergency instruction',
        ];
    }

    private function activityActions(int $risk, string $audience): array
    {
        if ($risk >= 3) {
            return match ($audience) {
                'children' => ['Pause non-essential strenuous outdoor activity.', 'Move activities indoors or to shade and provide drinking water.', 'Follow local clinical and safeguarding guidance for symptomatic children.'],
                'mobility' => ['Check access routes and avoid unsafe crossings or exposed travel.', 'Coordinate safe transport and referral routes if services are disrupted.'],
                'workers' => ['Activate heat, rain or wind safety procedures.', 'Provide rest, water and protective equipment; stop work when conditions become unsafe.'],
                default => ['Reduce prolonged exposure and follow clinical advice for symptoms.', 'Keep protective and referral arrangements ready.'],
            };
        }

        if ($risk === 2) {
            return ['Adjust timing and duration of outdoor activity.', 'Keep water, shade and local weather updates available.'];
        }

        return ['Continue routine activity while monitoring local conditions.', 'Keep water, shade and basic protective measures available.'];
    }

    private function pestOutlook(array $weather, array $daily): array
    {
        $temperature = is_numeric($weather['temperature_2m'] ?? null) ? (float) $weather['temperature_2m'] : null;
        $humidity = is_numeric($weather['relative_humidity_2m'] ?? null) ? (float) $weather['relative_humidity_2m'] : null;
        $rain = is_numeric($daily['precipitation_sum'][0] ?? null) ? (float) $daily['precipitation_sum'][0] : null;
        $warm = $temperature !== null && $temperature >= 24 && $temperature <= 34;
        $humid = $humidity !== null && $humidity >= 65;
        $wet = $rain !== null && $rain >= 5;
        $veryWet = $rain !== null && $rain >= 30;

        $mosquitoRisk = ($warm ? 1 : 0) + ($humid ? 1 : 0) + ($wet ? 1 : 0) + ($veryWet ? 1 : 0);
        $indoorRisk = ($humid ? 1 : 0) + ($temperature !== null && $temperature >= 24 ? 1 : 0) + ($wet ? 1 : 0);
        $outdoorRisk = ($warm ? 1 : 0) + ($humid ? 1 : 0) + ($wet ? 1 : 0);

        return [
            'available' => $temperature !== null || $humidity !== null || $rain !== null,
            'drivers' => ['temperature_c' => $temperature, 'humidity_percent' => $humidity, 'rainfall_mm' => $rain],
            'pests' => [
                ['key' => 'mosquitos', 'title' => 'Mosquitos', 'risk' => $this->pestLevel($mosquitoRisk, 4), 'summary' => 'Mosquito activity screening based on warmth, humidity and recent rain.', 'actions' => ['Remove standing water around homes, schools and facilities.', 'Use nets, screens and locally approved protection.', 'Follow local clinical guidance for fever or other symptoms.']],
                ['key' => 'indoor_pests', 'title' => 'Indoor Pests', 'risk' => $this->pestLevel($indoorRisk, 3), 'summary' => 'Indoor pest screening based on warm, humid conditions and rain.', 'actions' => ['Keep food and waste covered and improve ventilation.', 'Check damp areas, stores and facility rooms.', 'Record and escalate facility sanitation concerns.']],
                ['key' => 'outdoor_pests', 'title' => 'Outdoor Pests', 'risk' => $this->pestLevel($outdoorRisk, 3), 'summary' => 'Outdoor pest screening based on conditions that support insect activity.', 'actions' => ['Use protective clothing and repellents where appropriate.', 'Check play areas and field routes before activities.', 'Share prevention messages with community teams.']],
            ],
            'scope' => 'Environmental screening only; not a disease diagnosis or vector surveillance result.',
        ];
    }

    private function pestLevel(int $score, int $maximum): array
    {
        $ratio = $maximum > 0 ? $score / $maximum : 0;

        return $ratio >= .8
            ? ['label' => 'Extreme', 'status' => 'danger', 'score' => 3]
            : ($ratio >= .55
                ? ['label' => 'High', 'status' => 'warning', 'score' => 2]
                : ($ratio >= .3 ? ['label' => 'Moderate', 'status' => 'info', 'score' => 1] : ['label' => 'Low', 'status' => 'success', 'score' => 0]));
    }

    private function unavailable(string $summary): array
    {
        return ['label' => 'Unavailable', 'status' => 'secondary', 'summary' => $summary, 'scope' => 'Not available'];
    }
}
