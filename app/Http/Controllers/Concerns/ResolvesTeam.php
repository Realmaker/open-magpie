<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;

trait ResolvesTeam
{
    /**
     * Get the current team for a web request.
     * Uses the user's persisted current_team_id with fallback.
     */
    protected function resolveTeam(Request $request): ?Team
    {
        return $request->user()?->currentTeam();
    }

    /**
     * Get the current team ID, returning 0 if no team (safe for queries).
     */
    protected function resolveTeamId(Request $request): int
    {
        return $this->resolveTeam($request)?->id ?? 0;
    }
}
