<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreNoteRequest;
use App\Http\Resources\NoteResource;
use App\Models\Document;
use App\Models\Event;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    /**
     * List notes for a notable object.
     */
    public function index(Request $request)
    {
        $teamId = $request->attributes->get('team_id');

        $request->validate([
            'notable_type' => 'required|in:project,task,document,event',
            'notable_id' => 'required|integer',
        ]);

        $this->validateNotableAccess($request->input('notable_type'), $request->input('notable_id'), $teamId);

        $morphClass = $this->getMorphClass($request->input('notable_type'));

        $notes = Note::where('notable_type', $morphClass)
            ->where('notable_id', $request->input('notable_id'))
            ->whereNull('parent_id')
            ->with(['user', 'replies.user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return NoteResource::collection($notes);
    }

    /**
     * Create a new note.
     */
    public function store(StoreNoteRequest $request)
    {
        $teamId = $request->attributes->get('team_id');
        $data = $request->validated();

        $this->validateNotableAccess($data['notable_type'], $data['notable_id'], $teamId);

        $projectId = $this->getProjectId($data['notable_type'], $data['notable_id']);

        $note = Note::create([
            'user_id' => $request->attributes->get('user')->id,
            'notable_type' => $this->getMorphClass($data['notable_type']),
            'notable_id' => $data['notable_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'content' => $data['content'],
            'source' => $data['source'] ?? 'manual',
        ]);

        $note->load(['user', 'replies.user']);

        ActivityLogService::logFromRequest($request, 'created', $note, $projectId);

        return (new NoteResource($note))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a note.
     */
    public function update(Request $request, int $id)
    {
        $teamId = $request->attributes->get('team_id');
        $userId = $request->attributes->get('user')->id;

        // Find note scoped to team first (prevents IDOR / cross-team probing)
        $note = $this->findNoteInTeam($id, $teamId);

        // Only the author can edit their note
        if ($note->user_id !== $userId) {
            abort(403, 'You can only edit your own notes.');
        }

        $data = $request->validate([
            'content' => 'required|string|max:65535',
        ]);

        $note->update($data);
        $note->load(['user', 'replies.user']);

        $projectId = $this->getProjectId(
            $this->getTypeFromMorphClass($note->notable_type),
            $note->notable_id
        );
        ActivityLogService::logFromRequest($request, 'updated', $note, $projectId);

        return new NoteResource($note);
    }

    /**
     * Delete a note.
     */
    public function destroy(Request $request, int $id)
    {
        $teamId = $request->attributes->get('team_id');
        $userId = $request->attributes->get('user')->id;

        // Find note scoped to team first (prevents IDOR / cross-team probing)
        $note = $this->findNoteInTeam($id, $teamId);

        if ($note->user_id !== $userId) {
            abort(403, 'You can only delete your own notes.');
        }

        $projectId = $this->getProjectId(
            $this->getTypeFromMorphClass($note->notable_type),
            $note->notable_id
        );
        ActivityLogService::logFromRequest($request, 'deleted', $note, $projectId);

        $note->delete();

        return response()->noContent();
    }

    /**
     * Validate that the notable object belongs to the team.
     */
    private function validateNotableAccess(string $type, int $id, int $teamId): void
    {
        match ($type) {
            'project' => Project::where('team_id', $teamId)->findOrFail($id),
            'task' => Task::whereHas('project', fn ($q) => $q->where('team_id', $teamId))->findOrFail($id),
            'document' => Document::whereHas('project', fn ($q) => $q->where('team_id', $teamId))->findOrFail($id),
            'event' => Event::whereHas('project', fn ($q) => $q->where('team_id', $teamId))->findOrFail($id),
            default => abort(400, 'Invalid notable type.'),
        };
    }

    /**
     * Get the morph class for a type string.
     */
    private function getMorphClass(string $type): string
    {
        return match ($type) {
            'project' => Project::class,
            'task' => Task::class,
            'document' => Document::class,
            'event' => Event::class,
            default => abort(400, 'Invalid notable type.'),
        };
    }

    /**
     * Get the short type from a morph class.
     */
    private function getTypeFromMorphClass(string $morphClass): string
    {
        return match ($morphClass) {
            Project::class, 'App\Models\Project' => 'project',
            Task::class, 'App\Models\Task' => 'task',
            Document::class, 'App\Models\Document' => 'document',
            Event::class, 'App\Models\Event' => 'event',
            default => 'project',
        };
    }

    /**
     * Get the project ID from a notable object.
     */
    private function getProjectId(string $type, int $id): ?int
    {
        return match ($type) {
            'project' => $id,
            'task' => Task::find($id)?->project_id,
            'document' => Document::find($id)?->project_id,
            'event' => Event::find($id)?->project_id,
            default => null,
        };
    }

    /**
     * Find a note that belongs to a notable object within the given team.
     * Prevents IDOR by validating team ownership before revealing note existence.
     */
    private function findNoteInTeam(int $noteId, int $teamId): Note
    {
        $note = Note::findOrFail($noteId);

        // Validate that the notable object belongs to this team
        $type = $this->getTypeFromMorphClass($note->notable_type);
        $this->validateNotableAccess($type, $note->notable_id, $teamId);

        return $note;
    }
}
