<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Carbon\CarbonInterval;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
});

test('a request without a token gets 401 with the www-authenticate header and no redirect', function (string $accept) {
    $response = mcpRequest('tools/list', headers: ['Accept' => $accept]);

    $response->assertUnauthorized();
    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('Bearer realm="mcp"')
        ->toContain('resource_metadata="')
        ->toContain('/.well-known/oauth-protected-resource/mcp');
    expect($response->headers->get('Location'))->toBeNull();
})->with(['text/html', 'text/event-stream', 'application/json, text/event-stream']);

test('a token without the mcp scope gets 403 insufficient_scope', function () {
    actingAsMcpUser(scopes: []);

    mcpRequest('tools/list')->assertForbidden()->assertExactJson(['error' => 'insufficient_scope']);
});

test('a wildcard token without the explicit mcp scope gets 403 insufficient_scope', function () {
    actingAsMcpUser(scopes: ['*']);

    mcpRequest('tools/list')->assertForbidden()->assertExactJson(['error' => 'insufficient_scope']);
});

test('a token with a foreign scope only gets 403 insufficient_scope', function () {
    actingAsMcpUser(scopes: ['other:scope']);

    mcpRequest('tools/list')->assertForbidden()->assertExactJson(['error' => 'insufficient_scope']);
});

test('an unverified user gets 403 email_not_verified', function () {
    actingAsMcpUser(User::factory()->unverified()->create());

    mcpRequest('tools/list')->assertForbidden()->assertExactJson(['error' => 'email_not_verified']);
});

test('a client credentials token without a user is rejected like an invalid token', function () {
    $guest = mcpRequest('tools/list');

    auth()->forgetGuards();
    $client = app(ClientRepository::class)->createClientCredentialsGrantClient('M2M');
    Passport::actingAsClient($client, ['mcp:use']);

    $response = mcpRequest('tools/list');

    $response->assertUnauthorized();
    expect($response->getContent())->toBe($guest->getContent())
        ->and($response->headers->get('WWW-Authenticate'))->toContain('Bearer realm="mcp"');
});

test('a verified user with the scope can initialize and list the tools', function () {
    actingAsMcpUser();

    mcpRequest('initialize', [
        'protocolVersion' => '2025-06-18',
        'capabilities' => (object) [],
        'clientInfo' => ['name' => 'test', 'version' => '1'],
    ])->assertOk()->assertJsonPath('result.serverInfo.name', 'Nexoma Job Search');

    mcpRequest('tools/list')->assertOk()->assertJsonCount(3, 'result.tools');
});

test('a revoked token gets 401', function () {
    $user = User::factory()->create();
    $tokens = issueOAuthTokens($user);
    auth()->forgetGuards();

    mcpRequest('tools/list', headers: ['Authorization' => 'Bearer '.$tokens['access_token']])->assertOk();

    $user->tokens()->update(['revoked' => true]);
    auth()->forgetGuards();

    mcpRequest('tools/list', headers: ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
});

test('an expired token gets 401', function () {
    $user = User::factory()->create();

    try {
        Passport::tokensExpireIn(CarbonInterval::minutes(-5));
        $tokens = issueOAuthTokens($user);
    } finally {
        Passport::tokensExpireIn(CarbonInterval::hours(AppServiceProvider::OAUTH_ACCESS_TOKEN_LIFETIME_HOURS));
    }

    auth()->forgetGuards();

    mcpRequest('tools/list', headers: ['Authorization' => 'Bearer '.$tokens['access_token']])->assertUnauthorized();
});

test('get and delete on the mcp route answer 405 without details', function (string $method) {
    $response = $this->call($method, '/mcp');

    $response->assertStatus(405);
    expect($response->getContent())->toBe('')
        ->and($response->headers->get('Allow'))->toBe('POST');
})->with(['GET', 'DELETE']);

test('mcp responses set no cookie', function () {
    expect(mcpRequest('tools/list')->assertUnauthorized()->headers->getCookies())->toBeEmpty();

    actingAsMcpUser();
    $response = mcpRequest('tools/list')->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
});
