<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Vertraut Proxys nur, wenn `TRUSTED_PROXIES` (config `app.trusted_proxies`) sie ausdruecklich nennt.
 *
 * Die Konfiguration wird erst zur Anfragezeit gelesen, damit `config:cache` funktioniert. Leer = kein Proxy-Vertrauen.
 * Fail-closed: Ohne ausdrueckliche Liste wird nie vertraut; die Framework-Voreinstellung fuer Laravel Cloud sowie
 * `*.on-forge.com`/`*.on-vapor.com` (vertraut dort allen Proxys) ist bewusst abgeschaltet.
 * Nur der Wert `*` vertraut allen Proxys; `**` und `REMOTE_ADDR` werden bewusst ignoriert.
 */
class TrustProxies extends Middleware
{
    /**
     * @return list<string>|string
     */
    protected function proxies(): array|string
    {
        $configured = config('app.trusted_proxies');

        if (! is_string($configured)) {
            return [];
        }

        $entries = array_values(array_filter(
            array_map(trim(...), explode(',', $configured)),
            fn (string $entry): bool => $entry !== '' && ! in_array($entry, ['**', 'REMOTE_ADDR'], true),
        ));

        return in_array('*', $entries, true) ? '*' : $entries;
    }

    /**
     * Ohne ausdrueckliche Konfiguration keine Proxys vertrauen (kein Cloud-/Forge-/Vapor-Fallback des Frameworks).
     */
    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        if ($this->proxies() === []) {
            $request->setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);

            return;
        }

        parent::setTrustedProxyIpAddresses($request);
    }
}
