<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceOAuthAuthorizationRequest
{
    /**
     * Erzwingt PKCE mit S256 und die Consent-Seite fuer jede Autorisierungsanfrage.
     *
     * Die Middleware laeuft vor `auth` (siehe bootstrap/app.php), damit auch Gaeste vor dem Login abgewiesen werden.
     * league/oauth2-server akzeptiert sonst `plain` und nimmt es bei fehlender Methode sogar als Standard an.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $method = $request->query('code_challenge_method');
        $challenge = $request->query('code_challenge');

        if ($method !== 'S256' || ! is_string($challenge) || $challenge === '') {
            return response()->json([
                'error' => 'invalid_request',
                'error_description' => 'PKCE with code_challenge_method S256 is required.',
            ], 400);
        }

        // Passport liest `prompt` aus der Anfrage; ohne Erzwingung wuerde eine fruehere Zustimmung die Consent-Seite ueberspringen.
        $request->query->set('prompt', 'consent');

        return $next($request);
    }
}
