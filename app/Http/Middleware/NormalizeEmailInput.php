<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class NormalizeEmailInput
{
    /**
     * Normalisiert ein vorhandenes Feld `email` bei POST (trim, Kleinschreibung).
     *
     * Nicht-String-Werte (z. B. Arrays) werden auf null gesetzt und scheitern an der Validierung;
     * sonst loesen sie in den Fortify-Controllern einen Serverfehler aus.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->has('email')) {
            $email = $request->input('email');

            $request->merge(['email' => is_string($email) ? Str::lower(trim($email)) : null]);
        }

        return $next($request);
    }
}
