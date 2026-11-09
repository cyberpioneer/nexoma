<?php

use App\Http\Middleware\EnsureMcpAccess;
use App\Http\Middleware\ThrottleMcpByIp;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard requires login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->getJson(route('dashboard'))->assertUnauthorized();
});

test('unverified users are sent to the verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs($user)->getJson(route('dashboard'))->assertForbidden();
});

test('verified users can open the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
});

test('guest and auth middleware are assigned as intended to the fortify routes', function (string $name, string $expected) {
    $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();

    expect($middleware)->toContain($expected);
})->with([
    ['login', 'guest:web'],
    ['login.store', 'guest:web'],
    ['register', 'guest:web'],
    ['register.store', 'guest:web'],
    ['password.request', 'guest:web'],
    ['password.email', 'guest:web'],
    ['password.reset', 'guest:web'],
    ['password.update', 'guest:web'],
    ['logout', 'auth:web'],
    ['verification.notice', 'auth:web'],
    ['verification.send', 'auth:web'],
    ['verification.verify', 'auth:web'],
]);

test('disabled fortify features expose no routes', function () {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn (LaravelRoute $route) => $route->uri());

    expect($uris->filter(fn (string $uri) => Str::startsWith($uri, ['user/two-factor', 'user/profile-information', 'user/password', 'user/passkeys', 'passkeys', 'two-factor-challenge'])))
        ->toBeEmpty();
});

test('the unused password confirmation page does not cause a server error', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('password.confirm'))
        ->assertStatus(404);
});

/**
 * Liefert alle registrierten Routen, die nicht ausdruecklich (exakter Name bzw. exakte URI) freigegeben sind.
 *
 * @return Collection<int, LaravelRoute>
 */
function routesRequiringAuthAndVerified(): Collection
{
    $publicNames = [
        'home', 'locale.update', 'login', 'login.store', 'register', 'register.store',
        'password.request', 'password.email', 'password.reset', 'password.update',
        'logout', 'verification.notice', 'verification.send', 'verification.verify',
        'password.confirm', 'password.confirm.store', 'password.confirmation',
        'storage.local', 'storage.local.upload', 'boost.browser-logs',
        // OAuth2/MCP-Discovery, Registrierung und Token-Endpunkt: bewusst oeffentlich (exakte Liste siehe unten).
        'mcp.oauth.authorization-server', 'mcp.oauth.authorization-server.nested',
        'mcp.oauth.protected-resource', 'mcp.oauth.protected-resource.nested',
        'mcp.oauth.register', 'passport.token',
    ];
    // `mcp`: POST ist per auth:api + EnsureMcpAccess geschuetzt, GET/DELETE antworten 405 (siehe eigener Test unten).
    $publicUris = ['up', '_inertia/devtools/entries', '_inertia/devtools/entries/{id}', 'mcp'];

    return collect(Route::getRoutes()->getRoutes())->reject(
        fn (LaravelRoute $route): bool => in_array((string) $route->getName(), $publicNames, true)
            || in_array($route->uri(), $publicUris, true),
    )->values();
}

/**
 * Liefert die Routen aus der Sammlung, denen `auth` oder `verified` fehlt, als "METHODEN uri".
 *
 * @param  Collection<int, LaravelRoute>  $routes
 * @return list<string>
 */
function routesMissingAuthOrVerified(Collection $routes): array
{
    return $routes
        ->filter(function (LaravelRoute $route): bool {
            $middleware = $route->gatherMiddleware();

            return ! in_array('auth', $middleware, true) || ! in_array('verified', $middleware, true);
        })
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();
}

test('every route outside the allowlist requires auth and verified', function () {
    expect(routesMissingAuthOrVerified(routesRequiringAuthAndVerified()))->toBeEmpty();
});

test('the inventory check detects a newly registered unprotected route', function () {
    Route::middleware('web')->get('/_test/unprotected', fn () => 'x')->name('test.unprotected');
    Route::middleware(['web', 'auth', 'verified'])->get('/_test/protected', fn () => 'x')->name('test.protected');

    expect(routesMissingAuthOrVerified(routesRequiringAuthAndVerified()))
        ->toBe(['GET|HEAD _test/unprotected']);
});

test('the oauth and mcp route families match the exact allowlist', function () {
    $actual = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'oauth/')
            || str_starts_with($route->uri(), '.well-known/')
            || $route->uri() === 'mcp' || str_starts_with($route->uri(), 'mcp/'))
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->sort()->values()->all();

    $expected = [
        'DELETE mcp',
        'DELETE oauth/authorize',
        'GET|HEAD .well-known/oauth-authorization-server',
        'GET|HEAD .well-known/oauth-authorization-server/{path}',
        'GET|HEAD .well-known/oauth-protected-resource',
        'GET|HEAD .well-known/oauth-protected-resource/{path}',
        'GET|HEAD mcp',
        'GET|HEAD oauth/authorize',
        'POST mcp',
        'POST oauth/authorize',
        'POST oauth/register',
        'POST oauth/token',
    ];
    sort($expected);

    expect($actual)->toBe($expected);
});

test('the consent routes require auth and verified while discovery, registration and token routes are public by design', function () {
    foreach (['passport.authorizations.authorize', 'passport.authorizations.approve', 'passport.authorizations.deny'] as $name) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('auth', 'verified');
    }

    foreach (['passport.token', 'mcp.oauth.register'] as $name) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->not->toContain('web')
            ->and(collect(Route::getRoutes()->getByName($name)->gatherMiddleware())->first(fn ($m) => str_starts_with((string) $m, 'throttle:')))->not->toBeNull();
    }
});

test('the mcp route is protected by token, scope and verification, throttled and free of web middleware', function () {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (LaravelRoute $route): bool => $route->uri() === 'mcp');

    expect($routes->map(fn (LaravelRoute $route): string => implode('|', $route->methods()))->sort()->values()->all())
        ->toBe(['DELETE', 'GET|HEAD', 'POST']);

    $post = $routes->first(fn (LaravelRoute $route): bool => in_array('POST', $route->methods(), true));
    $middleware = $post->gatherMiddleware();

    expect($middleware)->not->toContain('web')
        ->and(array_values(array_intersect($middleware, [ThrottleMcpByIp::class, 'auth:api', EnsureMcpAccess::class, 'throttle:mcp'])))
        ->toBe([ThrottleMcpByIp::class, 'auth:api', EnsureMcpAccess::class, 'throttle:mcp']);

    foreach (['GET', 'DELETE'] as $method) {
        $this->call($method, '/mcp')->assertStatus(405);
    }
});

test('the connected apps routes sit in the auth and verified group and are throttled', function () {
    foreach (['connected-apps.index', 'connected-apps.destroy'] as $name) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('auth', 'verified', 'throttle:connected-apps');
    }
});

test('the access matrix holds for every protected get route without parameters', function () {
    $uris = routesRequiringAuthAndVerified()
        ->filter(fn (LaravelRoute $route) => in_array('GET', $route->methods(), true) && ! str_contains($route->uri(), '{'))
        // Die OAuth-Consent-Seite braucht gueltige OAuth-Parameter und wird in OAuth/AuthorizationCodeFlowTest geprueft.
        ->reject(fn (LaravelRoute $route) => $route->uri() === 'oauth/authorize')
        ->map(fn (LaravelRoute $route) => '/'.$route->uri());

    expect($uris)->not->toBeEmpty();

    foreach ($uris as $uri) {
        // actingAs() aus dem vorigen Durchlauf wirkt sonst auf den Gast-Aufruf.
        auth()->forgetGuards();

        $this->get($uri)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get($uri)->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->unverified()->create())->getJson($uri)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($uri)->assertOk();
    }
});

test('the access matrix holds for the job details route with a fixed reference number', function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('inertia.ssr.enabled', false);
    Http::preventStrayRequests();
    Http::fake(['https://jobsuche.test/pc/v4/jobdetails/*' => Http::response(['referenznummer' => '10001-1234567890-S', 'stellenangebotsTitel' => 'Pflege'])]);

    $url = route('jobs.show', '10001-1234567890-S');

    $this->get($url)->assertRedirect(route('login'));
    $this->getJson($url)->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->unverified()->create())->getJson($url)->assertForbidden();
    Http::assertNothingSent();

    $this->actingAs(User::factory()->create())->get($url)->assertOk();
    Http::assertSentCount(1);
});

test('trusted hosts and proxies default to closed (formerly todo; real checks live in HardeningTest)', function () {
    // Echte Pruefungen: tests/Feature/HardeningTest.php (Proxy-Vertrauen, Host-Header, Security-Header).
    // Mail-Links sichert zusaetzlich URL::forceRootUrl (siehe UrlGenerationTest).
    expect(config('app.trusted_proxies'))->toBe('')
        ->and(config('app.trusted_hosts'))->toBe([]);
});
