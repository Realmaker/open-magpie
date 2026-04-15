<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Standard API rate limit: 60 requests per minute per token
        RateLimiter::for('api', function (Request $request) {
            $token = $request->bearerToken();
            $key = $token ? hash('sha256', $token) : ($request->ip() ?? 'unknown');

            return Limit::perMinute((int) config('claude-hub.api_rate_limit', 60))
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'error' => [
                            'code' => 'rate_limit_exceeded',
                            'message' => 'Too many requests. Please try again later.',
                        ],
                    ], 429);
                });
        });

        // Stricter rate limit for AI endpoints: 10 per minute
        RateLimiter::for('ai', function (Request $request) {
            $token = $request->bearerToken();
            $key = $token ? hash('sha256', $token) : ($request->ip() ?? 'unknown');

            return Limit::perMinute((int) config('claude-hub.ai_rate_limit', 10))
                ->by('ai:' . $key)
                ->response(function () {
                    return response()->json([
                        'error' => [
                            'code' => 'ai_rate_limit_exceeded',
                            'message' => 'AI request limit reached. Please try again later.',
                        ],
                    ], 429);
                });
        });

        // Share invitations: 20 per hour
        RateLimiter::for('shares', function (Request $request) {
            $userId = $request->attributes->get('auth_user_id') ?? $request->user()?->id ?? 'anon';

            return Limit::perHour(20)
                ->by('shares:' . $userId)
                ->response(function () {
                    return response()->json([
                        'error' => [
                            'code' => 'share_rate_limit_exceeded',
                            'message' => 'Too many share invitations. Please try again later.',
                        ],
                    ], 429);
                });
        });
    }
}
