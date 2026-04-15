<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Worker extends Model
{
    protected $fillable = [
        'team_id',
        'name',
        'machine_id',
        'status',
        'version',
        'os_info',
        'capabilities',
        'current_jobs',
        'max_parallel_jobs',
        'last_heartbeat_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'current_jobs' => 'array',
            'metadata' => 'array',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(WorkerJob::class);
    }

    public function isOnline(): bool
    {
        if (!$this->last_heartbeat_at) {
            return false;
        }

        return $this->last_heartbeat_at->gt(now()->subSeconds(
            config('claude-hub.worker.heartbeat_timeout_seconds', 90)
        ));
    }
}
