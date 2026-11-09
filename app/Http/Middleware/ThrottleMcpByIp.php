<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wendet den Limiter `mcp-ip` (pro IP) vor der Authentifizierung an.
 *
 * `throttle:mcp-ip` direkt an der Route liefe wegen der Middleware-Prioritaet (Authenticate vor ThrottleRequests)
 * erst nach `auth:api`. Diese Klasse steht nicht in der Prioritaetsliste und behaelt daher ihren Platz vor `auth:api`,
 * ohne die Reihenfolge anderer Routen zu veraendern.
 */
class ThrottleMcpByIp
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->throttle->handle($request, $next, 'mcp-ip');
    }
}
