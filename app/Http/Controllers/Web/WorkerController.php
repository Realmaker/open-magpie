<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Worker;
use App\Models\WorkerJob;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $teamId = $team?->id ?? 0;

        $workers = Worker::where('team_id', $teamId)
            ->orderBy('last_heartbeat_at', 'desc')
            ->get()
            ->map(fn ($w) => array_merge($w->toArray(), ['is_online' => $w->isOnline()]));

        $query = WorkerJob::where('team_id', $teamId)
            ->with(['project:id,name,slug', 'creator:id,name', 'worker:id,name,machine_id']);

        if ($status = $request->get('status')) {
            $statuses = (array) $status;
            $query->whereIn('status', $statuses);
        }

        if ($projectSlug = $request->get('project')) {
            $project = Project::where('team_id', $teamId)->where('slug', $projectSlug)->first();
            if ($project) {
                $query->where('project_id', $project->id);
            }
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate(20);

        $projects = Project::where('team_id', $teamId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'worker_config']);

        $stats = [
            'total_jobs' => WorkerJob::where('team_id', $teamId)->count(),
            'pending' => WorkerJob::where('team_id', $teamId)->where('status', 'pending_approval')->count(),
            'queued' => WorkerJob::where('team_id', $teamId)->where('status', 'queued')->count(),
            'running' => WorkerJob::where('team_id', $teamId)->where('status', 'running')->count(),
            'done' => WorkerJob::where('team_id', $teamId)->where('status', 'done')->count(),
            'failed' => WorkerJob::where('team_id', $teamId)->where('status', 'failed')->count(),
        ];

        return Inertia::render('Workers/Index', [
            'workers' => $workers,
            'jobs' => $jobs,
            'projects' => $projects,
            'stats' => $stats,
            'filters' => $request->only(['status', 'project']),
        ]);
    }

    public function createJob(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $teamId = $team?->id ?? 0;

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'prompt' => 'required|string',
            'description' => 'nullable|string',
            'project_slug' => 'nullable|string',
            'type' => 'nullable|string|in:code_change,new_project,prepared',
            'priority' => 'nullable|string|in:low,medium,high,critical',
        ]);

        $projectId = null;
        $projectPath = null;
        $requiresApproval = config('claude-hub.worker.default_requires_approval', true);

        if (!empty($validated['project_slug'])) {
            $project = Project::where('team_id', $teamId)
                ->where('slug', $validated['project_slug'])
                ->first();

            if ($project) {
                $projectId = $project->id;
                $workerConfig = $project->worker_config ?? [];
                $requiresApproval = $workerConfig['requires_approval'] ?? $requiresApproval;
                $projectPath = $workerConfig['project_path'] ?? null;
            }
        }

        $job = WorkerJob::create([
            'team_id' => $teamId,
            'project_id' => $projectId,
            'created_by' => $user->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'prompt' => $validated['prompt'],
            'type' => $validated['type'] ?? 'code_change',
            'status' => $requiresApproval ? WorkerJob::STATUS_PENDING_APPROVAL : WorkerJob::STATUS_QUEUED,
            'priority' => $validated['priority'] ?? 'medium',
            'project_path' => $projectPath,
        ]);

        ActivityLogService::log('created', $job, $teamId, $user->id, $projectId);

        return back();
    }

    public function showJob(Request $request, int $id): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $job = WorkerJob::where('team_id', $team?->id ?? 0)
            ->with(['project:id,name,slug', 'creator:id,name', 'approver:id,name', 'worker:id,name,machine_id'])
            ->findOrFail($id);

        return Inertia::render('Workers/JobDetail', [
            'job' => $job,
        ]);
    }

    public function approveJob(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $job = WorkerJob::where('team_id', $team?->id ?? 0)->findOrFail($id);

        if ($job->status === WorkerJob::STATUS_PENDING_APPROVAL) {
            $job->update([
                'status' => WorkerJob::STATUS_QUEUED,
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
            ActivityLogService::log('approved', $job, $team->id, $user->id, $job->project_id);
        }

        return back();
    }

    public function cancelJob(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $job = WorkerJob::where('team_id', $team?->id ?? 0)->findOrFail($id);

        if (!$job->isTerminal()) {
            $job->update([
                'status' => WorkerJob::STATUS_CANCELLED,
                'completed_at' => now(),
            ]);
            ActivityLogService::log('cancelled', $job, $team->id, $user->id, $job->project_id);
        }

        return back();
    }

    public function retryJob(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $teamId = $team?->id ?? 0;
        $originalJob = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if (in_array($originalJob->status, [WorkerJob::STATUS_FAILED, WorkerJob::STATUS_CANCELLED])) {
            $newJob = WorkerJob::create([
                'team_id' => $teamId,
                'project_id' => $originalJob->project_id,
                'created_by' => $user->id,
                'title' => $originalJob->title,
                'description' => $originalJob->description,
                'prompt' => $originalJob->prompt,
                'type' => $originalJob->type,
                'status' => WorkerJob::STATUS_QUEUED,
                'priority' => $originalJob->priority,
                'project_path' => $originalJob->project_path,
                'working_directory' => $originalJob->working_directory,
                'environment' => $originalJob->environment,
                'metadata' => array_merge($originalJob->metadata ?? [], ['retried_from' => $originalJob->id]),
            ]);

            ActivityLogService::log('retried', $newJob, $teamId, $user->id, $newJob->project_id, [
                'original_job_id' => $originalJob->id,
            ]);
        }

        return back();
    }

    public function updateProjectWorkerConfig(Request $request, string $slug): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $project = Project::where('team_id', $team?->id ?? 0)
            ->where('slug', $slug)
            ->firstOrFail();

        $validated = $request->validate([
            'enabled' => 'boolean',
            'mode' => 'nullable|string|in:standard,prepared',
            'requires_approval' => 'boolean',
            'project_path' => 'nullable|string|max:500',
            'working_directory' => 'nullable|string|max:500',
            'default_environment' => 'nullable|array',
            'allowed_job_types' => 'nullable|array',
            'max_concurrent_jobs' => 'nullable|integer|min:1|max:5',
            'claude_flags' => 'nullable|string|max:255',
            'pre_commands' => 'nullable|array',
            'post_commands' => 'nullable|array',
        ]);

        $project->update(['worker_config' => $validated]);

        ActivityLogService::log('updated_worker_config', $project, $team->id, $user->id, $project->id);

        return back();
    }
}
