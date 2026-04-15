<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkerJob extends Model
{
    use SoftDeletes;

    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_QUEUED = 'queued';
    const STATUS_CLAIMED = 'claimed';
    const STATUS_RUNNING = 'running';
    const STATUS_DONE = 'done';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    const TYPE_CODE_CHANGE = 'code_change';
    const TYPE_NEW_PROJECT = 'new_project';
    const TYPE_PREPARED = 'prepared';

    protected $fillable = [
        'team_id',
        'project_id',
        'created_by',
        'approved_by',
        'worker_id',
        'title',
        'description',
        'prompt',
        'type',
        'status',
        'priority',
        'project_path',
        'working_directory',
        'environment',
        'output',
        'error_output',
        'exit_code',
        'duration_seconds',
        'result_summary',
        'approved_at',
        'claimed_at',
        'started_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'environment' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'claimed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED, self::STATUS_CANCELLED]);
    }
}
