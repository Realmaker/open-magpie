<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEventRequest;
use App\Http\Resources\EventResource;
use App\Jobs\GenerateSummary;
use App\Models\Event;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use \App\Http\Controllers\Concerns\FindsProjectWithSharing;

    /**
     * List all events for a project.
     */
    public function index(Request $request, string $projectSlug)
    {
        $project = $this->findProject($request, $projectSlug);

        $query = Event::where('project_id', $project->id);

        // Support type filter (can be multiple)
        if ($request->has('type')) {
            $types = is_array($request->input('type'))
                ? $request->input('type')
                : [$request->input('type')];
            $query->whereIn('type', $types);
        }

        $events = $query->orderBy('created_at', 'desc')
                        ->paginate(20);

        return EventResource::collection($events);
    }

    /**
     * Create a new event.
     */
    public function store(StoreEventRequest $request, string $projectSlug)
    {
        $project = $this->findProject($request, $projectSlug, 'editor');
        $data = $request->validated();

        // Set project_id and user_id
        $data['project_id'] = $project->id;
        $data['user_id'] = $request->attributes->get('user')->id;

        $event = Event::create($data);

        // Touch project's last_activity_at
        $project->touch('last_activity_at');

        // Log activity
        ActivityLogService::logFromRequest($request, 'created', $event, $project->id);

        // Dispatch auto-summary job if content is long enough
        if (strlen($event->content) > 500) {
            GenerateSummary::dispatch($event->id);
        }

        return (new EventResource($event))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific event.
     */
    public function show(Request $request, string $projectSlug, int $id)
    {
        $project = $this->findProject($request, $projectSlug);

        $event = Event::where('project_id', $project->id)
            ->findOrFail($id);

        return new EventResource($event);
    }

    /**
     * Update an event.
     */
    public function update(Request $request, string $projectSlug, int $id)
    {
        $project = $this->findProject($request, $projectSlug, 'editor');

        $event = Event::where('project_id', $project->id)
            ->findOrFail($id);

        $data = $request->validate([
            'type' => 'sometimes|in:changelog,documentation,decision,milestone,note,task_update,session_summary,deployment,issue,review',
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'summary' => 'sometimes|string|nullable',
            'source' => 'sometimes|in:claude-code,manual,api,system',
            'source_session_id' => 'sometimes|string|nullable',
            'metadata' => 'sometimes|array|nullable',
        ]);

        $event->update($data);

        // Log activity
        ActivityLogService::logFromRequest($request, 'updated', $event, $project->id);

        return new EventResource($event);
    }

    /**
     * Delete an event.
     */
    public function destroy(Request $request, string $projectSlug, int $id)
    {
        $project = $this->findProject($request, $projectSlug, 'admin');

        $event = Event::where('project_id', $project->id)
            ->findOrFail($id);

        // Log activity before deletion
        ActivityLogService::logFromRequest($request, 'deleted', $event, $project->id);

        $event->delete();

        return response()->noContent();
    }

    /**
     * Find project - team-owned or shared.
     * Write operations require 'editor', delete requires 'admin'.
     */
    private function findProject(Request $request, string $slug, string $permission = 'viewer'): Project
    {
        return $this->findProjectWithSharing($request, $slug, $permission);
    }
}
