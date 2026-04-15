<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\ProjectShare;
use App\Models\User;
use Illuminate\Http\Request;

trait FindsProjectWithSharing
{
    /**
     * Find project by slug - checks team ownership first, then active shares.
     *
     * For API requests: uses request attributes (set by ApiTokenAuth middleware).
     * For Web requests: uses auth() user and currentTeam().
     *
     * @param string $requiredPermission Minimum permission level needed: 'viewer', 'editor', 'admin'
     */
    protected function findProjectWithSharing(Request $request, string $slug, string $requiredPermission = 'viewer'): Project
    {
        $user = $this->resolveUser($request);
        $teamId = $this->resolveTeamIdForProject($request);

        // 1. Team-owned project (full access, no permission check needed)
        $project = Project::where('team_id', $teamId)
            ->where('slug', $slug)
            ->first();

        if ($project) {
            return $project;
        }

        // 2. Shared project with sufficient permission
        if ($user) {
            $project = Project::where('slug', $slug)
                ->whereHas('shares', function ($q) use ($user) {
                    $q->where('shared_with_user_id', $user->id)
                      ->whereNotNull('accepted_at')
                      ->where(function ($q2) {
                          $q2->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now());
                      });
                })
                ->first();

            if ($project && $project->isSharedWith($user, $requiredPermission)) {
                return $project;
            }
        }

        abort(404, "Project '{$slug}' not found.");
    }

    /**
     * Resolve the authenticated user from the request.
     */
    private function resolveUser(Request $request): ?User
    {
        // API: set by ApiTokenAuth middleware
        $user = $request->attributes->get('user');
        if ($user) {
            return $user;
        }

        // Web: Laravel auth
        return $request->user();
    }

    /**
     * Resolve team ID from request context.
     */
    private function resolveTeamIdForProject(Request $request): int
    {
        // API: set by ApiTokenAuth middleware
        $teamId = $request->attributes->get('team_id');
        if ($teamId) {
            return (int) $teamId;
        }

        // Web: from user's current team
        return $request->user()?->currentTeam()?->id ?? 0;
    }
}
