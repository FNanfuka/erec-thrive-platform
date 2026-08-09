<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ClimateAlert extends Model
{
    protected $fillable = ['alert_key', 'climate_location_id', 'hazard', 'severity', 'status', 'title', 'summary', 'recommended_actions', 'source', 'triggered_at', 'last_seen_at', 'resolved_at', 'metadata'];

    protected function casts(): array
    {
        return ['recommended_actions' => 'array', 'metadata' => 'array', 'triggered_at' => 'datetime', 'last_seen_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(ClimateLocation::class, 'climate_location_id');
    }

    public function action(): HasOne
    {
        return $this->hasOne(ClimateAlertAction::class);
    }
}
