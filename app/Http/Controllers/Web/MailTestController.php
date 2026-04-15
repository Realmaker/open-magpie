<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class MailTestController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (!$user->isTeamAdmin()) {
            abort(403);
        }

        // Current mail config (safe to show, no password)
        $mailConfig = [
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.smtp.host'),
            'port' => config('mail.mailers.smtp.port'),
            'scheme' => config('mail.mailers.smtp.scheme') ?: 'none',
            'username' => config('mail.mailers.smtp.username') ? '***' . substr((string) config('mail.mailers.smtp.username'), -4) : null,
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];

        return Inertia::render('Settings/MailTest', [
            'mailConfig' => $mailConfig,
            'userEmail' => $user->email,
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (!$user->isTeamAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'to' => 'required|email:rfc|max:255',
        ]);

        try {
            Mail::raw(
                "Dies ist eine Test-Mail vom Claude Code Brain.\n\n" .
                "Wenn du diese Mail lesen kannst, funktioniert der E-Mail-Versand.\n\n" .
                "Gesendet von: {$user->name} ({$user->email})\n" .
                "Zeitpunkt: " . now()->format('d.m.Y H:i:s') . "\n" .
                "Mailer: " . config('mail.default') . "\n" .
                "Host: " . config('mail.mailers.smtp.host') . ":" . config('mail.mailers.smtp.port') . "\n" .
                "Scheme: " . (config('mail.mailers.smtp.scheme') ?: 'none'),
                function ($message) use ($validated) {
                    $message->to($validated['to'])
                        ->subject('Brain Mail-Test');
                }
            );

            return back()->with('success', "Test-Mail an {$validated['to']} gesendet.");
        } catch (\Throwable $e) {
            return back()->with('error', "Mail-Versand fehlgeschlagen: {$e->getMessage()}");
        }
    }
}
