<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $team_id
 * @property int $created_by
 * @property string $name
 * @property string $slug
 */

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id',
        'created_by',
        'name',
        'slug',
        'description',
        'status',
        'priority',
        'category',
        'repository_url',
        'tech_stack',
        'metadata',
        'health_score',
        'last_activity_at',
        'worker_config',
        'install_notes',
    ];

    protected function casts(): array
    {
        return [
            'tech_stack' => 'array',
            'metadata' => 'array',
            'worker_config' => 'array',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->name);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function tags(): MorphMany
    {
        return $this->morphMany(Tag::class, 'taggable');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function workerJobs(): HasMany
    {
        return $this->hasMany(WorkerJob::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ProjectSnapshot::class);
    }

    public function latestSnapshot(): ?\Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProjectSnapshot::class)->orderBy('version', 'desc');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ProjectShare::class);
    }

    public function activeShares(): HasMany
    {
        return $this->hasMany(ProjectShare::class)
            ->whereNotNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Check if a user has access to this project via sharing.
     */
    public function isSharedWith(User $user, string $minimumPermission = 'viewer'): bool
    {
        return $this->shares()
            ->where('shared_with_user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->contains(fn ($share) => $share->hasPermission($minimumPermission));
    }

    /**
     * Get the share record for a specific user (if any).
     */
    public function getShareFor(User $user): ?ProjectShare
    {
        return $this->shares()
            ->where('shared_with_user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function touchActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->where($field ?? 'slug', $value)
            ->where('team_id', request()->attributes->get('team_id'))
            ->first();
    }
}
