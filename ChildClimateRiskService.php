<?php

namespace App\Services;

class ChildClimateRiskService
{
    public function __construct(private TerrainProfileService $terrain) {}

    public function assess(array $weather, array $airQuality, float $latitude, float $longitude): array
    {
        $current = $weather['current'] ?? [];
        $daily = $weather['daily'] ?? [];
        $air = $airQuality['current'] ?? [];
        $altitude = is_numeric($weather['elevation'] ?? null) ? (float) $weather['elevation'] : null;
        $temperature = is_numeric($current['temperature_2m'] ?? null) ? (float) $current['temperature_2m'] : null;
        $apparent = is_numeric($current['apparent_temperature'] ?? null) ? (float) $current['apparent_temperature'] : null;
        $rain = is_numeric($daily['precipitation_sum'][0] ?? null) ? (float) $daily['precipitation_sum'][0] : null;
        $rainProbability = is_numeric($daily['precipitation_probability_max'][0] ?? null) ? (float) $daily['precipitation_probability_max'][0] : null;
        $humidity = is_numeric($current['relative_humidity_2m'] ?? null) ? (float) $current['relative_humidity_2m'] : null;
        $aqi = is_numeric($air['us_aqi'] ?? null) ? (float) $air['us_aqi'] : (is_numeric($air['european_aqi'] ?? null) ? (float) $air['european_aqi'] : null);
        $pm25 = is_numeric($air['pm2_5'] ?? null) ? (float) $air['pm2_5'] : null;
        $water = $this->nearestWaterBody($latitude, $longitude);
        $terrain = ($weather['elevation'] ?? null) !== null ? $this->terrain->profile($latitude, $longitude, $altitude) : ['valley_like' => false, 'relief_m' => null, 'neighbor_average_m' => null, 'confidence' => 'low'];
        $lowland = ($altitude !== null && $altitude <= 300) || ($terrain['valley_like'] ?? false);
        $heavyRain = ($rain !== null && $rain >= 50) || ($rainProbability !== null && $rainProbability >= 70 && ($rain ?? 0) >= 20);
        $floodScore = ($lowland ? 1 : 0) + ($heavyRain ? 2 : (($rain !== null && $rain >= 20) ? 1 : 0)) + ($water['distance_km'] <= 10 ? 1 : 0);
        $respiratoryScore = ($aqi !== null && $aqi > 100 ? 2 : (($aqi !== null && $aqi > 50) ? 1 : 0)) + ($pm25 !== null && $pm25 > 35 ? 1 : 0);
        $malariaScore = (($temperature !== null && $temperature >= 20 && $temperature <= 32) ? 1 : 0) + (($humidity !== null && $humidity >= 60) ? 1 : 0) + (($rain !== null && $rain >= 5) ? 1 : 0) + ($water['distance_km'] <= 10 ? 1 : 0);
        $heatScore = $apparent !== null && $apparent >= 38 ? 3 : ($apparent !== null && $apparent >= 32 ? 2 : ($apparent !== null && $apparent >= 27 ? 1 : 0));
        $overall = max($floodScore, $respiratoryScore, $malariaScore, $heatScore);

        return [
            'overall' => $this->level($overall),
            'location' => ['latitude' => $latitude, 'longitude' => $longitude, 'altitude_m' => $altitude, 'terrain' => ($terrain['valley_like'] ?? false) ? 'Valley-like terrain' : ($lowland ? 'Lowland terrain' : 'Higher-ground terrain'), 'valley_like' => $terrain['valley_like'] ?? false, 'relief_m' => $terrain['relief_m'] ?? null, 'neighbor_average_m' => $terrain['neighbor_average_m'] ?? null, 'terrain_confidence' => $terrain['confidence'] ?? 'low', 'nearest_water_body' => $water['name'], 'water_distance_km' => $water['distance_km']],
            'drivers' => ['rainfall_mm' => $rain, 'rain_probability' => $rainProbability, 'air_quality_index' => $aqi, 'pm2_5' => $pm25, 'apparent_temperature_c' => $apparent],
            'pathways' => [
                ['name' => 'Flooding and unsafe access', 'risk' => $this->level($floodScore), 'why' => $this->floodReason($lowland, $heavyRain, $water)],
                ['name' => 'Malaria and mosquito exposure', 'risk' => $this->level($malariaScore), 'why' => 'Warmth, moisture, rainfall and nearby water can support mosquito breeding conditions.'],
                ['name' => 'Respiratory illness aggravation', 'risk' => $this->level($respiratoryScore), 'why' => 'Air pollution can aggravate breathing problems, especially in children with existing sensitivities.'],
                ['name' => 'Heat stress and dehydration', 'risk' => $this->level($heatScore), 'why' => 'High apparent temperature increases dehydration and heat-illness risk during prolonged activity.'],
            ],
            'actions' => $this->actions($overall, $floodScore, $respiratoryScore),
            'explainability' => $this->explainability($altitude, $terrain, $rain, $rainProbability, $aqi, $pm25, $water, $overall, $floodScore, $respiratoryScore),
            'scope' => 'Child-health screening support; not a diagnosis, flood warning or clinical prediction.',
        ];
    }

    private function level(int $score): array
    {
        return match (true) {
            $score >= 4 => ['label' => 'Severe', 'status' => 'danger', 'score' => 3],
            $score >= 3 => ['label' => 'Warning', 'status' => 'warning', 'score' => 2],
            $score >= 1 => ['label' => 'Watch', 'status' => 'info', 'score' => 1],
            default => ['label' => 'Low', 'status' => 'success', 'score' => 0],
        };
    }

    private function floodReason(bool $lowland, bool $heavyRain, array $water): string
    {
        $reasons = [];
        if ($lowland) $reasons[] = 'low altitude';
        if ($heavyRain) $reasons[] = 'heavy or likely rainfall';
        if ($water['distance_km'] <= 10) $reasons[] = 'near '.$water['name'];

        return $reasons === [] ? 'No strong combined flood-screening driver is detected.' : 'Combined drivers: '.implode(', ', $reasons).'.';
    }

    private function actions(int $overall, int $flood, int $respiratory): array
    {
        $actions = [];
        if ($flood >= 3) $actions[] = 'Check drainage, safe routes, latrines and referral access before rain intensifies.';
        if ($respiratory >= 2) $actions[] = 'Reduce prolonged outdoor exposure for sensitive children and improve indoor ventilation.';
        if ($overall >= 2) $actions[] = 'Notify the facility or school focal point and review child safeguarding arrangements.';
        if ($actions === []) $actions[] = 'Continue routine prevention, hydration and local weather monitoring.';

        return $actions;
    }

    private function explainability(?float $altitude, array $terrain, ?float $rain, ?float $rainProbability, ?float $aqi, ?float $pm25, array $water, int $overall, int $flood, int $respiratory): array
    {
        $drivers = [];
        $drivers[] = ['title' => 'Exact location and terrain', 'detail' => ($terrain['valley_like'] ?? false) ? 'Nearby elevations are higher than this point, creating a valley-like screening signal.' : 'No strong valley-like relief signal was detected.', 'value' => $altitude !== null ? number_format($altitude, 0).' m altitude' : 'Altitude unavailable', 'status' => $altitude !== null ? 'observed' : 'unavailable'];
        $drivers[] = ['title' => 'Rainfall pressure', 'detail' => $rain !== null ? 'Forecast rainfall is being compared with terrain and access conditions.' : 'Rainfall forecast is unavailable.', 'value' => $rain !== null ? $rain.' mm'.($rainProbability !== null ? ' · '.$rainProbability.'% probability' : '') : 'Unavailable', 'status' => $rain !== null ? 'forecast' : 'unavailable'];
        $drivers[] = ['title' => 'Water proximity', 'detail' => 'The nearest reference water body is used as a contextual flood and mosquito-exposure factor.', 'value' => $water['name'].' · '.$water['distance_km'].' km', 'status' => 'context'];
        $drivers[] = ['title' => 'Air and child sensitivity', 'detail' => 'AQI and PM2.5 are screened for respiratory aggravation in children and sensitive groups.', 'value' => $aqi !== null ? 'AQI '.$aqi.($pm25 !== null ? ' · PM2.5 '.$pm25.' µg/m³' : '') : 'Air quality unavailable', 'status' => $aqi !== null ? 'observed' : 'unavailable'];

        return [
            'chain' => [
                ['step' => 1, 'title' => 'Location', 'detail' => 'Exact coordinates are used instead of applying a whole-district average.'],
                ['step' => 2, 'title' => 'Drivers', 'detail' => count(array_filter($drivers, fn (array $driver) => $driver['status'] !== 'unavailable')).' environmental factors contributed to this screening result.'],
                ['step' => 3, 'title' => 'Child-health pathways', 'detail' => $flood >= 3 ? 'Flood access and water-related pathways are prioritised.' : ($respiratory >= 2 ? 'Respiratory exposure is prioritised for sensitive children.' : 'Heat, mosquito and respiratory pathways are screened together.'),
                ],
                ['step' => 4, 'title' => 'Action', 'detail' => $overall >= 2 ? 'Protective action is recommended before conditions intensify.' : 'Routine prevention and monitoring are recommended.'],
            ],
            'drivers' => $drivers,
            'evidence_status' => $altitude !== null || $rain !== null || $aqi !== null ? 'Live environmental screening' : 'Awaiting live environmental data',
        ];
    }

    private function nearestWaterBody(float $latitude, float $longitude): array
    {
        $waterBodies = [['name' => 'Lake Victoria', 'latitude' => -0.2, 'longitude' => 32.8], ['name' => 'Lake Kyoga', 'latitude' => 1.4, 'longitude' => 32.8], ['name' => 'Lake Albert', 'latitude' => 1.7, 'longitude' => 30.9], ['name' => 'Nile corridor', 'latitude' => 2.3, 'longitude' => 32.3]];
        $nearest = collect($waterBodies)->map(fn (array $water) => $water + ['distance_km' => $this->distanceKm($latitude, $longitude, $water['latitude'], $water['longitude'])])->sortBy('distance_km')->first();

        return ['name' => $nearest['name'], 'distance_km' => round($nearest['distance_km'], 1)];
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
