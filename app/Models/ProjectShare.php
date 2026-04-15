<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProjectShare extends Model
{
    protected $fillable = [
        'project_id',
        'shared_by',
        'shared_with_user_id',
        'shared_with_email',
        'permission',
        'invite_token',
        'accepted_at',
        'expires_at',
    ];

    protected $hidden = [
        'invite_token',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function sharedWithUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    /**
     * Generate a cryptographically secure invite token.
     * Stored as SHA-256 hash, plain token returned only once.
     */
    public static function generateInviteToken(): array
    {
        $plainToken = Str::random(64);
        $hashedToken = hash('sha256', $plainToken);

        return [
            'plain' => $plainToken,
            'hashed' => $hashedToken,
        ];
    }

    /**
     * Find a share by plain invite token.
     */
    public static function findByToken(string $plainToken): ?self
    {
        $hashed = hash('sha256', $plainToken);

        return static::where('invite_token', $hashed)->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return !$this->isAccepted() && !$this->isExpired();
    }

    /**
     * Check if this share grants at least the given permission level.
     */
    public function hasPermission(string $requiredLevel): bool
    {
        $levels = ['viewer' => 1, 'editor' => 2, 'admin' => 3];

        $has = $levels[$this->permission] ?? 0;
        $needs = $levels[$requiredLevel] ?? 0;

        return $has >= $needs;
    }
}
