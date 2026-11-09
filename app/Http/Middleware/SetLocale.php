<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** Session-Schluessel der vom Gast oder Benutzer gewaehlten Sprache. */
    public const SESSION_KEY = 'locale';

    /** Maximale Anzahl ausgewerteter Eintraege des Accept-Language-Headers. */
    private const MAX_ACCEPT_LANGUAGE_ENTRIES = 20;

    /**
     * Setzt die Oberflaechensprache: Benutzereinstellung, Session, Accept-Language, Standardsprache.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $sessionLocale = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return $request->user()?->preferredLocale()
            ?? $this->supportedOrNull($sessionLocale)
            ?? $this->fromAcceptLanguage($request)
            ?? config('app.locale');
    }

    private function supportedOrNull(mixed $locale): ?string
    {
        return is_string($locale) && array_key_exists($locale, config('app.supported_locales'))
            ? $locale
            : null;
    }

    /**
     * Erste unterstuetzte Sprache nach Qualitaetswert; Regionen (de-DE) werden auf die Hauptsprache (de) reduziert.
     */
    private function fromAcceptLanguage(Request $request): ?string
    {
        $header = $request->headers->get('Accept-Language');

        if (! is_string($header) || $header === '') {
            return null;
        }

        $items = array_slice(AcceptHeader::fromString($header)->all(), 0, self::MAX_ACCEPT_LANGUAGE_ENTRIES);

        foreach ($items as $item) {
            if ($item->getQuality() <= 0) {
                continue;
            }

            $primary = Str::lower(Str::before(str_replace('_', '-', $item->getValue()), '-'));

            if ($this->supportedOrNull($primary) !== null) {
                return $primary;
            }
        }

        return null;
    }
}
