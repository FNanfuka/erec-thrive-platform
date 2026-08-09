<?php

namespace App\Console\Commands;

use App\Models\ClimateLocation;
use App\Models\IngestionRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncOsmFacilitiesCommand extends Command
{
    protected $signature = 'climate:sync-osm-facilities';

    protected $description = 'Sync public candidate health facilities from OpenStreetMap for Uganda';

    public function handle(): int
    {
        $run = IngestionRun::create([
            'source' => 'openstreetmap-facilities',
            'status' => 'running',
            'started_at' => now(),
            'metadata' => ['candidate_registry' => true],
        ]);

        try {
            $client = Http::asForm()->withHeaders([
                'User-Agent' => 'THRIVE-Climate-Health/1.0 (open-weather-intelligence)',
                'Accept' => 'application/json',
            ])->connectTimeout(10)->timeout(150)->retry(2, 1000);
            $regions = [
                [-1.5, 29.5, 1.5, 32.3], [-1.5, 32.3, 1.5, 35.1],
                [1.5, 29.5, 4.3, 32.3], [1.5, 32.3, 4.3, 35.1],
            ];
            $elementsById = [];
            foreach ($regions as [$south, $west, $north, $east]) {
                $query = sprintf('[out:json][timeout:60];nwr["amenity"~"^(hospital|clinic|doctors|health_centre)$"](%s,%s,%s,%s);out center tags;', $south, $west, $north, $east);
                $response = null;
                foreach (['https://overpass-api.de/api/interpreter', 'https://overpass.kumi.systems/api/interpreter'] as $endpoint) {
                    $candidate = $client->post($endpoint, ['data' => $query]);
                    if ($candidate->successful()) {
                        $response = $candidate;
                        break;
                    }
                }
                if (! $response) {
                    $this->warn(sprintf('Skipped region %s,%s to %s,%s: Overpass providers unavailable or rate-limited.', $south, $west, $north, $east));
                    continue;
                }
                foreach ($response->json('elements') ?? [] as $element) {
                    $elementsById[$element['type'].':'.$element['id']] = $element;
                }
            }
            $elements = array_values($elementsById);

            $count = 0;
            foreach ($elements as $element) {
                $tags = $element['tags'] ?? [];
                $latitude = $element['lat'] ?? data_get($element, 'center.lat');
                $longitude = $element['lon'] ?? data_get($element, 'center.lon');
                if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                    continue;
                }

                $externalId = 'osm:'.$element['type'].':'.$element['id'];
                $location = ClimateLocation::where('external_id', $externalId)->first()
                    ?? ClimateLocation::where('type', 'facility')->where('latitude', $latitude)->where('longitude', $longitude)->first()
                    ?? new ClimateLocation;
                $metadata = $location->metadata ?? [];
                $location->fill([
                    'name' => $tags['name'] ?? $tags['operator'] ?? 'Unnamed health facility',
                    'type' => 'facility',
                    'country_code' => 'UG',
                    'admin_level' => 'facility',
                    'external_id' => $externalId,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'is_active' => true,
                    'metadata' => array_merge($metadata, [
                        'source' => 'OpenStreetMap',
                        'candidate_registry' => true,
                        'osm_type' => $element['type'],
                        'osm_id' => $element['id'],
                        'amenity' => $tags['amenity'] ?? null,
                        'operator' => $tags['operator'] ?? null,
                    ]),
                ])->save();
                $count++;
            }

            $run->update(['status' => 'succeeded', 'records_ingested' => $count, 'finished_at' => now()]);
            $this->info("Synchronized {$count} candidate facilities from OpenStreetMap.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => $exception->getMessage()]);
            $this->error('Facility sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
