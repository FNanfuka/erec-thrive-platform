<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionRun extends Model
{
    protected $fillable = [
        'source', 'status', 'records_ingested', 'started_at', 'finished_at',
        'error_message', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
