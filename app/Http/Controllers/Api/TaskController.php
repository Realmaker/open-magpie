<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\BulkStoreTaskRequest;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use \App\Http\Controllers\Concerns\FindsProjectWithSharing;

    /**
     * List all tasks for a project.
     */
    public function index(Request $request, string $projectSlug)
    {
        $project = $this->findProject($request, $projectSlug);

        $query = Task::where('project_id', $project->id);

        // Support status filter (can be multiple)
        if ($request->has('status')) {
            $statuses = is_array($request->input('status'))
                ? $request->input('status')
                : [$request->input('status')];
            $query->whereIn('status', $statuses);
        }

        // Support priority filter
        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        // Support type filter
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $tasks = $query->orderBy('sort_order')
                       ->orderBy('created_at', 'desc')
                       ->paginate(20);

        return TaskResource::collection($tasks);
    }

    /**
     * Create a new task.
     */
    public function store(StoreTaskRequest $request, string $projectSlug)
    {
        $project = $this->findProject($request, $projectSlug, 'editor');
        $data = $request->validated();

        // Set project_id and created_by
        $data['project_id'] = $project->id;
        $data['created_by'] = $request->attributes->get('user')->id;

        $task = Task::create($data);

        // Touch project's last_activity_at
        $project->touch('last_activity_at');

        // Log activity
        ActivityLogService::logFromRequest($request, 'created', $task, $project->id);

        return (new TaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific task.
     */
    public function show(Request $request, string $projectSlug, int $id)
    {
        $project = $this->findProject($request, $projectSlug);

        $task = Task::where('project_id', $project->id)
            ->findOrFail($id);

        return new TaskResource($task);
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, string $projectSlug, int $id)
    {
        $project = $this->findProject($request, $projectSlug, 'editor');

        $task = Task::where('project_id', $project->id)
            ->findOrFail($id);

        $data = $request->validated();

        // Track old status for logging
        $oldStatus = $task->status;

        // If status is changing to 'done', set completed_at
        if (isset($data['status']) && $data['status'] === 'done' && $task->status !== 'done') {
            $data['completed_at'] = now();
        }

        // If status is changing from 'done' to something else, clear completed_at
        if (isset($data['status']) && $data['status'] !== 'done' && $task->status === 'done') {
            $data['completed_at'] = null;
        }

        $task->update($data);

        // Touch project's last_activity_at
        $project->touch('last_activity_at');

        // Log activity
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            // Log status change
            ActivityLogService::logFromRequest($request, 'status_changed', $task, $project->id, [
                'old_status' => $oldStatus,
                'new_status' => $data['status']
            ]);
        } else {
            // Log general update
            ActivityLogService::logFromRequest($request, 'updated', $task, $project->id);
        }

        return new TaskResource($task);
    }

    /**
     * Soft delete a task.
     */
    public function destroy(Request $request, string $projectSlug, int $id)
    {
        $project = $this->findProject($request, $projectSlug, 'admin');

        $task = Task::where('project_id', $project->id)
            ->findOrFail($id);

        // Log activity before deletion
        ActivityLogService::logFromRequest($request, 'deleted', $task, $project->id);

        $task->delete();

        return response()->noContent();
    }

    /**
     * Bulk create tasks.
     */
    public function bulkStore(BulkStoreTaskRequest $request, string $projectSlug)
    {
        $project = $this->findProject($request, $projectSlug, 'editor');
        $data = $request->validated();

        $tasks = [];
        foreach ($data['tasks'] as $taskData) {
            $taskData['project_id'] = $project->id;
            $taskData['created_by'] = $request->attributes->get('user')->id;

            $tasks[] = Task::create($taskData);
        }

        // Touch project's last_activity_at
        $project->touch('last_activity_at');

        // Log bulk creation activity
        ActivityLogService::logFromRequest($request, 'bulk_created', $project, $project->id, [
            'count' => count($tasks),
            'task_ids' => array_map(fn($task) => $task->id, $tasks)
        ]);

        return TaskResource::collection(collect($tasks))
            ->response()
            ->setStatusCode(201);
    }

    private function findProject(Request $request, string $slug, string $permission = 'viewer'): Project
    {
        return $this->findProjectWithSharing($request, $slug, $permission);
    }
}
