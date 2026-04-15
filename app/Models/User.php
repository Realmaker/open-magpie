<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_team_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot('role')->withTimestamps();
    }

    public function currentTeamRelation(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function projectShares(): HasMany
    {
        return $this->hasMany(ProjectShare::class, 'shared_with_user_id');
    }

    /**
     * Get the user's current team, falling back to first team.
     * Auto-sets current_team_id if not yet set.
     */
    public function currentTeam(): ?Team
    {
        if ($this->current_team_id) {
            $team = $this->currentTeamRelation;
            if ($team && $this->teams()->where('teams.id', $team->id)->exists()) {
                return $team;
            }
        }

        // Fallback: use first team and persist choice
        $team = $this->teams()->first();
        if ($team && !$this->current_team_id) {
            $this->update(['current_team_id' => $team->id]);
        }

        return $team;
    }

    /**
     * Switch to a different team (with membership validation).
     */
    public function switchTeam(Team $team): bool
    {
        if (!$this->teams()->where('teams.id', $team->id)->exists()) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        return true;
    }

    /**
     * Get the user's role in a specific team.
     */
    public function teamRole(?Team $team): ?string
    {
        if (!$team) {
            return null;
        }

        return $this->teams()
            ->where('teams.id', $team->id)
            ->first()
            ?->pivot
            ?->role;
    }

    /**
     * Check if user is owner or admin of their current team.
     */
    public function isTeamAdmin(): bool
    {
        return in_array($this->teamRole($this->currentTeam()), ['owner', 'admin']);
    }
}
