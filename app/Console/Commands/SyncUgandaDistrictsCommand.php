<?php

namespace App\Console\Commands;

use App\Models\ClimateLocation;
use App\Models\IngestionRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class SyncUgandaDistrictsCommand extends Command
{
    protected $signature = 'climate:sync-uganda-districts';

    protected $description = 'Sync official Uganda district codes and centroids';

    private const SOURCE_URL = 'https://unosat-geodrr.cern.ch/data/rest/services/Hosted/UGA_District_Boundary/FeatureServer/23/query';

    public function handle(): int
    {
        $run = IngestionRun::create([
            'source' => 'uganda-district-boundaries',
            'status' => 'running',
            'started_at' => now(),
            'metadata' => ['provider' => 'UBOS/WHO via UNOSAT'],
        ]);

        try {
            $response = Http::connectTimeout(10)
                ->timeout(60)
                ->get(self::SOURCE_URL, [
                    'where' => '1=1',
                    'outFields' => 'admin2name_en,admin2pcode,admin1name_en',
                    'returnGeometry' => 'true',
                    'outSR' => '4326',
                    'f' => 'geojson',
                ])
                ->throw();
            $features = $response->json('features');

            if (! is_array($features) || count($features) < 100) {
                throw new \RuntimeException('The district source returned an incomplete feature set.');
            }

            $count = 0;
            foreach ($features as $feature) {
                $properties = $feature['properties'] ?? [];
                $code = trim((string) ($properties['admin2pcode'] ?? ''));
                $name = trim((string) ($properties['admin2name_en'] ?? ''));
                $centroid = $this->centroid($feature['geometry']['coordinates'] ?? null);

                if ($code === '' || $name === '' || $centroid === null) {
                    continue;
                }

                $location = ClimateLocation::firstOrNew(['type' => 'district', 'external_id' => $code]);
                if (! $location->exists) {
                    $location = ClimateLocation::where('type', 'district')->where('name', $name)->first() ?? $location;
                }
                $location->fill([
                    'name' => $name,
                    'type' => 'district',
                    'country_code' => 'UG',
                    'admin_level' => 'district',
                    'external_id' => $code,
                    'latitude' => $centroid['latitude'],
                    'longitude' => $centroid['longitude'],
                    'is_active' => true,
                    'metadata' => [
                        'source' => self::SOURCE_URL,
                        'region' => $properties['admin1name_en'] ?? null,
                        'geometry' => json_encode([
                            'type' => $feature['geometry']['type'] ?? null,
                            'coordinates' => $feature['geometry']['coordinates'] ?? null,
                        ], JSON_THROW_ON_ERROR),
                    ],
                ])->save();
                $count++;
            }

            $run->update(['status' => 'succeeded', 'records_ingested' => $count, 'finished_at' => now()]);
            $this->info("Synchronized {$count} Uganda districts.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => $exception->getMessage()]);
            $this->error('District sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function centroid($coordinates): ?array
    {
        $points = [];
        $collect = function ($value) use (&$collect, &$points): void {
            if (is_array($value) && isset($value[0], $value[1]) && is_numeric($value[0]) && is_numeric($value[1])) {
                $points[] = ['longitude' => (float) $value[0], 'latitude' => (float) $value[1]];

                return;
            }
            if (is_array($value)) {
                foreach ($value as $child) {
                    $collect($child);
                }
            }
        };
        $collect($coordinates);

        if ($points === []) {
            return null;
        }

        return [
            'latitude' => round(array_sum(array_column($points, 'latitude')) / count($points), 6),
            'longitude' => round(array_sum(array_column($points, 'longitude')) / count($points), 6),
        ];
    }
}
