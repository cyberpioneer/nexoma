<?php

use App\Http\Middleware\EnsureMcpAccess;
use App\Http\Middleware\ThrottleMcpByIp;
use App\Providers\AppServiceProvider;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

test('the post route has the middleware in the required order', function () {
    $post = collect(Route::getRoutes()->getRoutes())
        ->first(fn (LaravelRoute $route): bool => $route->uri() === 'mcp' && in_array('POST', $route->methods(), true));

    $middleware = array_values(array_filter(
        $post->gatherMiddleware(),
        fn (mixed $name): bool => is_string($name) && in_array($name, [ThrottleMcpByIp::class, 'auth:api', EnsureMcpAccess::class, 'throttle:mcp'], true),
    ));

    expect($middleware)->toBe([ThrottleMcpByIp::class, 'auth:api', EnsureMcpAccess::class, 'throttle:mcp'])
        ->and($post->gatherMiddleware())->not->toContain('web');
});

test('the per user limit answers 429 json on the 61st request', function () {
    actingAsMcpUser();

    foreach (range(1, AppServiceProvider::MCP_ATTEMPTS_PER_MINUTE) as $ignored) {
        mcpRequest('tools/list')->assertOk();
    }

    mcpRequest('tools/list')->assertStatus(429)->assertJsonStructure(['message']);
});

test('the per user limit is counted per user', function () {
    actingAsMcpUser();

    foreach (range(1, AppServiceProvider::MCP_ATTEMPTS_PER_MINUTE) as $ignored) {
        mcpRequest('tools/list');
    }

    auth()->forgetGuards();
    actingAsMcpUser();

    mcpRequest('tools/list')->assertOk();
});

test('the per ip limit applies before authentication', function () {
    foreach (range(1, AppServiceProvider::MCP_IP_ATTEMPTS_PER_MINUTE) as $ignored) {
        mcpRequest('tools/list')->assertUnauthorized();
    }

    mcpRequest('tools/list')->assertStatus(429);
});
