<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProjectShareRequest;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectShareResource;
use App\Mail\ProjectShareInvitation;
use App\Models\Project;
use App\Models\ProjectShare;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ProjectShareController extends Controller
{
    /**
     * List all shares for a project.
     * Only project owner or team members with admin access can see shares.
     */
    public function index(Request $request, string $slug): JsonResponse
    {
        $project = $this->findProjectWithShareAccess($request, $slug, 'admin');

        $shares = $project->shares()
            ->with(['sharedBy:id,name,email', 'sharedWithUser:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => ProjectShareResource::collection($shares),
        ]);
    }

    /**
     * Share a project with a user by email.
     */
    public function store(StoreProjectShareRequest $request, string $slug): JsonResponse
    {
        $project = $this->findProjectWithShareAccess($request, $slug, 'admin');
        $data = $request->validated();
        $sharer = $request->attributes->get('user');
        $email = strtolower(trim($data['email']));

        // Only team owners/admins can share
        if (!$sharer->isTeamAdmin()) {
            return response()->json([
                'error' => [
                    'code' => 'insufficient_team_role',
                    'message' => 'Only team administrators can share projects.',
                ],
            ], 403);
        }

        // Cannot share with yourself
        if ($email === strtolower($sharer->email)) {
            return response()->json([
                'error' => [
                    'code' => 'cannot_share_with_self',
                    'message' => 'You cannot share a project with yourself.',
                ],
            ], 422);
        }

        // Check if already shared with this email
        $existing = $project->shares()
            ->where('shared_with_email', $email)
            ->first();

        if ($existing) {
            return response()->json([
                'error' => [
                    'code' => 'already_shared',
                    'message' => 'This project is already shared with this email address.',
                ],
            ], 422);
        }

        // Generate secure invite token
        $tokenData = ProjectShare::generateInviteToken();

        // Check if the target user already exists
        $targetUser = User::where('email', $email)->first();
        $isAutoAccepted = $targetUser !== null;

        $share = ProjectShare::create([
            'project_id' => $project->id,
            'shared_by' => $sharer->id,
            'shared_with_user_id' => $targetUser?->id,
            'shared_with_email' => $email,
            'permission' => $data['permission'],
            'invite_token' => $tokenData['hashed'],
            'accepted_at' => $isAutoAccepted ? now() : null,
            'expires_at' => $isAutoAccepted ? null : now()->addDays(7),
        ]);

        // Only send email for non-registered users
        if (!$isAutoAccepted) {
            $acceptUrl = url("/shares/accept/{$tokenData['plain']}");

            Mail::to($email)->queue(new ProjectShareInvitation(
                project: $project,
                sharedBy: $sharer,
                permission: $data['permission'],
                acceptUrl: $acceptUrl,
                recipientEmail: $email,
            ));
        }

        // Log activity
        ActivityLogService::logFromRequest($request, 'project_shared', $project, $project->id, [
            'shared_with' => $email,
            'permission' => $data['permission'],
            'auto_accepted' => $isAutoAccepted,
        ]);

        $share->load(['sharedBy:id,name,email', 'sharedWithUser:id,name,email']);

        $message = $isAutoAccepted
            ? "Project shared with {$email} (auto-accepted)."
            : "Invitation sent to {$email}.";

        return response()->json([
            'data' => new ProjectShareResource($share),
            'message' => $message,
        ], 201);
    }

    /**
     * Update share permission.
     */
    public function update(Request $request, string $slug, int $id): JsonResponse
    {
        $project = $this->findProjectWithShareAccess($request, $slug, 'admin');

        $share = $project->shares()->findOrFail($id);

        $data = $request->validate([
            'permission' => ['required', 'string', 'in:viewer,editor,admin'],
        ]);

        $share->update(['permission' => $data['permission']]);

        ActivityLogService::logFromRequest($request, 'share_permission_changed', $project, $project->id, [
            'share_id' => $share->id,
            'email' => $share->shared_with_email,
            'new_permission' => $data['permission'],
        ]);

        $share->load(['sharedBy:id,name,email', 'sharedWithUser:id,name,email']);

        return response()->json([
            'data' => new ProjectShareResource($share),
            'message' => 'Permission updated.',
        ]);
    }

    /**
     * Revoke a share.
     */
    public function destroy(Request $request, string $slug, int $id): JsonResponse
    {
        $project = $this->findProjectWithShareAccess($request, $slug, 'admin');

        $share = $project->shares()->findOrFail($id);

        ActivityLogService::logFromRequest($request, 'share_revoked', $project, $project->id, [
            'share_id' => $share->id,
            'email' => $share->shared_with_email,
        ]);

        $share->delete();

        return response()->json(null, 204);
    }

    /**
     * List all projects shared with the authenticated user.
     */
    public function sharedWithMe(Request $request): JsonResponse
    {
        $user = $request->attributes->get('user');

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

        $projects = $shares->map(function ($share) {
            $projectData = (new ProjectResource($share->project))->toArray(request());
            $projectData['shared_permission'] = $share->permission;
            $projectData['shared_by'] = $share->sharedBy?->name;
            return $projectData;
        });

        return response()->json([
            'data' => $projects,
        ]);
    }

    /**
     * Find project - checks team ownership first, then share admin access.
     */
    private function findProjectWithShareAccess(Request $request, string $slug, string $requiredPermission): Project
    {
        $teamId = $request->attributes->get('team_id');
        $user = $request->attributes->get('user');

        // First: try team-owned project
        $project = Project::where('team_id', $teamId)
            ->where('slug', $slug)
            ->first();

        if ($project) {
            return $project;
        }

        // Second: try shared project with sufficient permission
        $project = Project::where('slug', $slug)
            ->whereHas('shares', function ($q) use ($user) {
                $q->where('shared_with_user_id', $user->id)
                  ->whereNotNull('accepted_at')
                  ->where(function ($q2) {
                      $q2->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                  });
            })
            ->first();

        if ($project && $project->isSharedWith($user, $requiredPermission)) {
            return $project;
        }

        abort(404, "Project '{$slug}' not found.");
    }
}
