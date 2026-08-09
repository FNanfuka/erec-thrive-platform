<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClimateObservation extends Model
{
    protected $fillable = [
        'climate_location_id', 'source', 'variable', 'value', 'unit',
        'observed_at', 'quality_flag', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'observed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ClimateLocation::class, 'climate_location_id');
    }
}
