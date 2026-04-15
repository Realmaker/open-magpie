<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $teamId = $team?->id ?? 0;

        $projectIds = Project::where('team_id', $teamId)->pluck('id');

        $query = Event::whereIn('project_id', $projectIds)
            ->with(['project:id,name,slug', 'user:id,name']);

        // Filter by type
        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        // Filter by project
        if ($projectSlug = $request->get('project')) {
            $project = Project::where('team_id', $teamId)->where('slug', $projectSlug)->first();
            if ($project) {
                $query->where('project_id', $project->id);
            }
        }

        $events = $query->orderBy('created_at', 'desc')->paginate(20);

        $projects = Project::where('team_id', $teamId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Inbox/Index', [
            'events' => $events,
            'projects' => $projects,
            'filters' => $request->only(['type', 'project']),
        ]);
    }
}
