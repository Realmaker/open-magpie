<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Event;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();
        $teamId = $team?->id ?? 0;

        $query = $request->get('q', '');
        $type = $request->get('type', 'all');
        $results = [];

        if (strlen($query) >= 2) {
            $escaped = QueryHelper::escapeLike($query);
            $projectIds = Project::where('team_id', $teamId)->pluck('id');

            if ($type === 'all' || $type === 'projects') {
                $projects = Project::where('team_id', $teamId)
                    ->where(function ($q) use ($escaped) {
                        $q->where('name', 'like', "%{$escaped}%")
                          ->orWhere('description', 'like', "%{$escaped}%")
                          ->orWhere('slug', 'like', "%{$escaped}%");
                    })
                    ->limit(10)
                    ->get()
                    ->map(fn ($p) => [
                        'id' => $p->id,
                        'type' => 'project',
                        'title' => $p->name,
                        'subtitle' => $p->status . ' · ' . ($p->category ?? 'No category'),
                        'excerpt' => $p->description ? Str::limit($p->description, 150) : null,
                        'url' => route('projects.show', $p->slug),
                        'date' => $p->updated_at->toISOString(),
                    ]);
                $results = array_merge($results, $projects->toArray());
            }

            if ($type === 'all' || $type === 'events') {
                $events = Event::whereIn('project_id', $projectIds)
                    ->where(function ($q) use ($escaped) {
                        $q->where('title', 'like', "%{$escaped}%")
                          ->orWhere('content', 'like', "%{$escaped}%");
                    })
                    ->with('project:id,name,slug')
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get()
                    ->map(fn ($e) => [
                        'id' => $e->id,
                        'type' => 'event',
                        'title' => $e->title,
                        'subtitle' => $e->type . ' · ' . ($e->project?->name ?? 'Unknown'),
                        'excerpt' => Str::limit(strip_tags($e->content), 150),
                        'url' => route('projects.show', $e->project?->slug ?? ''),
                        'date' => $e->created_at->toISOString(),
                    ]);
                $results = array_merge($results, $events->toArray());
            }

            if ($type === 'all' || $type === 'tasks') {
                $tasks = Task::whereIn('project_id', $projectIds)
                    ->where(function ($q) use ($escaped) {
                        $q->where('title', 'like', "%{$escaped}%")
                          ->orWhere('description', 'like', "%{$escaped}%");
                    })
                    ->with('project:id,name,slug')
                    ->orderBy('created_at', 'desc')
                    ->limit(15)
                    ->get()
                    ->map(fn ($t) => [
                        'id' => $t->id,
                        'type' => 'task',
                        'title' => $t->title,
                        'subtitle' => $t->status . ' · ' . $t->priority . ' · ' . ($t->project?->name ?? 'Unknown'),
                        'excerpt' => $t->description ? Str::limit($t->description, 150) : null,
                        'url' => route('projects.show', $t->project?->slug ?? ''),
                        'date' => $t->created_at->toISOString(),
                    ]);
                $results = array_merge($results, $tasks->toArray());
            }

            if ($type === 'all' || $type === 'documents') {
                $documents = Document::whereIn('project_id', $projectIds)
                    ->where(function ($q) use ($escaped) {
                        $q->where('title', 'like', "%{$escaped}%");
                    })
                    ->with('project:id,name,slug')
                    ->orderBy('updated_at', 'desc')
                    ->limit(15)
                    ->get()
                    ->map(fn ($d) => [
                        'id' => $d->id,
                        'type' => 'document',
                        'title' => $d->title,
                        'subtitle' => $d->category . ' · v' . $d->current_version . ' · ' . ($d->project?->name ?? 'Unknown'),
                        'excerpt' => null,
                        'url' => route('projects.show', $d->project?->slug ?? ''),
                        'date' => $d->updated_at->toISOString(),
                    ]);

                $docVersionResults = DocumentVersion::whereHas('document', function ($q) use ($projectIds) {
                        $q->whereIn('project_id', $projectIds);
                    })
                    ->where('content', 'like', "%{$escaped}%")
                    ->with(['document.project:id,name,slug'])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get()
                    ->map(fn ($v) => [
                        'id' => $v->document_id,
                        'type' => 'document',
                        'title' => $v->document?->title ?? 'Document',
                        'subtitle' => 'v' . $v->version . ' · ' . ($v->document?->project?->name ?? 'Unknown'),
                        'excerpt' => Str::limit(strip_tags($v->content), 150),
                        'url' => route('projects.show', $v->document?->project?->slug ?? ''),
                        'date' => $v->created_at->toISOString(),
                    ]);

                $allDocs = collect(array_merge($documents->toArray(), $docVersionResults->toArray()))
                    ->unique('id')
                    ->values()
                    ->toArray();
                $results = array_merge($results, $allDocs);
            }

            usort($results, fn ($a, $b) => strcmp($b['date'], $a['date']));
        }

        return Inertia::render('Search/Index', [
            'results' => $results,
            'query' => $query,
            'type' => $type,
            'totalResults' => count($results),
        ]);
    }
}
