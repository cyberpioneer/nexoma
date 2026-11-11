<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Paywall fuer die kostenpflichtigen Funktionen ab Release 1.2.0 (Alias `subscribed`).
 *
 * Gehoert in die Gruppe `auth` + `verified` und laeuft danach. Die Entscheidung faellt ausschliesslich ueber
 * {@see User::hasActiveSubscription()} (fail-closed). Browser werden zur Upgrade-Seite geleitet, JSON-Clients
 * erhalten 402. Funktionen aus Release 1.1.0 duerfen diese Middleware nie tragen.
 */
class EnsureUserIsSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasActiveSubscription()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('ui.billing.subscription_required')], 402);
        }

        return redirect()->route('billing.upgrade');
    }
}
