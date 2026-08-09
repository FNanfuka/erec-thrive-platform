<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClimateAlertAction extends Model
{
    protected $fillable = [
        'climate_alert_id', 'status', 'actor_name', 'notes',
        'acknowledged_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(ClimateAlert::class, 'climate_alert_id');
    }
}
