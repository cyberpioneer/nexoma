<?php

use App\Http\Middleware\LimitOAuthClientRegistrations;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Passport\Http\Controllers\AccessTokenController;

/*
| Diese Datei wird von laravel/mcp ohne Middleware-Gruppe geladen: keine Session, kein CSRF,
| kein SetLocale. Alle Routen hier sind bewusst oeffentlich bzw. tokenbasiert und gedrosselt.
*/

// OAuth2-Token-Endpunkt (Authorization Code + PKCE, Refresh); Clients senden kein CSRF-Token.
Route::post('/oauth/token', [AccessTokenController::class, 'issueToken'])
    ->middleware('throttle:oauth-token')
    ->name('passport.token');

// Discovery-Metadaten (RFC 8414 / RFC 9728); die Registrierung wird unten mit Schutz angelegt.
Route::middleware('throttle:oauth-metadata')->group(function () {
    Mcp::oauthRoutes(registration: false);
});

// Dynamische Client-Registrierung (RFC 7591): oeffentlich, daher pro IP/global gedrosselt und mit Obergrenze.
Route::post('/oauth/register', OAuthRegisterController::class)
    ->middleware(['throttle:oauth-register', LimitOAuthClientRegistrations::class])
    ->name('mcp.oauth.register');
