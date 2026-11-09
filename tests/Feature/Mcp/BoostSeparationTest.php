<?php

use App\Mcp\Servers\NexomaServer;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server;

test('the only http mcp server is the production server at /mcp', function () {
    $servers = Mcp::servers();

    expect($servers)->toHaveKey('mcp')
        ->and(array_filter(array_keys($servers), fn (string $key): bool => str_contains(strtolower($key), 'boost')))->toBeEmpty();

    $httpRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => str_starts_with($route->uri(), 'mcp') && in_array('POST', $route->methods(), true));

    expect($httpRoutes)->toHaveCount(1);
});

test('the route file registers only the production server and never boost', function () {
    $routes = (string) file_get_contents(base_path('routes/ai.php'));

    expect($routes)->toContain('NexomaServer::class')
        ->and(strtolower($routes))->not->toContain('boost');
});

test('the production server exposes no boost tools and no application code references boost', function () {
    actingAsMcpUser();

    $names = collect(mcpRequest('tools/list')->json('result.tools'))->pluck('name')->all();

    expect($names)->toBe(['job_search', 'job_details', 'job_search_options']);

    $found = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php' && str_contains(strtolower((string) file_get_contents($file->getPathname())), 'boost'))
        ->map(fn (SplFileInfo $file): string => $file->getPathname());

    expect($found->all())->toBe([]);
});

test('the production server class is not the boost server', function () {
    expect(NexomaServer::class)->toStartWith('App\\Mcp\\')
        ->and(is_subclass_of(NexomaServer::class, Server::class))->toBeTrue();
});
