<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team) {
            return Inertia::render('Dashboard/Index', [
                'projects' => [],
                'recentEvents' => [],
                'stats' => ['projects' => 0, 'openTasks' => 0, 'eventsToday' => 0, 'documents' => 0, 'completedTasks' => 0, 'staleProjects' => 0],
            ]);
        }

        // Update health scores
        \App\Services\ProjectHealthService::updateForTeam($team->id);

        $projects = Project::where('team_id', $team->id)
            ->withCount(['events', 'documents', 'tasks'])
            ->orderBy('last_activity_at', 'desc')
            ->limit(10)
            ->get();

        $allProjectIds = Project::where('team_id', $team->id)->pluck('id');

        $recentEvents = \App\Models\Event::whereIn('project_id', $allProjectIds)
            ->with('project:id,name,slug')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $stats = [
            'projects' => Project::where('team_id', $team->id)->count(),
            'openTasks' => \App\Models\Task::whereIn('project_id', $allProjectIds)
                ->whereIn('status', ['open', 'in_progress'])
                ->count(),
            'eventsToday' => \App\Models\Event::whereIn('project_id', $allProjectIds)
                ->whereDate('created_at', today())
                ->count(),
            'documents' => \App\Models\Document::whereIn('project_id', $allProjectIds)->count(),
            'completedTasks' => \App\Models\Task::whereIn('project_id', $allProjectIds)
                ->where('status', 'done')
                ->count(),
            'staleProjects' => Project::where('team_id', $team->id)
                ->where(function ($q) {
                    $q->where('last_activity_at', '<', now()->subDays(7))
                      ->orWhereNull('last_activity_at');
                })
                ->where('status', 'active')
                ->count(),
        ];

        // Worker stats
        $workerStats = [
            'workersOnline' => \App\Models\Worker::where('team_id', $team->id)
                ->where('last_heartbeat_at', '>', now()->subSeconds(config('claude-hub.worker.heartbeat_timeout_seconds', 90)))
                ->count(),
            'jobsRunning' => \App\Models\WorkerJob::where('team_id', $team->id)
                ->where('status', 'running')
                ->count(),
            'jobsPending' => \App\Models\WorkerJob::where('team_id', $team->id)
                ->where('status', 'pending_approval')
                ->count(),
        ];

        return Inertia::render('Dashboard/Index', [
            'projects' => $projects,
            'recentEvents' => $recentEvents,
            'stats' => $stats,
            'workerStats' => $workerStats,
        ]);
    }
}
