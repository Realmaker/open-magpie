<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamAccess
{
    /**
     * Handle an incoming request.
     *
     * Ensures the authenticated user has access to the requested team.
     * For API routes, the team_id comes from the api_token set by ApiTokenAuth middleware.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get team_id from request attributes (set by ApiTokenAuth)
        $teamId = $request->attributes->get('team_id');

        if (!$teamId) {
            return response()->json([
                'error' => [
                    'code' => 'team_access_denied',
                    'message' => 'No team access. Please ensure you are authenticated with a valid API token.'
                ]
            ], 403);
        }

        // Get the authenticated user
        $user = $request->attributes->get('user');

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'unauthorized',
                    'message' => 'User not authenticated.'
                ]
            ], 401);
        }

        // Verify the user has access to this team
        $hasAccess = $user->teams()->where('teams.id', $teamId)->exists();

        if (!$hasAccess) {
            return response()->json([
                'error' => [
                    'code' => 'team_access_denied',
                    'message' => 'You do not have access to this team.'
                ]
            ], 403);
        }

        // Team access verified, continue with request
        return $next($request);
    }
}
