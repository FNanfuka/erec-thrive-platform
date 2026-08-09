<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClimateHealthOutcome extends Model
{
    protected $fillable = [
        'climate_location_id', 'indicator', 'age_group', 'period_start', 'period_end',
        'value', 'unit', 'source', 'quality_flag', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'value' => 'float',
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ClimateLocation::class, 'climate_location_id');
    }
}
