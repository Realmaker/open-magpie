<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\FindsProjectWithSharing;
use App\Http\Resources\ProjectSnapshotResource;
use App\Models\Project;
use App\Models\ProjectSnapshot;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SnapshotController extends Controller
{
    use FindsProjectWithSharing;

    /**
     * List all snapshots for a project.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $project = $this->findProject($request, $slug);

        $snapshots = $project->snapshots()
            ->with('uploader:id,name')
            ->orderBy('version', 'desc')
            ->get();

        return response()->json([
            'data' => ProjectSnapshotResource::collection($snapshots),
            'install_notes' => $project->install_notes,
        ]);
    }

    /**
     * Get latest snapshot with full file tree.
     */
    public function latest(Request $request, string $slug): JsonResponse
    {
        $project = $this->findProject($request, $slug);

        $snapshot = $project->snapshots()
            ->with('uploader:id,name')
            ->orderBy('version', 'desc')
            ->first();

        if (!$snapshot) {
            return response()->json([
                'data' => null,
                'install_notes' => $project->install_notes,
                'message' => 'No snapshots yet.',
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $snapshot->id,
                'version' => $snapshot->version,
                'file_size' => $snapshot->file_size,
                'human_size' => $snapshot->human_size,
                'file_count' => $snapshot->file_count,
                'file_tree' => $snapshot->file_tree,
                'change_note' => $snapshot->change_note,
                'source' => $snapshot->source,
                'created_at' => $snapshot->created_at,
                'uploaded_by' => $snapshot->uploader?->name,
            ],
            'install_notes' => $project->install_notes,
        ]);
    }

    /**
     * Upload a new snapshot (ZIP file).
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $project = $this->findProject($request, $slug, 'editor');

        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:51200'], // 50MB max
            'change_note' => ['nullable', 'string', 'max:500'],
            'install_notes' => ['nullable', 'string', 'max:50000'],
            'source' => ['nullable', 'string', 'in:manual,claude-code,api'],
            'exclude_patterns' => ['nullable', 'array'],
        ]);

        $file = $request->file('file');
        $user = $request->attributes->get('user');

        // Determine next version
        $lastVersion = $project->snapshots()->max('version') ?? 0;
        $newVersion = $lastVersion + 1;

        // Store ZIP
        $storagePath = ProjectSnapshot::storagePath($project->id);
        $fileName = "v{$newVersion}.zip";
        $path = $file->storeAs($storagePath, $fileName, 'local');

        // Build file tree from ZIP
        $fullPath = Storage::disk('local')->path($path);
        $excludePatterns = $request->input('exclude_patterns', []);
        [$fileTree, $fileCount] = ProjectSnapshot::buildFileTreeFromZip($fullPath, $excludePatterns);

        $snapshot = ProjectSnapshot::create([
            'project_id' => $project->id,
            'version' => $newVersion,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_count' => $fileCount,
            'file_tree' => $fileTree,
            'exclude_patterns' => $excludePatterns ?: null,
            'change_note' => $request->input('change_note'),
            'source' => $request->input('source', 'api'),
            'uploaded_by' => $user->id,
        ]);

        // Update install notes if provided
        if ($request->has('install_notes')) {
            $project->update(['install_notes' => $request->input('install_notes')]);
        }

        $project->touchActivity();

        ActivityLogService::logFromRequest($request, 'snapshot_uploaded', $snapshot, $project->id, [
            'version' => $newVersion,
            'file_count' => $fileCount,
            'file_size' => $file->getSize(),
        ]);

        return response()->json([
            'data' => [
                'id' => $snapshot->id,
                'version' => $snapshot->version,
                'file_size' => $snapshot->file_size,
                'human_size' => $snapshot->human_size,
                'file_count' => $snapshot->file_count,
                'file_tree' => $snapshot->file_tree,
                'change_note' => $snapshot->change_note,
            ],
            'message' => "Snapshot v{$newVersion} uploaded successfully ({$fileCount} files).",
        ], 201);
    }

    /**
     * Download a snapshot ZIP.
     */
    public function download(Request $request, string $slug, int $version)
    {
        $project = $this->findProject($request, $slug);

        $snapshot = $project->snapshots()
            ->where('version', $version)
            ->firstOrFail();

        $fullPath = Storage::disk('local')->path($snapshot->file_path);

        if (!file_exists($fullPath)) {
            return response()->json([
                'error' => ['code' => 'file_not_found', 'message' => 'Snapshot file not found on disk.'],
            ], 404);
        }

        $filename = "{$project->slug}-v{$version}.zip";

        return response()->download($fullPath, $filename);
    }

    /**
     * Preview a file from within a snapshot ZIP.
     */
    public function previewFile(Request $request, string $slug, int $version): JsonResponse
    {
        $project = $this->findProject($request, $slug);

        $snapshot = $project->snapshots()
            ->where('version', $version)
            ->firstOrFail();

        $filePath = $request->input('path');
        if (!$filePath) {
            return response()->json([
                'error' => ['code' => 'path_required', 'message' => 'File path is required.'],
            ], 400);
        }

        $content = $snapshot->readFileFromZip($filePath);

        if ($content === null) {
            return response()->json([
                'error' => ['code' => 'file_not_found', 'message' => "File '{$filePath}' not found in snapshot."],
            ], 404);
        }

        // Limit preview size (500KB)
        $isTruncated = false;
        if (strlen($content) > 512000) {
            $content = substr($content, 0, 512000);
            $isTruncated = true;
        }

        // Check if binary
        $isBinary = !mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0");

        return response()->json([
            'data' => [
                'path' => $filePath,
                'content' => $isBinary ? null : $content,
                'is_binary' => $isBinary,
                'is_truncated' => $isTruncated,
                'size' => strlen($content),
            ],
        ]);
    }

    /**
     * Update install notes for a project.
     */
    public function updateInstallNotes(Request $request, string $slug): JsonResponse
    {
        $project = $this->findProject($request, $slug, 'editor');

        $data = $request->validate([
            'install_notes' => ['required', 'string', 'max:50000'],
        ]);

        $project->update(['install_notes' => $data['install_notes']]);

        return response()->json([
            'data' => ['install_notes' => $project->install_notes],
            'message' => 'Install notes updated.',
        ]);
    }

    private function findProject(Request $request, string $slug, string $permission = 'viewer'): Project
    {
        return $this->findProjectWithSharing($request, $slug, $permission);
    }
}
