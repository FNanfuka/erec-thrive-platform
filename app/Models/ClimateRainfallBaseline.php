<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClimateRainfallBaseline extends Model
{
    protected $fillable = [
        'climate_location_id', 'month', 'mean_mm', 'stddev_mm', 'sample_count',
        'source', 'period_start', 'period_end',
    ];

    protected function casts(): array
    {
        return [
            'mean_mm' => 'float',
            'stddev_mm' => 'float',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ClimateLocation::class, 'climate_location_id');
    }
}
