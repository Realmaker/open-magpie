<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Display activity logs.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $query = ActivityLog::where('team_id', $team?->id ?? 0)
            ->with(['user:id,name', 'project:id,name,slug'])
            ->orderBy('created_at', 'desc');

        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        $logs = $query->paginate(30);

        return Inertia::render('ActivityLog/Index', [
            'logs' => $logs,
            'filters' => $request->only(['project_id', 'action']),
        ]);
    }
}
