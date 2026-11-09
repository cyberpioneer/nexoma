<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

class LimitOAuthClientRegistrations
{
    /**
     * Begrenzt die oeffentliche Client-Registrierung: Tageslimit und Gesamtzahl nicht widerrufener Clients.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $limits = config('mcp.registration_limits');

        $registeredToday = Passport::client()->newQuery()
            ->where('created_at', '>=', Date::now()->subDay())
            ->count();

        $registeredTotal = Passport::client()->newQuery()
            ->where('revoked', false)
            ->count();

        if ($registeredToday >= $limits['per_day'] || $registeredTotal >= $limits['total']) {
            return response()->json(['error' => 'temporarily_unavailable'], 503);
        }

        return $next($request);
    }
}
