<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectRequest;
use App\Http\Requests\Api\UpdateProjectRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\ProjectResource;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    use \App\Http\Controllers\Concerns\FindsProjectWithSharing;

    /**
     * List all projects for the authenticated team.
     * Use ?include_shared=1 to also include projects shared with the user.
     */
    public function index(Request $request)
    {
        $teamId = $request->attributes->get('team_id');
        $user = $request->attributes->get('user');

        $query = Project::where(function ($q) use ($teamId, $user, $request) {
            // Team-owned projects
            $q->where('team_id', $teamId);

            // Optionally include shared projects
            if ($request->boolean('include_shared') && $user) {
                $q->orWhereHas('shares', function ($sq) use ($user) {
                    $sq->where('shared_with_user_id', $user->id)
                       ->whereNotNull('accepted_at')
                       ->where(function ($q2) {
                           $q2->whereNull('expires_at')
                             ->orWhere('expires_at', '>', now());
                       });
                });
            }
        });

        // Support search query parameter
        if ($request->has('search')) {
            $search = QueryHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Support status filter
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $projects = $query->orderBy('last_activity_at', 'desc')
                          ->paginate(20);

        return ProjectResource::collection($projects);
    }

    /**
     * Create a new project.
     */
    public function store(StoreProjectRequest $request)
    {
        $data = $request->validated();

        // Set team_id and created_by from middleware attributes
        $data['team_id'] = $request->attributes->get('team_id');
        $data['created_by'] = $request->attributes->get('user')->id;

        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // Ensure slug is unique within team
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Project::where('team_id', $data['team_id'])
                      ->where('slug', $data['slug'])
                      ->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Set initial last_activity_at
        $data['last_activity_at'] = now();

        $project = Project::create($data);

        // Log activity
        ActivityLogService::logFromRequest($request, 'created', $project, $project->id);

        return (new ProjectResource($project))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific project by slug.
     */
    public function show(Request $request, string $slug)
    {
        $project = $this->findProject($request, $slug);
        return new ProjectResource($project);
    }

    /**
     * Update a project.
     */
    public function update(UpdateProjectRequest $request, string $slug)
    {
        $project = $this->findProject($request, $slug, 'admin');
        $data = $request->validated();

        // If slug is being updated, ensure uniqueness
        if (isset($data['slug']) && $data['slug'] !== $project->slug) {
            $originalSlug = $data['slug'];
            $counter = 1;
            while (Project::where('team_id', $project->team_id)
                          ->where('slug', $data['slug'])
                          ->where('id', '!=', $project->id)
                          ->exists()) {
                $data['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $project->update($data);

        // Log activity
        ActivityLogService::logFromRequest($request, 'updated', $project, $project->id, ['changed' => $request->validated()]);

        return new ProjectResource($project);
    }

    /**
     * Soft delete a project.
     */
    public function destroy(Request $request, string $slug)
    {
        // Only team-owned projects can be deleted (not shared ones)
        $project = Project::where('team_id', $request->attributes->get('team_id'))
            ->where('slug', $slug)
            ->firstOrFail();

        // Log activity before deletion
        ActivityLogService::logFromRequest($request, 'deleted', $project, $project->id);

        $project->delete();

        return response()->noContent();
    }

    /**
     * Get project statistics.
     */
    public function stats(Request $request, string $slug)
    {
        $project = $this->findProject($request, $slug);

        $stats = [
            'event_count' => $project->events()->count(),
            'document_count' => $project->documents()->count(),
            'task_counts' => [
                'total' => $project->tasks()->count(),
                'open' => $project->tasks()->where('status', 'open')->count(),
                'in_progress' => $project->tasks()->where('status', 'in_progress')->count(),
                'done' => $project->tasks()->where('status', 'done')->count(),
                'deferred' => $project->tasks()->where('status', 'deferred')->count(),
                'cancelled' => $project->tasks()->where('status', 'cancelled')->count(),
            ],
            'health_score' => $project->health_score,
            'last_activity_at' => $project->last_activity_at,
        ];

        return response()->json(['data' => $stats]);
    }

    /**
     * Get activity log for a project.
     */
    public function activity(Request $request, string $slug)
    {
        $project = $this->findProject($request, $slug);

        $logs = ActivityLog::where('project_id', $project->id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return ActivityLogResource::collection($logs);
    }

    /**
     * Find project by slug - team-owned or shared.
     */
    private function findProject(Request $request, string $slug, string $permission = 'viewer'): Project
    {
        return $this->findProjectWithSharing($request, $slug, $permission);
    }
}
