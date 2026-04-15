<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $team = $user->currentTeam();

        $tokenCount = $team
            ? ApiToken::where('team_id', $team->id)->where('user_id', $user->id)->count()
            : 0;

        $apiUrl = rtrim(config('app.url'), '/') . '/api/v1';

        return Inertia::render('Onboarding/Index', [
            'apiUrl' => $apiUrl,
            'hasTeam' => $team !== null,
            'hasToken' => $tokenCount > 0,
            'userName' => $user->name,
            'isTeamAdmin' => $user->isTeamAdmin(),
        ]);
    }
}
