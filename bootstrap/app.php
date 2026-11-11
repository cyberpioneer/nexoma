<?php

use App\Http\Middleware\EnforceOAuthAuthorizationRequest;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxies;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies as IlluminateTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Host-Header-Schutz (Mail-Links): nur Hosts aus TRUSTED_HOSTS bzw. der Host aus APP_URL; die Middleware ist
        // lokal und in Tests inaktiv. Die Konfiguration wird erst zur Anfragezeit gelesen (config:cache).
        $middleware->trustHosts(
            at: function (): array {
                $configured = config('app.trusted_hosts');
                $hosts = is_array($configured) ? $configured : [];

                if ($hosts === []) {
                    $hosts = [parse_url((string) config('app.url'), PHP_URL_HOST)];
                }

                $patterns = array_map(
                    fn (mixed $host): string => '^'.preg_quote((string) $host, '/').'$',
                    array_values(array_filter($hosts, fn (mixed $host): bool => is_string($host) && $host !== '')),
                );

                // Fail-closed: ohne ermittelbaren Host (APP_URL fehlerhaft) passt kein Host (Muster, das nie trifft).
                return $patterns === [] ? ['(?!)'] : $patterns;
            },
            subdomains: false,
        );

        // Proxy-Vertrauen nur ueber TRUSTED_PROXIES (Standard: keines), siehe App\Http\Middleware\TrustProxies.
        $middleware->replace(IlluminateTrustProxies::class, TrustProxies::class);

        // Aeusserste Schicht, damit auch Antworten frueher Middleware (Wartungsmodus, Host-Abweisung) die Header tragen.
        $middleware->prepend(SecurityHeaders::class);

        // Sessions werden nach Passwortaenderung/-reset ungueltig (Abgleich des Passwort-Hashs je Anfrage).
        $middleware->authenticateSessions();

        // Die OAuth-Pruefung (PKCE S256) muss vor `auth` laufen, damit auch Gaeste vor dem Login abgewiesen werden.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnforceOAuthAuthorizationRequest::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Fehlgeschlagene Formulare der Gastseiten (Anmeldung, Registrierung, Passwort): immer zurueck zur eigenen Seite.
        // Ohne Vorgabe leitet Laravel auf die zuletzt gemerkte Seite (bei Inertia-Navigation oft "/") um, weil
        // Referrer-Policy "no-referrer" den Referer entfernt; die Fehler gingen dann ungesehen verloren.
        $exceptions->render(function (ValidationException $exception, Request $request): null {
            if ($request->expectsJson()) {
                return null;
            }

            $target = match ($request->route()?->getName()) {
                'login.store' => route('login'),
                'register.store' => route('register'),
                'password.email' => route('password.request'),
                'password.update' => route('password.reset', [
                    'token' => is_string($request->input('token')) ? $request->input('token') : '',
                    'email' => is_string($request->input('email')) ? $request->input('email') : '',
                ]),
                default => null,
            };

            if ($target !== null) {
                $exception->redirectTo($target);
            }

            return null;
        });

        // Maschinenclients (MCP/OAuth) erhalten JSON statt Weiterleitungen/HTML. `oauth/authorize` ist
        // bewusst ausgenommen: das ist die Browserseite mit Login-Weiterleitung.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp', 'mcp/*', '.well-known/*', 'oauth/token', 'oauth/register', 'stripe/webhook')
                || $request->expectsJson(),
        );
    })->create();
