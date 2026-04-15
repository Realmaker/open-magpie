<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function apiTokens(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $tokens = ApiToken::where('user_id', $user->id)
            ->where('team_id', $team?->id ?? 0)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_four' => substr($token->token, -4), // last 4 of hash as identifier
                    'last_used_at' => $token->last_used_at,
                    'expires_at' => $token->expires_at,
                    'created_at' => $token->created_at,
                ];
            });

        return Inertia::render('Settings/ApiTokens', [
            'tokens' => $tokens,
        ]);
    }

    public function createApiToken(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $team = $user->currentTeam();

        if (!$team) {
            return back()->withErrors(['team' => 'No team found. Please create a team first.']);
        }

        $plainToken = Str::random(40);

        $apiToken = ApiToken::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'name' => $request->name,
            'token' => hash('sha256', $plainToken),
        ]);

        return back()->with('newToken', $plainToken);
    }

    public function deleteApiToken(Request $request, int $id)
    {
        $user = $request->user();

        $token = ApiToken::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $token->delete();

        return back();
    }
}
