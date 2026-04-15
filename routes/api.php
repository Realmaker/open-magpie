<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectShareController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\SnapshotController;
use App\Http\Controllers\Api\WorkerController;
use App\Http\Controllers\Api\WorkerJobController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.token', 'team.access', 'throttle:api'])->group(function () {

    // --- Projects ---
    Route::get('/projects', [ProjectController::class, 'index'])
        ->middleware('api.ability:projects:read');
    Route::post('/projects', [ProjectController::class, 'store'])
        ->middleware('api.ability:projects:write');
    Route::get('/projects/{slug}', [ProjectController::class, 'show'])
        ->middleware('api.ability:projects:read');
    Route::patch('/projects/{slug}', [ProjectController::class, 'update'])
        ->middleware('api.ability:projects:write');
    Route::delete('/projects/{slug}', [ProjectController::class, 'destroy'])
        ->middleware('api.ability:projects:delete');
    Route::get('/projects/{slug}/stats', [ProjectController::class, 'stats'])
        ->middleware('api.ability:projects:read');
    Route::get('/projects/{slug}/activity', [ProjectController::class, 'activity'])
        ->middleware('api.ability:projects:read');

    // --- Project Shares ---
    Route::get('/projects/{slug}/shares', [ProjectShareController::class, 'index'])
        ->middleware('api.ability:projects:read');
    Route::post('/projects/{slug}/shares', [ProjectShareController::class, 'store'])
        ->middleware(['api.ability:projects:write', 'throttle:shares']);
    Route::patch('/projects/{slug}/shares/{id}', [ProjectShareController::class, 'update'])
        ->middleware('api.ability:projects:write');
    Route::delete('/projects/{slug}/shares/{id}', [ProjectShareController::class, 'destroy'])
        ->middleware('api.ability:projects:write');
    Route::get('/shared-with-me', [ProjectShareController::class, 'sharedWithMe'])
        ->middleware('api.ability:projects:read');

    // --- Events ---
    Route::get('/projects/{slug}/events', [EventController::class, 'index'])
        ->middleware('api.ability:events:read');
    Route::post('/projects/{slug}/events', [EventController::class, 'store'])
        ->middleware('api.ability:events:write');
    Route::get('/projects/{slug}/events/{id}', [EventController::class, 'show'])
        ->middleware('api.ability:events:read');
    Route::patch('/projects/{slug}/events/{id}', [EventController::class, 'update'])
        ->middleware('api.ability:events:write');
    Route::delete('/projects/{slug}/events/{id}', [EventController::class, 'destroy'])
        ->middleware('api.ability:events:delete');

    // --- Tasks ---
    Route::get('/projects/{slug}/tasks', [TaskController::class, 'index'])
        ->middleware('api.ability:tasks:read');
    Route::post('/projects/{slug}/tasks', [TaskController::class, 'store'])
        ->middleware('api.ability:tasks:write');
    Route::post('/projects/{slug}/tasks/bulk', [TaskController::class, 'bulkStore'])
        ->middleware('api.ability:tasks:write');
    Route::get('/projects/{slug}/tasks/{id}', [TaskController::class, 'show'])
        ->middleware('api.ability:tasks:read');
    Route::patch('/projects/{slug}/tasks/{id}', [TaskController::class, 'update'])
        ->middleware('api.ability:tasks:write');
    Route::delete('/projects/{slug}/tasks/{id}', [TaskController::class, 'destroy'])
        ->middleware('api.ability:tasks:delete');

    // --- Documents ---
    Route::get('/projects/{slug}/documents', [DocumentController::class, 'index'])
        ->middleware('api.ability:documents:read');
    Route::post('/projects/{slug}/documents', [DocumentController::class, 'store'])
        ->middleware('api.ability:documents:write');
    Route::get('/projects/{slug}/documents/{docSlug}', [DocumentController::class, 'show'])
        ->middleware('api.ability:documents:read');
    Route::put('/projects/{slug}/documents/{docSlug}', [DocumentController::class, 'update'])
        ->middleware('api.ability:documents:write');
    Route::delete('/projects/{slug}/documents/{docSlug}', [DocumentController::class, 'destroy'])
        ->middleware('api.ability:documents:delete');
    Route::get('/projects/{slug}/documents/{docSlug}/versions', [DocumentController::class, 'versions'])
        ->middleware('api.ability:documents:read');

    // --- Snapshots (Project Files) ---
    Route::get('/projects/{slug}/snapshots', [SnapshotController::class, 'index'])
        ->middleware('api.ability:projects:read');
    Route::get('/projects/{slug}/snapshots/latest', [SnapshotController::class, 'latest'])
        ->middleware('api.ability:projects:read');
    Route::post('/projects/{slug}/snapshots', [SnapshotController::class, 'store'])
        ->middleware('api.ability:projects:write');
    Route::get('/projects/{slug}/snapshots/{version}/download', [SnapshotController::class, 'download'])
        ->middleware('api.ability:projects:read');
    Route::get('/projects/{slug}/snapshots/{version}/file', [SnapshotController::class, 'previewFile'])
        ->middleware('api.ability:projects:read');
    Route::patch('/projects/{slug}/install-notes', [SnapshotController::class, 'updateInstallNotes'])
        ->middleware('api.ability:projects:write');

    // --- Notes ---
    Route::get('/notes', [NoteController::class, 'index'])
        ->middleware('api.ability:notes:read');
    Route::post('/notes', [NoteController::class, 'store'])
        ->middleware('api.ability:notes:write');
    Route::patch('/notes/{id}', [NoteController::class, 'update'])
        ->middleware('api.ability:notes:write');
    Route::delete('/notes/{id}', [NoteController::class, 'destroy'])
        ->middleware('api.ability:notes:delete');

    // --- AI (stricter rate limit) ---
    Route::middleware('throttle:ai')->group(function () {
        Route::post('/ai/summarize', [AiController::class, 'summarize'])
            ->middleware('api.ability:ai:use');
        Route::post('/ai/extract-tasks', [AiController::class, 'extractTasks'])
            ->middleware('api.ability:ai:use');
        Route::post('/ai/project-summary/{slug}', [AiController::class, 'projectSummary'])
            ->middleware('api.ability:ai:use');
    });

    // --- Workers ---
    Route::post('/worker/heartbeat', [WorkerController::class, 'heartbeat'])
        ->middleware('api.ability:workers:write');
    Route::get('/workers', [WorkerController::class, 'index'])
        ->middleware('api.ability:workers:read');
    Route::get('/worker/jobs/pending', [WorkerController::class, 'pendingJobs'])
        ->middleware('api.ability:workers:read');
    Route::post('/worker/jobs/{id}/claim', [WorkerController::class, 'claimJob'])
        ->middleware('api.ability:workers:write');
    Route::post('/worker/jobs/{id}/start', [WorkerController::class, 'startJob'])
        ->middleware('api.ability:workers:write');
    Route::post('/worker/jobs/{id}/complete', [WorkerController::class, 'completeJob'])
        ->middleware('api.ability:workers:write');

    // --- Worker Jobs CRUD ---
    Route::get('/worker-jobs', [WorkerJobController::class, 'index'])
        ->middleware('api.ability:workers:read');
    Route::post('/worker-jobs', [WorkerJobController::class, 'store'])
        ->middleware('api.ability:workers:write');
    Route::get('/worker-jobs/{id}', [WorkerJobController::class, 'show'])
        ->middleware('api.ability:workers:read');
    Route::patch('/worker-jobs/{id}', [WorkerJobController::class, 'update'])
        ->middleware('api.ability:workers:write');
    Route::post('/worker-jobs/{id}/approve', [WorkerJobController::class, 'approve'])
        ->middleware('api.ability:workers:write');
    Route::post('/worker-jobs/{id}/cancel', [WorkerJobController::class, 'cancel'])
        ->middleware('api.ability:workers:write');
    Route::post('/worker-jobs/{id}/retry', [WorkerJobController::class, 'retry'])
        ->middleware('api.ability:workers:write');
});
