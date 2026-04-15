<?php

namespace App\Http\Controllers\Web;

use App\Helpers\QueryHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\FindsProjectWithSharing;
use App\Mail\ProjectShareInvitation;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectShare;
use App\Models\ProjectSnapshot;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use FindsProjectWithSharing;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $query = Project::where('team_id', $team?->id ?? 0)
            ->withCount(['events', 'documents', 'tasks']);

        if ($search = $request->get('search')) {
            $escaped = QueryHelper::escapeLike($search);
            $query->where(function ($q) use ($escaped) {
                $q->where('name', 'like', "%{$escaped}%")
                    ->orWhere('slug', 'like', "%{$escaped}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $projects = $query->orderBy('last_activity_at', 'desc')->paginate(20);

        // Count shared projects for the sidebar indicator
        $sharedCount = ProjectShare::where('shared_with_user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status']),
            'sharedProjectsCount' => $sharedCount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team) {
            return back()->withErrors(['team' => 'No team found.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,paused,completed,archived',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'category' => 'nullable|string|max:255',
            'repository_url' => 'nullable|string|url|max:255',
            'tech_stack' => 'nullable|string|max:500',
        ]);

        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Project::where('team_id', $team->id)->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $techStack = null;
        if (!empty($validated['tech_stack'])) {
            $techStack = array_map('trim', explode(',', $validated['tech_stack']));
        }

        $project = Project::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'priority' => $validated['priority'] ?? 'medium',
            'category' => $validated['category'] ?? null,
            'repository_url' => $validated['repository_url'] ?? null,
            'tech_stack' => $techStack,
            'last_activity_at' => now(),
        ]);

        ActivityLogService::log('created', $project, $team->id, $user->id, $project->id);

        return redirect()->route('projects.show', $project->slug);
    }

    public function update(Request $request, string $slug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'admin');

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|in:active,paused,completed,archived',
            'priority' => 'sometimes|string|in:low,medium,high,critical',
            'category' => 'nullable|string|max:255',
            'repository_url' => 'nullable|string|max:255',
        ]);

        $project->update($validated);
        ActivityLogService::log('updated', $project, $project->team_id, $request->user()->id, $project->id);

        return back();
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        // Only team-owned projects can be deleted
        $team = $request->user()->currentTeam();
        $project = Project::where('team_id', $team?->id ?? 0)
            ->where('slug', $slug)
            ->firstOrFail();
        ActivityLogService::log('deleted', $project, $project->team_id, $request->user()->id, $project->id);
        $project->delete();

        return redirect()->route('projects.index');
    }

    public function show(Request $request, string $slug): Response
    {
        $user = $request->user();
        $project = $this->findProject($request, $slug);
        $project->loadCount(['events', 'documents', 'tasks']);

        $events = $project->events()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $tasks = $project->tasks()
            ->orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get();

        $documents = $project->documents()
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($doc) {
                $version = $doc->currentVersionContent();
                $doc->content = $version?->content;
                return $doc;
            });

        $notes = $project->notes()
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        $workerJobs = $project->workerJobs()
            ->with(['creator:id,name', 'worker:id,name,machine_id'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Determine user's access level for this project
        $team = $user->currentTeam();
        $isTeamOwned = $project->team_id === ($team?->id ?? 0);
        $share = $isTeamOwned ? null : $project->getShareFor($user);
        $userPermission = $isTeamOwned ? 'owner' : ($share?->permission ?? 'viewer');

        // Load shares for project admins/owners
        $shares = ($userPermission === 'owner' || $userPermission === 'admin')
            ? $project->shares()->with(['sharedBy:id,name,email', 'sharedWithUser:id,name,email'])->get()
            : [];

        // Snapshots
        $latestSnapshot = $project->snapshots()
            ->with('uploader:id,name')
            ->orderBy('version', 'desc')
            ->first();

        $snapshotVersions = $project->snapshots()
            ->with('uploader:id,name')
            ->orderBy('version', 'desc')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'version' => $s->version,
                'human_size' => $s->human_size,
                'file_count' => $s->file_count,
                'change_note' => $s->change_note,
                'source' => $s->source,
                'uploaded_by' => $s->uploader?->name,
                'created_at' => $s->created_at,
            ]);

        return Inertia::render('Projects/Show', [
            'project' => $project,
            'events' => $events,
            'tasks' => $tasks,
            'documents' => $documents,
            'notes' => $notes,
            'workerJobs' => $workerJobs,
            'userPermission' => $userPermission,
            'shares' => $shares,
            'latestSnapshot' => $latestSnapshot ? [
                'version' => $latestSnapshot->version,
                'file_tree' => $latestSnapshot->file_tree,
                'file_count' => $latestSnapshot->file_count,
                'human_size' => $latestSnapshot->human_size,
                'change_note' => $latestSnapshot->change_note,
                'created_at' => $latestSnapshot->created_at,
            ] : null,
            'snapshotVersions' => $snapshotVersions,
            'installNotes' => $project->install_notes,
        ]);
    }

    private function findProject(Request $request, string $slug, string $permission = 'viewer'): Project
    {
        return $this->findProjectWithSharing($request, $slug, $permission);
    }

    // --- Document CRUD ---

    public function storeDocument(Request $request, string $slug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'editor');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string',
            'content' => 'required|string',
            'change_note' => 'nullable|string|max:500',
            'source' => 'nullable|string',
        ]);

        $document = $project->documents()->create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'category' => $validated['category'] ?? 'documentation',
            'source' => $validated['source'] ?? 'manual',
            'created_by' => $request->user()->id,
            'current_version' => 1,
        ]);

        $document->versions()->create([
            'version' => 1,
            'content' => $validated['content'],
            'change_note' => $validated['change_note'] ?? 'Initial version',
            'created_by' => $request->user()->id,
        ]);

        $project->touchActivity();
        ActivityLogService::log('created', $document, $project->team_id, $request->user()->id, $project->id);

        return back();
    }

    public function updateDocument(Request $request, string $slug, string $docSlug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'editor');
        $document = $project->documents()->where('slug', $docSlug)->firstOrFail();

        $validated = $request->validate([
            'content' => 'required|string',
            'change_note' => 'nullable|string|max:500',
        ]);

        $newVersion = $document->current_version + 1;

        $document->versions()->create([
            'version' => $newVersion,
            'content' => $validated['content'],
            'change_note' => $validated['change_note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $document->update(['current_version' => $newVersion]);
        $project->touchActivity();
        ActivityLogService::log('version_created', $document, $project->team_id, $request->user()->id, $project->id, ['version' => $newVersion]);

        return back();
    }

    public function destroyDocument(Request $request, string $slug, string $docSlug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'admin');
        $document = $project->documents()->where('slug', $docSlug)->firstOrFail();

        ActivityLogService::log('deleted', $document, $project->team_id, $request->user()->id, $project->id);
        $document->delete();

        return back();
    }

    // --- Task CRUD ---

    public function storeTask(Request $request, string $slug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'editor');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'type' => 'nullable|string',
            'due_date' => 'nullable|date',
            'source' => 'nullable|string',
        ]);

        $task = $project->tasks()->create([
            ...$validated,
            'created_by' => $request->user()->id,
            'source' => $validated['source'] ?? 'manual',
        ]);

        $project->touchActivity();
        ActivityLogService::log('created', $task, $project->team_id, $request->user()->id, $project->id);

        return back();
    }

    public function updateTask(Request $request, string $slug, int $id): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'editor');
        $task = $project->tasks()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|string',
            'priority' => 'sometimes|string',
            'type' => 'sometimes|string',
            'due_date' => 'nullable|date',
        ]);

        $oldStatus = $task->status;
        $task->update($validated);

        if (isset($validated['status']) && $validated['status'] === 'done' && $oldStatus !== 'done') {
            $task->update(['completed_at' => now()]);
        } elseif (isset($validated['status']) && $validated['status'] !== 'done') {
            $task->update(['completed_at' => null]);
        }

        $project->touchActivity();
        ActivityLogService::log('updated', $task, $project->team_id, $request->user()->id, $project->id);

        return back();
    }

    public function destroyTask(Request $request, string $slug, int $id): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'admin');
        $task = $project->tasks()->findOrFail($id);

        ActivityLogService::log('deleted', $task, $project->team_id, $request->user()->id, $project->id);
        $task->delete();

        return back();
    }

    // --- Note CRUD ---

    public function storeNote(Request $request, string $slug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'editor');

        $validated = $request->validate([
            'content' => 'required|string',
            'parent_id' => 'nullable|integer|exists:notes,id',
        ]);

        $note = $project->notes()->create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
            'parent_id' => $validated['parent_id'] ?? null,
            'source' => 'manual',
        ]);

        $project->touchActivity();
        ActivityLogService::log('created', $note, $project->team_id, $request->user()->id, $project->id);

        return back();
    }

    public function updateNote(Request $request, int $id): RedirectResponse
    {
        $note = Note::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $note->update($validated);

        return back();
    }

    public function destroyNote(Request $request, int $id): RedirectResponse
    {
        $note = Note::where('user_id', $request->user()->id)->findOrFail($id);

        $note->delete();

        return back();
    }

    // --- Snapshots ---

    public function storeSnapshot(Request $request, string $slug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'editor');
        $user = $request->user();

        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'change_note' => ['nullable', 'string', 'max:500'],
            'install_notes' => ['nullable', 'string', 'max:50000'],
        ]);

        $file = $request->file('file');
        $lastVersion = $project->snapshots()->max('version') ?? 0;
        $newVersion = $lastVersion + 1;

        $storagePath = ProjectSnapshot::storagePath($project->id);
        $fileName = "v{$newVersion}.zip";
        $path = $file->storeAs($storagePath, $fileName, 'local');

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        [$fileTree, $fileCount] = ProjectSnapshot::buildFileTreeFromZip($fullPath);

        ProjectSnapshot::create([
            'project_id' => $project->id,
            'version' => $newVersion,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_count' => $fileCount,
            'file_tree' => $fileTree,
            'change_note' => $request->input('change_note'),
            'source' => 'manual',
            'uploaded_by' => $user->id,
        ]);

        if ($request->has('install_notes')) {
            $project->update(['install_notes' => $request->input('install_notes')]);
        }

        $project->touchActivity();
        ActivityLogService::log('snapshot_uploaded', $project, $project->team_id, $user->id, $project->id, [
            'version' => $newVersion,
            'file_count' => $fileCount,
        ]);

        return back()->with('success', "Snapshot v{$newVersion} hochgeladen ({$fileCount} Dateien).");
    }

    public function downloadSnapshot(Request $request, string $slug, int $version)
    {
        $project = $this->findProject($request, $slug);

        $snapshot = $project->snapshots()
            ->where('version', $version)
            ->firstOrFail();

        $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($snapshot->file_path);

        if (!file_exists($fullPath)) {
            return back()->withErrors(['file' => 'Snapshot-Datei nicht gefunden.']);
        }

        return response()->download($fullPath, "{$project->slug}-v{$version}.zip");
    }

    public function updateInstallNotes(Request $request, string $slug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'editor');

        $validated = $request->validate([
            'install_notes' => ['required', 'string', 'max:50000'],
        ]);

        $project->update(['install_notes' => $validated['install_notes']]);

        return back()->with('success', 'Installationsanleitung aktualisiert.');
    }

    // --- Project Shares ---

    public function shares(Request $request, string $slug): Response
    {
        $project = $this->findProject($request, $slug, 'admin');

        $shares = $project->shares()
            ->with(['sharedBy:id,name,email', 'sharedWithUser:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Projects/Shares', [
            'project' => $project,
            'shares' => $shares,
        ]);
    }

    public function storeShare(Request $request, string $slug): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'admin');
        $user = $request->user();

        // Only team owners/admins can share
        if (!$user->isTeamAdmin()) {
            abort(403, 'Nur Team-Administratoren duerfen Projekte teilen.');
        }

        $validated = $request->validate([
            'email' => 'required|email:rfc,dns|max:255',
            'permission' => 'required|string|in:viewer,editor,admin',
        ]);

        $email = strtolower(trim($validated['email']));

        // Cannot share with yourself
        if ($email === strtolower($user->email)) {
            return back()->withErrors(['email' => 'Du kannst ein Projekt nicht mit dir selbst teilen.']);
        }

        // Check if already shared
        if ($project->shares()->where('shared_with_email', $email)->exists()) {
            return back()->withErrors(['email' => 'Dieses Projekt wurde bereits mit dieser E-Mail geteilt.']);
        }

        $tokenData = ProjectShare::generateInviteToken();
        $targetUser = User::where('email', $email)->first();

        // Auto-accept for registered users
        $isAutoAccepted = $targetUser !== null;

        $share = ProjectShare::create([
            'project_id' => $project->id,
            'shared_by' => $user->id,
            'shared_with_user_id' => $targetUser?->id,
            'shared_with_email' => $email,
            'permission' => $validated['permission'],
            'invite_token' => $tokenData['hashed'],
            'accepted_at' => $isAutoAccepted ? now() : null,
            'expires_at' => $isAutoAccepted ? null : now()->addDays(7),
        ]);

        // Only send email if user is not yet registered
        if (!$isAutoAccepted) {
            $acceptUrl = route('shares.accept', $tokenData['plain']);

            Mail::to($email)->queue(new ProjectShareInvitation(
                project: $project,
                sharedBy: $user,
                permission: $validated['permission'],
                acceptUrl: $acceptUrl,
                recipientEmail: $email,
            ));
        }

        ActivityLogService::log('project_shared', $project, $project->team_id, $user->id, $project->id, [
            'shared_with' => $email,
            'permission' => $validated['permission'],
        ]);

        $message = $isAutoAccepted
            ? "Projekt mit {$email} geteilt (sofort aktiv)."
            : "Einladung an {$email} gesendet.";

        return back()->with('success', $message);
    }

    public function updateShare(Request $request, string $slug, int $id): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'admin');

        $share = $project->shares()->findOrFail($id);

        $validated = $request->validate([
            'permission' => 'required|string|in:viewer,editor,admin',
        ]);

        $share->update(['permission' => $validated['permission']]);

        ActivityLogService::log('share_permission_changed', $project, $project->team_id, $request->user()->id, $project->id, [
            'email' => $share->shared_with_email,
            'new_permission' => $validated['permission'],
        ]);

        return back()->with('success', 'Berechtigung aktualisiert.');
    }

    public function destroyShare(Request $request, string $slug, int $id): RedirectResponse
    {
        $project = $this->findProject($request, $slug, 'admin');

        $share = $project->shares()->findOrFail($id);

        ActivityLogService::log('share_revoked', $project, $project->team_id, $request->user()->id, $project->id, [
            'email' => $share->shared_with_email,
        ]);

        $share->delete();

        return back()->with('success', 'Freigabe widerrufen.');
    }

    public function sharedWithMe(Request $request): Response
    {
        $user = $request->user();

        $shares = ProjectShare::where('shared_with_user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->with(['project' => function ($q) {
                $q->withCount(['events', 'documents', 'tasks']);
            }, 'sharedBy:id,name,email'])
            ->get();

        return Inertia::render('Projects/SharedWithMe', [
            'shares' => $shares,
        ]);
    }
}
