<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team) {
            return Inertia::render('Tasks/Index', [
                'tasks' => [],
                'projects' => [],
                'stats' => ['total' => 0, 'open' => 0, 'in_progress' => 0, 'done' => 0, 'deferred' => 0, 'cancelled' => 0, 'overdue' => 0],
                'filters' => [],
            ]);
        }

        $projectIds = Project::where('team_id', $team->id)->pluck('id');

        $query = Task::whereIn('project_id', $projectIds)
            ->with('project:id,name,slug');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($projectSlug = $request->get('project')) {
            $project = Project::where('team_id', $team->id)->where('slug', $projectSlug)->first();
            if ($project) {
                $query->where('project_id', $project->id);
            }
        }

        if ($search = $request->get('search')) {
            $escaped = QueryHelper::escapeLike($search);
            $query->where(function ($q) use ($escaped) {
                $q->where('title', 'like', "%{$escaped}%")
                    ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');
        $allowedSorts = ['created_at', 'updated_at', 'priority', 'due_date', 'status', 'title'];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }
        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $tasks = $query->orderBy($sort, $direction)->paginate(50)->withQueryString();

        $projects = Project::where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $allTasks = Task::whereIn('project_id', $projectIds);
        $stats = [
            'total' => (clone $allTasks)->count(),
            'open' => (clone $allTasks)->where('status', 'open')->count(),
            'in_progress' => (clone $allTasks)->where('status', 'in_progress')->count(),
            'done' => (clone $allTasks)->where('status', 'done')->count(),
            'deferred' => (clone $allTasks)->where('status', 'deferred')->count(),
            'cancelled' => (clone $allTasks)->where('status', 'cancelled')->count(),
            'overdue' => (clone $allTasks)->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->whereNotIn('status', ['done', 'cancelled'])
                ->count(),
        ];

        return Inertia::render('Tasks/Index', [
            'tasks' => $tasks,
            'projects' => $projects,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'priority', 'type', 'project', 'sort', 'direction']),
        ]);
    }
}
