<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckApiAbility
{
    /**
     * Handle an incoming request.
     *
     * Checks if the API token has the required ability for this endpoint.
     * Abilities follow the pattern "resource:action" (e.g. "projects:write", "tasks:read").
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$abilities  Required abilities (any one must match)
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $apiToken = $request->attributes->get('api_token');

        if (!$apiToken) {
            return response()->json([
                'error' => [
                    'code' => 'unauthorized',
                    'message' => 'No API token found.',
                ],
            ], 401);
        }

        // Tokens with no abilities defined have unrestricted access
        if (empty($apiToken->abilities)) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            if ($apiToken->hasAbility($ability)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => [
                'code' => 'insufficient_permissions',
                'message' => 'Your API token does not have the required permission: ' . implode(' or ', $abilities),
            ],
        ], 403);
    }
}
