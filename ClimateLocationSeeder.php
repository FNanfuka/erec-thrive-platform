<?php

namespace Database\Seeders;

use App\Models\ClimateLocation;
use Illuminate\Database\Seeder;

class ClimateLocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'name' => 'Kampala District',
                'external_id' => 'UG-DIST-KLA',
                'latitude' => 0.3476,
                'longitude' => 32.5825,
            ],
            [
                'name' => 'Lira District',
                'external_id' => 'UG-DIST-LIR',
                'latitude' => 2.2499,
                'longitude' => 32.8998,
            ],
        ] as $district) {
            ClimateLocation::updateOrCreate(
                ['type' => 'district', 'external_id' => $district['external_id']],
                $district + [
                    'country_code' => 'UG',
                    'admin_level' => 'district',
                    'is_active' => true,
                ]
            );
        }
    }
}
