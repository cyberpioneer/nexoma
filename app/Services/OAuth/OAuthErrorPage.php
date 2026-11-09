<?php

namespace App\Services\OAuth;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gestaltete Fehlerseite fuer die Browser-Autorisierung (`GET /oauth/authorize`).
 * Es erfolgt nie eine Weiterleitung an eine uebergebene URI; Fehlertexte kommen nur aus den Sprachdateien.
 */
final class OAuthErrorPage
{
    /**
     * Nur fuer Browser (Accept enthaelt text/html); Maschinenclients behalten die JSON-Antwort.
     */
    public static function wanted(Request $request): bool
    {
        return $request->isMethod('GET')
            && $request->is('oauth/authorize')
            && str_contains(strtolower((string) $request->header('Accept')), 'text/html');
    }

    public static function response(Request $request, int $status): Response
    {
        return Inertia::render('oauth/Error', ['status' => $status])
            ->toResponse($request)
            ->setStatusCode($status);
    }
}
