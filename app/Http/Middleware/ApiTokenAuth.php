<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract Bearer token from Authorization header
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'error' => [
                    'code' => 'token_missing',
                    'message' => 'API token is required. Please provide a Bearer token in the Authorization header.'
                ]
            ], 401);
        }

        // Hash the incoming token and look up in database
        $hashedToken = hash('sha256', $token);
        $apiToken = ApiToken::where('token', $hashedToken)->first();

        if (!$apiToken) {
            return response()->json([
                'error' => [
                    'code' => 'token_invalid',
                    'message' => 'Invalid API token.'
                ]
            ], 401);
        }

        // Check if token is expired
        if ($apiToken->expires_at && $apiToken->expires_at->isPast()) {
            return response()->json([
                'error' => [
                    'code' => 'token_expired',
                    'message' => 'API token has expired.'
                ]
            ], 401);
        }

        // Update last_used_at timestamp
        $apiToken->update(['last_used_at' => now()]);

        // Store auth data in request attributes (NOT in input bag, to prevent spoofing)
        $request->attributes->set('api_token', $apiToken);
        $request->attributes->set('user', $apiToken->user);
        $request->attributes->set('auth_user_id', $apiToken->user_id);
        $request->attributes->set('team_id', $apiToken->team_id);

        // Set the authenticated user for Laravel's auth system
        auth()->setUser($apiToken->user);

        return $next($request);
    }
}
