<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDocumentRequest;
use App\Http\Requests\Api\UpdateDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    use \App\Http\Controllers\Concerns\FindsProjectWithSharing;

    /**
     * List all documents for a project.
     */
    public function index(Request $request, string $projectSlug)
    {
        $project = $this->findProject($request, $projectSlug);

        $query = Document::where('project_id', $project->id);

        // Support category filter
        if ($request->has('category')) {
            $query->where('category', $request->input('category'));
        }

        $documents = $query->orderBy('created_at', 'desc')
                           ->paginate(20);

        return DocumentResource::collection($documents);
    }

    /**
     * Create a new document with first version.
     */
    public function store(StoreDocumentRequest $request, string $projectSlug)
    {
        $project = $this->findProject($request, $projectSlug, 'editor');
        $data = $request->validated();

        // Set project_id and created_by
        $data['project_id'] = $project->id;
        $data['created_by'] = $request->attributes->get('user')->id;

        // Auto-generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Ensure slug is unique within project
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Document::where('project_id', $project->id)
                       ->where('slug', $data['slug'])
                       ->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        // Extract content and change_note for version
        $content = $data['content'];
        $changeNote = $data['change_note'] ?? 'Initial version';
        unset($data['content'], $data['change_note']);

        // Set initial version
        $data['current_version'] = 1;

        $document = Document::create($data);

        // Create first version
        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => 1,
            'content' => $content,
            'change_note' => $changeNote,
            'created_by' => $request->attributes->get('user')->id,
        ]);

        // Touch project's last_activity_at
        $project->touch('last_activity_at');

        // Log activity
        ActivityLogService::logFromRequest($request, 'created', $document, $project->id);

        return (new DocumentResource($document->load('currentVersion')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a specific document with current version.
     */
    public function show(Request $request, string $projectSlug, string $docSlug)
    {
        $project = $this->findProject($request, $projectSlug);

        $document = Document::where('project_id', $project->id)
            ->where('slug', $docSlug)
            ->with('currentVersion')
            ->firstOrFail();

        return new DocumentResource($document);
    }

    /**
     * Create a new version of the document (update).
     */
    public function update(UpdateDocumentRequest $request, string $projectSlug, string $docSlug)
    {
        $project = $this->findProject($request, $projectSlug, 'editor');

        $document = Document::where('project_id', $project->id)
            ->where('slug', $docSlug)
            ->firstOrFail();

        $data = $request->validated();

        // Extract content and change_note for new version
        $content = $data['content'];
        $changeNote = $data['change_note'] ?? 'Updated';
        unset($data['content'], $data['change_note']);

        // Update document metadata if provided
        if (!empty($data)) {
            // If slug is being updated, ensure uniqueness
            if (isset($data['slug']) && $data['slug'] !== $document->slug) {
                $originalSlug = $data['slug'];
                $counter = 1;
                while (Document::where('project_id', $project->id)
                               ->where('slug', $data['slug'])
                               ->where('id', '!=', $document->id)
                               ->exists()) {
                    $data['slug'] = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            $document->update($data);
        }

        // Increment version
        $newVersion = $document->current_version + 1;
        $document->update(['current_version' => $newVersion]);

        // Create new version
        DocumentVersion::create([
            'document_id' => $document->id,
            'version' => $newVersion,
            'content' => $content,
            'change_note' => $changeNote,
            'created_by' => $request->attributes->get('user')->id,
        ]);

        // Touch project's last_activity_at
        $project->touch('last_activity_at');

        // Log activity
        ActivityLogService::logFromRequest($request, 'version_created', $document, $project->id, [
            'version' => $newVersion
        ]);

        return new DocumentResource($document->load('currentVersion'));
    }

    /**
     * Soft delete a document.
     */
    public function destroy(Request $request, string $projectSlug, string $docSlug)
    {
        $project = $this->findProject($request, $projectSlug, 'admin');

        $document = Document::where('project_id', $project->id)
            ->where('slug', $docSlug)
            ->firstOrFail();

        // Log activity before deletion
        ActivityLogService::logFromRequest($request, 'deleted', $document, $project->id);

        $document->delete();

        return response()->noContent();
    }

    /**
     * List all versions of a document.
     */
    public function versions(Request $request, string $projectSlug, string $docSlug)
    {
        $project = $this->findProject($request, $projectSlug);

        $document = Document::where('project_id', $project->id)
            ->where('slug', $docSlug)
            ->firstOrFail();

        $versions = DocumentVersion::where('document_id', $document->id)
            ->orderBy('version', 'desc')
            ->get();

        return response()->json(['data' => $versions]);
    }

    private function findProject(Request $request, string $slug, string $permission = 'viewer'): Project
    {
        return $this->findProjectWithSharing($request, $slug, $permission);
    }
}
