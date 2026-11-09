<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Laravel\Passport\Passport;

test('the api guard uses passport and the default guard stays web', function () {
    expect(config('auth.guards.api'))->toBe(['driver' => 'passport', 'provider' => 'users'])
        ->and(config('auth.defaults.guard'))->toBe('web');
});

test('the user model is an oauth authenticatable with api tokens', function () {
    expect(new User)->toBeInstanceOf(OAuthenticatable::class)
        ->and(class_uses_recursive(User::class))->toHaveKey(HasApiTokens::class);
});

test('the mcp and passport packages are production dependencies and boost is dev only', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require'])->toHaveKeys(['laravel/passport', 'laravel/mcp'])->not->toHaveKey('laravel/boost')
        ->and($composer['require-dev'])->toHaveKey('laravel/boost');
});

test('the mcp configuration permits no wildcard and no custom schemes', function () {
    expect(config('mcp.redirect_domains'))->toBeArray()->not->toBeEmpty()->not->toContain('*')
        ->and(config('mcp.custom_schemes'))->toBe([])
        ->and(config('mcp.authorization_server'))->toBe(config('app.url'));

    // Loopback ist nur per .env-Opt-in erlaubt, nicht im Standard.
    expect(config('mcp.redirect_domains'))->toBe(['https://chatgpt.com', 'https://chat.openai.com', 'https://claude.ai', 'https://claude.com']);

    foreach (config('mcp.redirect_domains') as $domain) {
        expect($domain)->toMatch('#^https?://[^/*]+$#');
    }
});

test('the redirect domains are read from the environment without the wildcard', function () {
    $_SERVER['MCP_REDIRECT_DOMAINS'] = ' https://a.example , * ,,http://localhost ';

    try {
        $config = require config_path('mcp.php');
    } finally {
        unset($_SERVER['MCP_REDIRECT_DOMAINS']);
    }

    expect($config['redirect_domains'])->toBe(['https://a.example', 'http://localhost']);
});

test('without an environment value the loopback hosts are not permitted', function () {
    unset($_SERVER['MCP_REDIRECT_DOMAINS'], $_ENV['MCP_REDIRECT_DOMAINS']);
    putenv('MCP_REDIRECT_DOMAINS');

    $config = require config_path('mcp.php');

    expect($config['redirect_domains'])->not->toContain('http://localhost')->not->toContain('http://127.0.0.1');
});

test('only the mcp use scope exists and it is the default', function () {
    expect(Passport::scopeIds())->toBe([Registrar::OAUTH_SCOPE])
        ->and(Registrar::OAUTH_SCOPE)->toBe('mcp:use')
        ->and(Passport::defaultScopes())->toBe(['mcp:use']);
});

test('access tokens last one hour and refresh tokens thirty days', function () {
    expect(Passport::tokensExpireIn()->h)->toBe(AppServiceProvider::OAUTH_ACCESS_TOKEN_LIFETIME_HOURS)
        ->and(AppServiceProvider::OAUTH_ACCESS_TOKEN_LIFETIME_HOURS)->toBe(1)
        ->and(Passport::tokensExpireIn()->d)->toBe(0)
        ->and(Passport::refreshTokensExpireIn()->d)->toBe(30)
        ->and(AppServiceProvider::OAUTH_REFRESH_TOKEN_LIFETIME_DAYS)->toBe(30);
});

test('the device, password and implicit grants are disabled', function () {
    expect(Passport::$deviceCodeGrantEnabled)->toBeFalse()
        ->and(Passport::$passwordGrantEnabled)->toBeFalse()
        ->and(Passport::$implicitGrantEnabled)->toBeFalse()
        ->and(Passport::$registersJsonApiRoutes)->toBeFalse();
});

test('exactly the intended oauth routes are registered with the intended middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => str_starts_with($route->uri(), 'oauth/'))
        ->mapWithKeys(fn (LaravelRoute $route) => [implode('|', $route->methods()).' '.$route->uri() => $route]);

    expect($routes->keys()->sort()->values()->all())->toBe([
        'DELETE oauth/authorize',
        'GET|HEAD oauth/authorize',
        'POST oauth/authorize',
        'POST oauth/register',
        'POST oauth/token',
    ]);

    foreach (['DELETE oauth/authorize', 'GET|HEAD oauth/authorize', 'POST oauth/authorize'] as $key) {
        expect($routes[$key]->gatherMiddleware())->toContain('web', 'auth', 'verified');
    }

    $token = $routes['POST oauth/token'];
    expect($token->gatherMiddleware())->toBe(['throttle:oauth-token'])
        ->and($token->getName())->toBe('passport.token')
        ->and($routes['GET|HEAD oauth/authorize']->getName())->toBe('passport.authorizations.authorize')
        ->and($routes['POST oauth/authorize']->getName())->toBe('passport.authorizations.approve')
        ->and($routes['DELETE oauth/authorize']->getName())->toBe('passport.authorizations.deny');
});

test('no passport management routes exist', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn (LaravelRoute $route) => $route->uri());

    expect($uris->filter(fn (string $uri) => str_contains($uri, 'device') || str_contains($uri, 'personal-access') || str_contains($uri, 'oauth/clients') || str_contains($uri, 'oauth/tokens') || str_contains($uri, 'oauth/scopes') || str_contains($uri, 'token/refresh')))
        ->toBeEmpty();
});

test('the token endpoint is rate limited per ip', function () {
    foreach (range(1, AppServiceProvider::OAUTH_TOKEN_ATTEMPTS_PER_MINUTE) as $attempt) {
        $this->postJson('/oauth/token', ['grant_type' => 'authorization_code'])->assertStatus(400);
    }

    $this->postJson('/oauth/token', ['grant_type' => 'authorization_code'])->assertTooManyRequests();
});

test('oauth machine endpoints answer errors as json even for html clients', function () {
    $this->get('/oauth/token', ['Accept' => 'text/html'])->assertStatus(405);
    $this->post('/oauth/token', [], ['Accept' => 'text/html'])->assertStatus(400)->assertJsonStructure(['error']);
});

test('the oauth tables exist', function () {
    foreach (['oauth_auth_codes', 'oauth_access_tokens', 'oauth_refresh_tokens', 'oauth_clients', 'oauth_device_codes'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});
