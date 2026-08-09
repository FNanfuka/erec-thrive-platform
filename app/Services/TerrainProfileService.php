<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TerrainProfileService
{
    public function profile(float $latitude, float $longitude, ?float $fallbackElevation = null): array
    {
        $key = 'terrain-profile:'.number_format($latitude, 3, '.', '').':'.number_format($longitude, 3, '.', '');
        $cached = Cache::get($key);
        if (is_array($cached)) return $cached;

        $offset = .025;
        $points = [[$latitude, $longitude], [$latitude + $offset, $longitude], [$latitude - $offset, $longitude], [$latitude, $longitude + $offset], [$latitude, $longitude - $offset], [$latitude + $offset, $longitude + $offset], [$latitude - $offset, $longitude - $offset], [$latitude + $offset, $longitude - $offset], [$latitude - $offset, $longitude + $offset]];
        $response = null;

        try {
            $response = Http::connectTimeout(4)->timeout(8)->get('https://api.open-meteo.com/v1/elevation', ['latitude' => collect($points)->pluck(0)->implode(','), 'longitude' => collect($points)->pluck(1)->implode(',')])->throw()->json();
        } catch (ConnectionException|RequestException) {
            return ['available' => $fallbackElevation !== null, 'elevation_m' => $fallbackElevation, 'neighbor_average_m' => null, 'relief_m' => null, 'valley_like' => false, 'confidence' => 'low'];
        }

        $elevations = $response['elevation'] ?? [];
        if (count($elevations) < 2) return ['available' => $fallbackElevation !== null, 'elevation_m' => $fallbackElevation, 'neighbor_average_m' => null, 'relief_m' => null, 'valley_like' => false, 'confidence' => 'low'];
        $center = (float) $elevations[0];
        $neighbors = array_map('floatval', array_slice($elevations, 1));
        $average = array_sum($neighbors) / count($neighbors);
        $profile = ['available' => true, 'elevation_m' => $center, 'neighbor_average_m' => round($average, 1), 'relief_m' => round($average - $center, 1), 'valley_like' => ($average - $center) >= 40, 'confidence' => 'moderate'];
        Cache::put($key, $profile, now()->addDay());

        return $profile;
    }
}
