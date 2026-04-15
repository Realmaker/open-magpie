<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProjectShare;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectShareAcceptController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        $share = ProjectShare::findByToken($token);

        if (!$share) {
            return redirect()->route('dashboard')
                ->with('error', 'Ungültiger Einladungslink.');
        }

        if ($share->isExpired()) {
            return redirect()->route('dashboard')
                ->with('error', 'Diese Einladung ist abgelaufen.');
        }

        if ($share->isAccepted()) {
            return redirect()->route('projects.show', $share->project->slug)
                ->with('info', 'Diese Einladung wurde bereits angenommen.');
        }

        // Verify email matches (case-insensitive)
        if (strtolower($user->email) !== strtolower($share->shared_with_email)) {
            return redirect()->route('dashboard')
                ->with('error', 'Diese Einladung ist für eine andere E-Mail-Adresse bestimmt.');
        }

        // Accept the share
        $share->update([
            'shared_with_user_id' => $user->id,
            'accepted_at' => now(),
        ]);

        ActivityLogService::log(
            'share_accepted',
            $share->project,
            $share->project->team_id,
            $user->id,
            $share->project_id,
            ['permission' => $share->permission]
        );

        return redirect()->route('projects.show', $share->project->slug)
            ->with('success', "Du hast jetzt Zugriff auf das Projekt '{$share->project->name}'.");
    }
}
