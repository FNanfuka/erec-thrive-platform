<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClimateLocation extends Model
{
    protected $fillable = [
        'name', 'type', 'country_code', 'admin_level', 'external_id',
        'latitude', 'longitude', 'elevation_m', 'drainage_score', 'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'elevation_m' => 'float',
            'drainage_score' => 'float',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function observations(): HasMany
    {
        return $this->hasMany(ClimateObservation::class);
    }

    public function healthOutcomes(): HasMany
    {
        return $this->hasMany(ClimateHealthOutcome::class);
    }
}
