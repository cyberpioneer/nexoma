<?php

use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
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
        'home', 'login', 'login.store', 'register', 'register.store',
        'password.request', 'password.email', 'password.reset', 'password.update',
        'logout', 'verification.notice', 'verification.send', 'verification.verify',
        'password.confirm', 'password.confirm.store', 'password.confirmation',
        'storage.local', 'storage.local.upload', 'boost.browser-logs',
    ];
    $publicUris = ['up', '_inertia/devtools/entries', '_inertia/devtools/entries/{id}'];

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

test('the access matrix holds for every protected get route without parameters', function () {
    $uris = routesRequiringAuthAndVerified()
        ->filter(fn (LaravelRoute $route) => in_array('GET', $route->methods(), true) && ! str_contains($route->uri(), '{'))
        ->map(fn (LaravelRoute $route) => '/'.$route->uri());

    expect($uris)->not->toBeEmpty();

    foreach ($uris as $uri) {
        $this->get($uri)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->unverified()->create())->get($uri)->assertRedirect(route('verification.notice'));
        $this->actingAs(User::factory()->unverified()->create())->getJson($uri)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($uri)->assertOk();
    }
});

test('trusted hosts and proxies are intentionally not configured yet', function () {
    // TODO: Nach Klaerung von Produktionshost und Proxy-Topologie trustHosts()/trustProxies() setzen
    // und diesen Test durch eine echte Pruefung ersetzen. Mail-Links sichert bis dahin URL::forceRootUrl (siehe UrlGenerationTest).
})->todo();
