<?php

namespace App\Services\OAuth;

use Illuminate\Support\Arr;

/**
 * Aufbereitung von Redirect-URIs (aus der dynamischen Client-Registrierung, unvertrauenswuerdig) zur Anzeige.
 */
final class RedirectUriDisplay
{
    /**
     * Anzeigeform einer Redirect-URI: Schema, Host und Port (nur wenn nicht Standard); Userinfo, Pfad und Query
     * werden bewusst weggelassen. Bei Schemata ohne Host (z. B. `app://`) nur das Schema.
     *
     * @return array{host: string, punycode: bool}
     */
    public static function describe(mixed $uri): array
    {
        $empty = ['host' => '', 'punycode' => false];

        if (! is_string($uri)) {
            return $empty;
        }

        $parts = parse_url($uri);

        if (! is_array($parts) || ! isset($parts['scheme'])) {
            return $empty;
        }

        $scheme = strtolower($parts['scheme']);

        if (! isset($parts['host']) || $parts['host'] === '') {
            return ['host' => $scheme.'://', 'punycode' => false];
        }

        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? null;
        $isDefaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        $display = $scheme.'://'.$host.($port !== null && ! $isDefaultPort ? ':'.$port : '');

        return ['host' => $display, 'punycode' => self::isPunycode($host)];
    }

    /**
     * Anzeigeformen aller Redirect-URIs eines Clients (ohne Duplikate).
     *
     * @return list<array{host: string, punycode: bool}>
     */
    public static function describeAll(mixed $uris): array
    {
        $entries = [];

        foreach (Arr::wrap($uris) as $uri) {
            $entry = self::describe($uri);

            if ($entry['host'] !== '' && ! array_key_exists($entry['host'], $entries)) {
                $entries[$entry['host']] = $entry;
            }
        }

        return array_values($entries);
    }

    private static function isPunycode(string $host): bool
    {
        if (preg_match('/[^\x00-\x7F]/', $host) === 1) {
            return true;
        }

        foreach (explode('.', $host) as $label) {
            if (str_starts_with($label, 'xn--')) {
                return true;
            }
        }

        return false;
    }
}
