<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    */
    'api_rate_limit' => env('API_RATE_LIMIT', 60),
    'ai_rate_limit' => env('AI_RATE_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration (Phase 4)
    |--------------------------------------------------------------------------
    */
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_model' => env('OPENAI_MODEL', 'gpt-4o'),

    /*
    |--------------------------------------------------------------------------
    | Worker Configuration
    |--------------------------------------------------------------------------
    */
    'worker' => [
        'heartbeat_timeout_seconds' => env('WORKER_HEARTBEAT_TIMEOUT', 90),
        'default_requires_approval' => env('WORKER_DEFAULT_REQUIRES_APPROVAL', true),
        'max_job_runtime_minutes' => env('WORKER_MAX_JOB_RUNTIME', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Score Configuration
    |--------------------------------------------------------------------------
    */
    'health_score' => [
        'inactivity_threshold_days' => 3,
        'inactivity_penalty_per_day' => 5,
        'overdue_task_penalty' => 10,
        'task_ratio_threshold' => 3,
        'task_ratio_penalty' => 5,
    ],
];
