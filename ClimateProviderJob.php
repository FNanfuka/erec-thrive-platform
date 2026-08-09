<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClimateProviderJob extends Model
{
    protected $fillable = [
        'climate_location_id', 'provider', 'external_job_id', 'status',
        'period_start', 'period_end', 'attempts', 'error_message', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'metadata' => 'array',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ClimateLocation::class, 'climate_location_id');
    }
}
