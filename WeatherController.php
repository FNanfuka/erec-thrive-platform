<?php

namespace App\Http\Controllers;

use App\Services\AirQualityService;
use App\Services\ChildClimateRiskService;
use App\Services\ClimateHealthAssessmentService;
use App\Services\WeatherService;
use Illuminate\Http\Request;

class WeatherController extends Controller
{
    public function index()
    {

        return view('weather.home');

    }

    public function weather(
        Request $request,
        WeatherService $service,
        AirQualityService $airQualityService,
        ClimateHealthAssessmentService $assessmentService,
        ChildClimateRiskService $childRiskService
    ) {
        $coordinates = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $latitude = (float) $coordinates['latitude'];
        $longitude = (float) $coordinates['longitude'];

        $data = $service->getWeather(
            $latitude,
            $longitude
        );
        $weatherSummary = $service->weatherSummary($data['daily']['weather_code'][0] ?? $data['current']['weather_code'] ?? null);
        $airQuality = $airQualityService->getAirQuality(
            $latitude,
            $longitude
        );
        $airQualityValue = $airQuality['current']['us_aqi'] ?? $airQuality['current']['european_aqi'] ?? null;
        $airQualityIndex = $airQualityService->aqiDescription($airQualityValue);
        $airQualityHealth = $airQualityService->aqiHealthMessage($airQualityValue);
        $signals = $assessmentService->assess($data, $airQuality);
        $childRisk = $childRiskService->assess($data, $airQuality, $latitude, $longitude);
        $airQualityForecast = $signals['air_forecast'];

        return view(
            'weather.dashboard',
            compact('data', 'weatherSummary', 'airQuality', 'airQualityIndex', 'airQualityValue', 'airQualityHealth', 'airQualityForecast', 'signals', 'childRisk', 'latitude', 'longitude')
        );

    }
}
