<?php

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;

/**
 * Setzt die Produktions-URL-Erzeugung (APP_URL + HTTPS) wie der AppServiceProvider es in Produktion tut.
 */
function useForcedProductionUrl(): void
{
    config(['app.url' => 'https://nexoma.example', 'mcp.authorization_server' => 'https://nexoma.example']);
    URL::forceRootUrl('https://nexoma.example');
    URL::forceScheme('https');
}

test('the authorization server metadata is complete and independent of the host header', function () {
    useForcedProductionUrl();

    $this->getJson('http://evil.test/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertExactJson([
            'issuer' => 'https://nexoma.example',
            'authorization_endpoint' => 'https://nexoma.example/oauth/authorize',
            'token_endpoint' => 'https://nexoma.example/oauth/token',
            'registration_endpoint' => 'https://nexoma.example/oauth/register',
            'response_types_supported' => ['code'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => ['mcp:use'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
        ]);
});

test('the authorization server metadata variant with a path answers identically', function () {
    useForcedProductionUrl();

    $plain = $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->json();

    $this->getJson('http://evil.test/.well-known/oauth-authorization-server/mcp')->assertOk()->assertExactJson($plain);
});

test('the protected resource metadata points at the mcp resource and the issuer', function () {
    useForcedProductionUrl();

    $this->getJson('http://evil.test/.well-known/oauth-protected-resource/mcp')
        ->assertOk()
        ->assertExactJson([
            'resource' => 'https://nexoma.example/mcp',
            'authorization_servers' => ['https://nexoma.example'],
            'scopes_supported' => ['mcp:use'],
        ]);

    $this->getJson('/.well-known/oauth-protected-resource')->assertOk()->assertJsonPath('authorization_servers', ['https://nexoma.example']);
});

test('the issuer comes from the configured app url and not from the request host', function () {
    config(['mcp.authorization_server' => 'https://nexoma.example']);

    $this->getJson('http://evil.test/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('issuer', 'https://nexoma.example');
});

test('the registration endpoint is advertised', function () {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('registration_endpoint', route('mcp.oauth.register'));
});

test('the metadata endpoints are rate limited per ip', function () {
    foreach (range(1, AppServiceProvider::OAUTH_METADATA_ATTEMPTS_PER_MINUTE) as $attempt) {
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk();
    }

    $this->getJson('/.well-known/oauth-authorization-server')->assertTooManyRequests();
});

test('the metadata endpoints set no cookie and need no session', function () {
    expect($this->getJson('/.well-known/oauth-authorization-server')->headers->getCookies())->toBeEmpty();
});
