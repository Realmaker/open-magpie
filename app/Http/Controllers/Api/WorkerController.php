<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ClaimWorkerJobRequest;
use App\Http\Requests\Api\CompleteWorkerJobRequest;
use App\Http\Requests\Api\WorkerHeartbeatRequest;
use App\Http\Resources\WorkerJobResource;
use App\Http\Resources\WorkerResource;
use App\Models\Event;
use App\Models\Worker;
use App\Models\WorkerJob;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
    /**
     * Send heartbeat and register/update worker.
     */
    public function heartbeat(WorkerHeartbeatRequest $request)
    {
        $data = $request->validated();
        $teamId = $request->attributes->get('team_id');

        $worker = Worker::updateOrCreate(
            ['machine_id' => $data['machine_id'], 'team_id' => $teamId],
            [
                'name' => $data['name'],
                'status' => $data['status'] ?? 'online',
                'version' => $data['version'] ?? null,
                'os_info' => $data['os_info'] ?? null,
                'capabilities' => $data['capabilities'] ?? null,
                'current_jobs' => $data['current_jobs'] ?? null,
                'max_parallel_jobs' => $data['max_parallel_jobs'] ?? 2,
                'last_heartbeat_at' => now(),
            ]
        );

        return (new WorkerResource($worker))
            ->additional(['message' => 'Heartbeat received']);
    }

    /**
     * List all workers for the team.
     */
    public function index(Request $request)
    {
        $workers = Worker::where('team_id', $request->attributes->get('team_id'))
            ->orderBy('last_heartbeat_at', 'desc')
            ->get();

        return WorkerResource::collection($workers);
    }

    /**
     * Get pending jobs that workers can claim.
     */
    public function pendingJobs(Request $request)
    {
        $jobs = WorkerJob::where('team_id', $request->attributes->get('team_id'))
            ->where('status', WorkerJob::STATUS_QUEUED)
            ->with(['project:id,name,slug,worker_config'])
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END")
            ->orderBy('created_at', 'asc')
            ->get();

        return WorkerJobResource::collection($jobs);
    }

    /**
     * Claim a job atomically.
     */
    public function claimJob(ClaimWorkerJobRequest $request, int $id)
    {
        $data = $request->validated();
        $teamId = $request->attributes->get('team_id');

        $job = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if ($job->status !== WorkerJob::STATUS_QUEUED) {
            return response()->json([
                'error' => [
                    'code' => 'job_not_available',
                    'message' => "Job is not in queued status (current: {$job->status})",
                ],
            ], 409);
        }

        // Verify worker belongs to same team
        $worker = Worker::where('team_id', $teamId)
            ->where('id', $data['worker_id'])
            ->where('machine_id', $data['machine_id'])
            ->first();

        if (!$worker) {
            return response()->json([
                'error' => [
                    'code' => 'worker_not_found',
                    'message' => 'Worker not found or does not belong to this team',
                ],
            ], 404);
        }

        // Atomic update: only succeeds if status is still 'queued'
        $updated = WorkerJob::where('id', $id)
            ->where('status', WorkerJob::STATUS_QUEUED)
            ->update([
                'status' => WorkerJob::STATUS_CLAIMED,
                'worker_id' => $worker->id,
                'claimed_at' => now(),
            ]);

        if (!$updated) {
            return response()->json([
                'error' => [
                    'code' => 'claim_failed',
                    'message' => 'Job was already claimed by another worker',
                ],
            ], 409);
        }

        $job->refresh();
        $job->load(['project:id,name,slug,worker_config']);

        return (new WorkerJobResource($job))
            ->additional(['message' => 'Job claimed successfully']);
    }

    /**
     * Mark a claimed job as running.
     */
    public function startJob(Request $request, int $id)
    {
        $teamId = $request->attributes->get('team_id');
        $job = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if ($job->status !== WorkerJob::STATUS_CLAIMED) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_status',
                    'message' => "Job must be in claimed status to start (current: {$job->status})",
                ],
            ], 409);
        }

        $job->update([
            'status' => WorkerJob::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        return (new WorkerJobResource($job))
            ->additional(['message' => 'Job started']);
    }

    /**
     * Complete a job (done or failed).
     */
    public function completeJob(CompleteWorkerJobRequest $request, int $id)
    {
        $data = $request->validated();
        $teamId = $request->attributes->get('team_id');
        $job = WorkerJob::where('team_id', $teamId)->findOrFail($id);

        if (!in_array($job->status, [WorkerJob::STATUS_RUNNING, WorkerJob::STATUS_CLAIMED])) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_status',
                    'message' => "Job must be running or claimed to complete (current: {$job->status})",
                ],
            ], 409);
        }

        $job->update([
            'status' => $data['status'],
            'output' => $data['output'] ?? null,
            'error_output' => $data['error_output'] ?? null,
            'exit_code' => $data['exit_code'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'result_summary' => $data['result_summary'] ?? null,
            'completed_at' => now(),
        ]);

        // Create timeline event if project is linked
        if ($job->project_id) {
            $eventType = $data['status'] === 'done' ? 'changelog' : 'issue';
            $eventTitle = $data['status'] === 'done'
                ? "Worker Job completed: {$job->title}"
                : "Worker Job failed: {$job->title}";

            $content = "## Worker Job: {$job->title}\n\n";
            $content .= "**Type:** {$job->type}\n";
            $content .= "**Status:** {$data['status']}\n";
            if ($job->duration_seconds) {
                $content .= "**Duration:** {$job->duration_seconds}s\n";
            }
            if ($data['result_summary']) {
                $content .= "\n### Summary\n{$data['result_summary']}\n";
            }

            Event::create([
                'project_id' => $job->project_id,
                'user_id' => $job->created_by,
                'type' => $eventType,
                'title' => $eventTitle,
                'content' => $content,
                'source' => 'system',
                'metadata' => [
                    'worker_job_id' => $job->id,
                    'exit_code' => $data['exit_code'] ?? null,
                ],
            ]);

            $job->project?->touchActivity();
        }

        ActivityLogService::log(
            'worker_job_completed',
            $job,
            $teamId,
            $job->created_by,
            $job->project_id,
            ['status' => $data['status'], 'exit_code' => $data['exit_code'] ?? null]
        );

        return (new WorkerJobResource($job))
            ->additional(['message' => "Job marked as {$data['status']}"]);
    }
}
