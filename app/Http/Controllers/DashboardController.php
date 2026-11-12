<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uebersicht: Kennzahlen und, fuer Abonnenten, das neueste fertige Audio-Briefing. Es werden nur eigene Daten und
 * nur Whitelist-Felder ausgegeben (kein Sprechtext, kein Dateipfad, kein Fehlercode).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $audio = null;

        // Audio ist Premium (der Stream liegt hinter der Paywall); Benutzer ohne Abo sehen den Bereich nicht.
        if ($user->hasActiveSubscription()) {
            $latest = $user->audioBriefings()->ready()->latest()->latest('id')->first();

            $audio = [
                'count' => $user->audioBriefings()->ready()->count(),
                'latest' => $latest === null ? null : [
                    'id' => $latest->id,
                    'durationMs' => $latest->duration_ms ?? 0,
                    'createdAt' => $latest->created_at?->format('Y-m-d'),
                ],
            ];
        }

        return Inertia::render('Dashboard', [
            'savedJobsCount' => $user->savedJobs()->count(),
            'audio' => $audio,
        ]);
    }
}
