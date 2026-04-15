<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team || !$user->isTeamAdmin()) {
            abort(403, 'Nur Team-Administratoren haben Zugriff auf die Benutzerverwaltung.');
        }

        $members = $team->users()
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->role,
                    'joined_at' => $member->pivot->created_at,
                    'email_verified' => $member->email_verified_at !== null,
                    'last_login' => $member->updated_at,
                ];
            });

        // All registered users NOT in this team (for invite suggestions)
        $availableUsers = User::whereDoesntHave('teams', function ($q) use ($team) {
                $q->where('teams.id', $team->id);
            })
            ->where('email_verified_at', '!=', null)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'created_at']);

        $stats = [
            'total' => $members->count(),
            'owners' => $members->where('role', 'owner')->count(),
            'admins' => $members->where('role', 'admin')->count(),
            'members' => $members->where('role', 'member')->count(),
            'viewers' => $members->where('role', 'viewer')->count(),
        ];

        return Inertia::render('Settings/Users', [
            'members' => $members,
            'availableUsers' => $availableUsers,
            'stats' => $stats,
            'teamName' => $team->name,
            'currentUserId' => $user->id,
        ]);
    }

    public function updateRole(Request $request, int $userId): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team || !$user->isTeamAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:admin,member,viewer',
        ]);

        // Cannot change own role
        if ($userId === $user->id) {
            return back()->withErrors(['role' => 'Du kannst deine eigene Rolle nicht aendern.']);
        }

        // Cannot change owner role
        $target = $team->users()->where('users.id', $userId)->first();
        if (!$target) {
            abort(404);
        }

        if ($target->pivot->role === 'owner') {
            return back()->withErrors(['role' => 'Die Owner-Rolle kann nicht geaendert werden.']);
        }

        $team->users()->updateExistingPivot($userId, [
            'role' => $validated['role'],
        ]);

        ActivityLogService::log('role_changed', $target, $team->id, $user->id, null, [
            'new_role' => $validated['role'],
        ]);

        return back()->with('success', 'Rolle aktualisiert.');
    }

    public function addToTeam(Request $request): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team || !$user->isTeamAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|string|in:admin,member,viewer',
        ]);

        // Check user not already in team
        if ($team->users()->where('users.id', $validated['user_id'])->exists()) {
            return back()->withErrors(['user_id' => 'Dieser User ist bereits im Team.']);
        }

        $team->users()->attach($validated['user_id'], [
            'role' => $validated['role'],
        ]);

        $target = User::find($validated['user_id']);

        ActivityLogService::log('added_to_team', $target, $team->id, $user->id, null, [
            'role' => $validated['role'],
        ]);

        return back()->with('success', "{$target->name} zum Team hinzugefuegt.");
    }

    public function removeFromTeam(Request $request, int $userId): RedirectResponse
    {
        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team || !$user->isTeamAdmin()) {
            abort(403);
        }

        // Cannot remove yourself
        if ($userId === $user->id) {
            return back()->withErrors(['user' => 'Du kannst dich nicht selbst entfernen.']);
        }

        $target = $team->users()->where('users.id', $userId)->first();
        if (!$target) {
            abort(404);
        }

        // Cannot remove owner
        if ($target->pivot->role === 'owner') {
            return back()->withErrors(['user' => 'Der Owner kann nicht entfernt werden.']);
        }

        $team->users()->detach($userId);

        ActivityLogService::log('removed_from_team', $target, $team->id, $user->id, null, [
            'removed_user' => $target->email,
        ]);

        return back()->with('success', "{$target->name} aus dem Team entfernt.");
    }
}
