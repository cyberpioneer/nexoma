<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Domains (mit Schema), die OAuth-Clients bei der dynamischen Registrierung
    | als Redirect-URI verwenden duerfen. Der Vergleich ist praefixbasiert
    | (`https://claude.ai/...`). Quelle: MCP_REDIRECT_DOMAINS, kommagetrennt.
    | Der Platzhalter "*" (alles erlauben) wird bewusst herausgefiltert; ein
    | leerer Wert erlaubt keine Registrierung. Loopback (`http://localhost`,
    | `http://127.0.0.1`) fuer lokale CLI-Clients ist nur per Opt-in in der .env erlaubt.
    | Die Standard-Domains sind nicht mit echten Clients verifiziert.
    |
    */

    'redirect_domains' => array_values(array_filter(
        array_map('trim', explode(',', (string) env(
            'MCP_REDIRECT_DOMAINS',
            'https://chatgpt.com,https://chat.openai.com,https://claude.ai,https://claude.com',
        ))),
        fn (string $domain): bool => $domain !== '' && $domain !== '*',
    )),

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Eigene URI-Schemata (RFC 8252) umgehen die Domainpruefung oben. Muss leer
    | bleiben, solange kein verifizierter Client es zwingend braucht.
    |
    */

    'custom_schemes' => [],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | Issuer-Kennung (RFC 8414), fest aus der Umgebung statt aus dem Host-Header.
    | Rueckfall ist APP_URL (wie config/app.php).
    |
    */

    'authorization_server' => env('MCP_AUTHORIZATION_SERVER') ?: env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Registration Limits
    |--------------------------------------------------------------------------
    |
    | Obergrenzen fuer die oeffentliche dynamische Client-Registrierung
    | (POST /oauth/register). Wird eine Grenze erreicht, antwortet der Endpunkt
    | mit 503 `temporarily_unavailable`.
    |
    */

    'registration_limits' => [
        'per_day' => (int) env('MCP_REGISTRATIONS_PER_DAY', 1000),
        'total' => (int) env('MCP_REGISTRATIONS_TOTAL', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Retention
    |--------------------------------------------------------------------------
    |
    | Fristen fuer `oauth:prune-clients` (einzige Quelle). Clients ohne jede
    | Token-/Code-Zeile werden erst nach `unused_days` (seit Anlage und letzter
    | Aenderung) geloescht, widerrufene Clients ohne aktives Token nach `revoked_days`.
    |
    */

    'client_retention' => [
        'unused_days' => 30,
        'revoked_days' => 90,
    ],

];
