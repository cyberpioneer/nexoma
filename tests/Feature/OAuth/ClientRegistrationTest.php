<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

function registerOAuthClient(array $payload = []): TestResponse
{
    return test()->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ...$payload,
    ]);
}

test('a permitted client is registered as a public authorization code client', function () {
    $response = registerOAuthClient()
        ->assertCreated()
        ->assertJsonPath('token_endpoint_auth_method', 'none')
        ->assertJsonPath('scope', 'mcp:use')
        ->assertJsonPath('response_types', ['code'])
        ->assertJsonPath('redirect_uris', ['https://claude.ai/api/mcp/auth_callback'])
        ->assertJsonPath('grant_types', ['authorization_code', 'refresh_token']);

    $client = Passport::client()->newQuery()->findOrFail($response->json('client_id'));

    expect($client->confidential())->toBeFalse()
        ->and($client->secret)->toBeNull()
        ->and($client->revoked)->toBeFalse()
        ->and($client->grant_types)->not->toContain('client_credentials', 'password', 'urn:ietf:params:oauth:grant-type:device_code', 'implicit')
        ->and($client->owner_id)->toBeNull();
});

test('registering a client creates no token and no access for any user', function () {
    registerOAuthClient()->assertCreated();

    expect(Passport::token()->newQuery()->count())->toBe(0)
        ->and(Passport::authCode()->newQuery()->count())->toBe(0)
        ->and(Passport::refreshToken()->newQuery()->count())->toBe(0);
});

test('forbidden redirect uris are rejected', function (string $uri) {
    registerOAuthClient(['redirect_uris' => [$uri]])
        ->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');

    expect(Passport::client()->newQuery()->count())->toBe(0);
})->with([
    'foreign domain' => 'https://evil.example/cb',
    'lookalike prefix' => 'https://claude.ai.evil.example/cb',
    'userinfo' => 'https://claude.ai@evil.example/cb',
    'javascript' => 'javascript:alert(1)',
    'data' => 'data:text/html;base64,PHNjcmlwdD4=',
    'file' => 'file:///etc/passwd',
    'custom scheme' => 'cursor://anysphere.cursor/oauth/callback',
    'http on a non loopback host' => 'http://claude.ai/cb',
    'plain http foreign host' => 'http://evil.example/cb',
    'not a url' => 'claude.ai/cb',
]);

test('loopback redirects are accepted only when configured', function () {
    expect(config('mcp.redirect_domains'))->not->toContain('http://localhost');
    registerOAuthClient(['redirect_uris' => ['http://localhost:8123/cb']])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');

    config(['mcp.redirect_domains' => ['https://claude.ai', 'http://localhost', 'http://127.0.0.1']]);
    registerOAuthClient(['redirect_uris' => ['http://localhost:8123/cb']])->assertCreated();
    registerOAuthClient(['redirect_uris' => ['http://127.0.0.1:8123/cb']])->assertCreated();
    registerOAuthClient(['redirect_uris' => ['http://localhost.evil.example/cb']])->assertStatus(400);
});

test('an empty domain list permits no registration', function () {
    config(['mcp.redirect_domains' => []]);

    registerOAuthClient()->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    registerOAuthClient(['redirect_uris' => ['http://localhost:1/cb']])->assertStatus(400);
});

test('custom schemes stay forbidden by default', function () {
    expect(config('mcp.custom_schemes'))->toBe([]);

    registerOAuthClient(['redirect_uris' => ['vscode://vscode.github-authentication/did-authenticate']])->assertStatus(400);
});

test('invalid client metadata is rejected', function (array $payload) {
    registerOAuthClient($payload)->assertStatus(400);
})->with([
    'more than ten uris' => [['redirect_uris' => array_map(fn (int $i) => "https://claude.ai/cb/$i", range(1, 11))]],
    'name longer than 255 characters' => [['client_name' => str_repeat('a', 256)]],
    'no redirect uris' => [['redirect_uris' => []]],
    'redirect uri too long' => [['redirect_uris' => ['https://claude.ai/'.str_repeat('a', 1100)]]],
    'logo is not a url' => [['logo_uri' => 'javascript:alert(1)']],
]);

test('a client name of exactly 255 characters is stored verbatim as text', function () {
    $name = '<script>alert(1)</script>'.str_repeat('x', 230);

    $id = registerOAuthClient(['client_name' => $name])->assertCreated()->json('client_id');

    expect(Passport::client()->newQuery()->findOrFail($id)->name)->toBe($name);
});

test('registration is limited to ten per hour and ip', function () {
    foreach (range(1, AppServiceProvider::OAUTH_REGISTER_ATTEMPTS_PER_HOUR) as $attempt) {
        registerOAuthClient()->assertCreated();
    }

    registerOAuthClient()->assertTooManyRequests();
    expect(Passport::client()->newQuery()->count())->toBe(AppServiceProvider::OAUTH_REGISTER_ATTEMPTS_PER_HOUR);
});

test('registration is limited globally across ips', function () {
    $key = md5('oauth-register'.'global');

    foreach (range(1, AppServiceProvider::OAUTH_REGISTER_GLOBAL_ATTEMPTS_PER_HOUR) as $hit) {
        RateLimiter::hit($key, 3600);
    }

    registerOAuthClient()->assertTooManyRequests();
    expect(Passport::client()->newQuery()->count())->toBe(0);
});

test('the global registration limiter allows 200 per hour in total', function () {
    $limiter = RateLimiter::limiter('oauth-register');
    $limits = $limiter(request());

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->maxAttempts)->toBe(AppServiceProvider::OAUTH_REGISTER_ATTEMPTS_PER_HOUR)
        ->and($limits[1]->maxAttempts)->toBe(AppServiceProvider::OAUTH_REGISTER_GLOBAL_ATTEMPTS_PER_HOUR)
        ->and($limits[1]->decaySeconds)->toBe(3600)
        ->and($limits[1]->key)->toBe('global');
});

test('registration stops with 503 once the daily limit is reached', function () {
    config(['mcp.registration_limits.per_day' => 2]);

    registerOAuthClient()->assertCreated();
    registerOAuthClient()->assertCreated();
    registerOAuthClient()->assertStatus(503)->assertExactJson(['error' => 'temporarily_unavailable']);

    expect(Passport::client()->newQuery()->count())->toBe(2);
});

test('registration stops with 503 once the total limit of active clients is reached', function () {
    config(['mcp.registration_limits.total' => 1]);
    createPublicOAuthClient();
    // Aeltere Clients zaehlen nicht zum Tageslimit, wohl aber zur Gesamtzahl.
    Passport::client()->newQuery()->update(['created_at' => now()->subDays(5)]);

    registerOAuthClient()->assertStatus(503)->assertExactJson(['error' => 'temporarily_unavailable']);
});

test('revoked clients do not count towards the total limit', function () {
    config(['mcp.registration_limits.total' => 1]);
    createPublicOAuthClient()->forceFill(['revoked' => true, 'created_at' => now()->subDays(5)])->save();

    registerOAuthClient()->assertCreated();
});

test('the registration endpoint sets no cookie', function () {
    expect(registerOAuthClient()->headers->getCookies())->toBeEmpty();
});
