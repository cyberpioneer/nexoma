<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt Sicherheits-Header auf jede Antwort (global, auch fuer Routen ohne `web`-Gruppe wie /mcp und /oauth/token).
 *
 * Bereits gesetzte Header (z. B. X-Frame-Options/frame-ancestors von laravel/mcp) bleiben unveraendert.
 * Eine Content-Security-Policy ist bewusst nicht Teil dieser Middleware.
 */
class SecurityHeaders
{
    /** HSTS-Laufzeit in Sekunden (ein Jahr, ohne includeSubDomains/preload). */
    public const HSTS_MAX_AGE_SECONDS = 31536000;

    public const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=()';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
            'Permissions-Policy' => self::PERMISSIONS_POLICY,
        ];

        if (app()->isProduction() && $request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age='.self::HSTS_MAX_AGE_SECONDS;
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
