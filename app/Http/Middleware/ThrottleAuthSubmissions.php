<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAuthSubmissions
{
    /**
     * Routen, die ueber den Limiter `auth` gedrosselt werden (Login hat den Limiter in der Fortify-Konfiguration).
     *
     * @var list<string>
     */
    private const THROTTLED_ROUTES = ['register.store', 'password.email', 'password.update', 'password.confirm.store'];

    public function __construct(private readonly ThrottleRequests $throttle) {}

    /**
     * Drosselt nur POST-Anfragen der Fortify-Routen ohne eigenen Throttle.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs(...self::THROTTLED_ROUTES)) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'auth');
    }
}
