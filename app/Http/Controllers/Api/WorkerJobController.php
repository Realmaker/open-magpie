<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWorkerJobRequest;
use App\Http\Requests\Api\UpdateWorkerJobRequest;
use App\Http\Resources\WorkerJobResource;
use App\Models\Project;
use App\Models\WorkerJob;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class WorkerJobController extends Controller
{
    /**
     * List all worker jobs for the team.
     */
    public function index(Request $request)
    {
        $teamId = $request->attributes->get('team_id');
        $query = WorkerJob::where('team_id', $teamId)
            ->with(['project:id,name,slug', 'creator:id,name', 'worker:id,name,machine_id']);

        if ($request->has('status')) {
            $statuses = (array) $request->input('status');
            $query->whereIn('status', $statuses);
        }

        if ($request->has('project_slug')) {
            $project = Project::where('team_id', $teamId)
                ->where('slug', $request->input('project_slug'))
                ->first();
            if ($project) {
                $query->where('project_id', $project->id);
            }
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate(20);

        return WorkerJobResource::collection($jobs);
    }

    /**
     * Create a new worker job.
     */
    public function store(StoreWorkerJobRequest $request)
    {
        $data = $request->validated();
        $teamId = $request->attributes->get('team_id');
        $user = $request->attributes->get('user');

        $projectId = null;
        $projectPath = $data['project_path'] ?? null;
        $requiresApproval = config('claude-hub.worker.default_requires_approval', true);

        // Resolve project if slug provided
        if (!empty($data['project_slug'])) {
            $project = Project::where('team_id', $teamId)
                ->where('slug', $data['project_slug'])
                ->first();

            if ($project) {
                $projectId = $project->id;
                $workerConfig = $project->worker_config ?? [];
                $requiresApproval = $workerConfig['requires_approval'] ?? $requiresApproval;
                $projectPath = $projectPath ?? ($workerConfig['project_path'] ?? null);
            }
        }

        $job = WorkerJob::create([
            'team_id' => $teamId,
            'project_id' => $projectId,
            'created_by' => $user->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'prompt' => $data['prompt'],
            'type' => $data['type'] ?? WorkerJob::TYPE_CODE_CHANGE,
            'status' => $requiresApproval ? WorkerJob::STATUS_PENDING_APPROVAL : WorkerJob::STATUS_QUEUED,
            'priority' => $data['priority'] ?? 'medium',
            'project_path' => $projectPath,
            'working_directory' => $data['working_directory'] ?? null,
            'environment' => $data['environment'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        $job->load(['project:id,name,slug', 'creator:id,name']);

        ActivityLogService::logFromRequest($request, 'created', $job, $projectId);

        return (new WorkerJobResource($job))
            ->additional(['message' => $requiresApproval ? 'Job created, awaiting approval' : 'Job created and queued'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific job.
     */
    public function show(Request $request, int $id)
    {
        $job = WorkerJob::where('team_id', $request->attributes->get('team_id'))
            ->with(['project:id,name,slug', 'creator:id,name', 'approver:id,name', 'worker:id,name,machine_id'])
            ->findOrFail($id);

        return new WorkerJobResource($job);
    }

    /**
     * Update a job (only if not yet running).
     */
    public function update(UpdateWorkerJobRequest $request, int $id)
    {
        $teamId = $request->attributes->get('team_id');
        $job = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if (!in_array($job->status, [WorkerJob::STATUS_PENDING_APPROVAL, WorkerJob::STATUS_APPROVED, WorkerJob::STATUS_QUEUED])) {
            return response()->json([
                'error' => [
                    'code' => 'job_not_editable',
                    'message' => 'Job can only be edited before it starts running',
                ],
            ], 409);
        }

        $job->update($request->validated());

        ActivityLogService::logFromRequest($request, 'updated', $job, $job->project_id);

        return (new WorkerJobResource($job))
            ->additional(['message' => 'Job updated']);
    }

    /**
     * Approve a pending job.
     */
    public function approve(Request $request, int $id)
    {
        $teamId = $request->attributes->get('team_id');
        $user = $request->attributes->get('user');
        $job = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if ($job->status !== WorkerJob::STATUS_PENDING_APPROVAL) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_status',
                    'message' => "Job is not pending approval (current: {$job->status})",
                ],
            ], 409);
        }

        $job->update([
            'status' => WorkerJob::STATUS_QUEUED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        ActivityLogService::logFromRequest($request, 'approved', $job, $job->project_id);

        return (new WorkerJobResource($job))
            ->additional(['message' => 'Job approved and queued']);
    }

    /**
     * Cancel a job.
     */
    public function cancel(Request $request, int $id)
    {
        $teamId = $request->attributes->get('team_id');
        $job = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if ($job->isTerminal()) {
            return response()->json([
                'error' => [
                    'code' => 'job_already_terminal',
                    'message' => "Job is already in terminal status: {$job->status}",
                ],
            ], 409);
        }

        $job->update([
            'status' => WorkerJob::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        ActivityLogService::logFromRequest($request, 'cancelled', $job, $job->project_id);

        return (new WorkerJobResource($job))
            ->additional(['message' => 'Job cancelled']);
    }

    /**
     * Retry a failed job by creating a copy.
     */
    public function retry(Request $request, int $id)
    {
        $teamId = $request->attributes->get('team_id');
        $user = $request->attributes->get('user');
        $originalJob = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if (!in_array($originalJob->status, [WorkerJob::STATUS_FAILED, WorkerJob::STATUS_CANCELLED])) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_status',
                    'message' => 'Only failed or cancelled jobs can be retried',
                ],
            ], 409);
        }

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
            'metadata' => array_merge(
                $originalJob->metadata ?? [],
                ['retried_from' => $originalJob->id]
            ),
        ]);

        $newJob->load(['project:id,name,slug', 'creator:id,name']);

        ActivityLogService::logFromRequest($request, 'retried', $newJob, $newJob->project_id, [
            'original_job_id' => $originalJob->id,
        ]);

        return (new WorkerJobResource($newJob))
            ->additional(['message' => 'Job retried - new job created and queued'])
            ->response()
            ->setStatusCode(201);
    }
}
