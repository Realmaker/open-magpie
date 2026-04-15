<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\ActivityLogController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InboxController;
use App\Http\Controllers\Web\ProjectController as WebProjectController;
use App\Http\Controllers\Web\SearchController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\DownloadController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\TaskController as WebTaskController;
use App\Http\Controllers\Web\WorkerController as WebWorkerController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Share accept route - requires auth but separate from main routes
Route::get('/shares/accept/{token}', [\App\Http\Controllers\Web\ProjectShareAcceptController::class, 'accept'])
    ->middleware(['auth', 'verified'])
    ->name('shares.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tasks', [WebTaskController::class, 'index'])->name('tasks.index');
    Route::get('/projects', [WebProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [WebProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{slug}', [WebProjectController::class, 'show'])->name('projects.show');
    Route::patch('/projects/{slug}', [WebProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{slug}', [WebProjectController::class, 'destroy'])->name('projects.destroy');

    // Document CRUD
    Route::post('/projects/{slug}/documents', [WebProjectController::class, 'storeDocument'])->name('projects.documents.store');
    Route::put('/projects/{slug}/documents/{docSlug}', [WebProjectController::class, 'updateDocument'])->name('projects.documents.update');
    Route::delete('/projects/{slug}/documents/{docSlug}', [WebProjectController::class, 'destroyDocument'])->name('projects.documents.destroy');

    // Task CRUD
    Route::post('/projects/{slug}/tasks', [WebProjectController::class, 'storeTask'])->name('projects.tasks.store');
    Route::patch('/projects/{slug}/tasks/{id}', [WebProjectController::class, 'updateTask'])->name('projects.tasks.update');
    Route::delete('/projects/{slug}/tasks/{id}', [WebProjectController::class, 'destroyTask'])->name('projects.tasks.destroy');
    // Snapshots
    Route::post('/projects/{slug}/snapshots', [WebProjectController::class, 'storeSnapshot'])->name('projects.snapshots.store');
    Route::get('/projects/{slug}/snapshots/{version}/download', [WebProjectController::class, 'downloadSnapshot'])->name('projects.snapshots.download');
    Route::patch('/projects/{slug}/install-notes', [WebProjectController::class, 'updateInstallNotes'])->name('projects.install-notes.update');

    // Project Shares
    Route::get('/projects/{slug}/shares', [WebProjectController::class, 'shares'])->name('projects.shares.index');
    Route::post('/projects/{slug}/shares', [WebProjectController::class, 'storeShare'])->name('projects.shares.store');
    Route::patch('/projects/{slug}/shares/{id}', [WebProjectController::class, 'updateShare'])->name('projects.shares.update');
    Route::delete('/projects/{slug}/shares/{id}', [WebProjectController::class, 'destroyShare'])->name('projects.shares.destroy');
    Route::get('/shared-with-me', [WebProjectController::class, 'sharedWithMe'])->name('projects.shared');

    // Notes CRUD
    Route::post('/projects/{slug}/notes', [WebProjectController::class, 'storeNote'])->name('projects.notes.store');
    Route::patch('/notes/{id}', [WebProjectController::class, 'updateNote'])->name('notes.update');
    Route::delete('/notes/{id}', [WebProjectController::class, 'destroyNote'])->name('notes.destroy');

    // Workers
    Route::get('/workers', [WebWorkerController::class, 'index'])->name('workers.index');
    Route::post('/workers/jobs', [WebWorkerController::class, 'createJob'])->name('workers.jobs.store');
    Route::get('/workers/jobs/{id}', [WebWorkerController::class, 'showJob'])->name('workers.jobs.show');
    Route::post('/workers/jobs/{id}/approve', [WebWorkerController::class, 'approveJob'])->name('workers.jobs.approve');
    Route::post('/workers/jobs/{id}/cancel', [WebWorkerController::class, 'cancelJob'])->name('workers.jobs.cancel');
    Route::post('/workers/jobs/{id}/retry', [WebWorkerController::class, 'retryJob'])->name('workers.jobs.retry');
    Route::patch('/projects/{slug}/worker-config', [WebWorkerController::class, 'updateProjectWorkerConfig'])->name('projects.worker-config.update');

    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding');
    Route::get('/downloads/brain-skill', [DownloadController::class, 'skill'])->name('downloads.skill');
    Route::get('/downloads/brain-worker', [DownloadController::class, 'worker'])->name('downloads.worker');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
    // Mail Test (admin only)
    Route::get('/settings/mail-test', [\App\Http\Controllers\Web\MailTestController::class, 'index'])->name('settings.mail-test');
    Route::post('/settings/mail-test', [\App\Http\Controllers\Web\MailTestController::class, 'send'])->name('settings.mail-test.send');

    // User Management (admin only)
    Route::get('/settings/users', [\App\Http\Controllers\Web\UserManagementController::class, 'index'])->name('settings.users');
    Route::patch('/settings/users/{id}/role', [\App\Http\Controllers\Web\UserManagementController::class, 'updateRole'])->name('settings.users.role');
    Route::post('/settings/users/add', [\App\Http\Controllers\Web\UserManagementController::class, 'addToTeam'])->name('settings.users.add');
    Route::delete('/settings/users/{id}', [\App\Http\Controllers\Web\UserManagementController::class, 'removeFromTeam'])->name('settings.users.remove');

    Route::get('/settings/api-tokens', [SettingsController::class, 'apiTokens'])->name('settings.api-tokens');
    Route::post('/settings/api-tokens', [SettingsController::class, 'createApiToken'])->name('settings.api-tokens.store');
    Route::delete('/settings/api-tokens/{id}', [SettingsController::class, 'deleteApiToken'])->name('settings.api-tokens.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
