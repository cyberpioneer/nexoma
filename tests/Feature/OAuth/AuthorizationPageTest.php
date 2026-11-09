<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

function consentUrl(array $overrides = [], ?array &$pkce = null, ?Client &$client = null, string $name = 'Test App'): string
{
    $client ??= createPublicOAuthClient(name: $name);
    $pkce = pkcePair();

    return '/oauth/authorize?'.http_build_query(authorizationQuery($client, $pkce['challenge'], $overrides));
}

test('the consent page renders the whitelisted props only', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient(['https://claude.ai/api/mcp/auth_callback'], 'Claude');

    $this->actingAs($user)->get(consentUrl(client: $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('oauth/Authorize')
            ->where('client', ['name' => 'Claude'])
            ->where('clientId', $client->getKey())
            ->where('redirectHost', 'https://claude.ai')
            ->where('redirectPunycode', false)
            ->where('scopes', [['id' => 'mcp:use', 'description' => 'Use MCP server']])
            ->where('state', 'state-123')
            ->where('approveUrl', '/oauth/authorize')
            ->where('denyUrl', '/oauth/authorize')
            ->where('user', ['email' => $user->email])
            ->has('authToken')
            ->has('csrfToken')
            ->missing('client.secret')
            ->missing('client.redirect_uris')
            ->missing('client.logo_uri')
            ->missing('user.password')
            ->has('scopes', 1)
            ->etc());

    $content = $this->actingAs($user)->get(consentUrl(client: $client))->getContent();
    expect($content)->not->toContain($user->password);
});

test('the page props contain exactly the specified keys', function () {
    $response = $this->actingAs(User::factory()->create())->get(consentUrl());

    $props = array_keys($response->viewData('page')['props']);
    $shared = ['errors', 'name', 'auth', 'status', 'locale', 'availableLocales', 'translations', 'flash'];

    expect(array_values(array_diff($props, $shared)))->toEqualCanonicalizing([
        'client', 'clientId', 'redirectHost', 'redirectPunycode', 'scopes', 'authToken', 'state', 'approveUrl', 'denyUrl', 'csrfToken', 'user',
    ]);
});

test('a malicious client name is passed through unchanged as plain data', function () {
    $name = '<script>alert(1)</script>'.str_repeat('a', 200);

    $this->actingAs(User::factory()->create())->get(consentUrl(name: $name))
        ->assertInertia(fn (Assert $page) => $page->where('client.name', $name)->etc());

    expect((string) file_get_contents(resource_path('js/pages/oauth/Authorize.vue')))->not->toContain('v-html');
});

test('the redirect host is derived from the requested redirect uri only', function () {
    $client = createPublicOAuthClient(['http://localhost:8080/callback']);

    $this->actingAs(User::factory()->create())->get(consentUrl(client: $client))
        ->assertInertia(fn (Assert $page) => $page->where('redirectHost', 'http://localhost:8080')->etc());
});

test('the consent page forbids framing', function () {
    $response = $this->actingAs(User::factory()->create())->get(consentUrl());

    $response->assertOk()->assertHeader('X-Frame-Options', 'DENY');
    expect($response->headers->all('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
});

test('approving through the native form redirects to the registered uri with code and state', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $page = $this->actingAs($user)->get(consentUrl(client: $client))->viewData('page')['props'];

    $response = $this->post($page['approveUrl'], [
        '_token' => $page['csrfToken'],
        'state' => $page['state'],
        'client_id' => $page['clientId'],
        'auth_token' => $page['authToken'],
    ]);

    $response->assertRedirectContains($client->redirect_uris[0]);
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['code'])->not->toBeEmpty()->and($query['state'])->toBe('state-123');
});

test('denying through the native form redirects with access_denied', function () {
    $client = createPublicOAuthClient();
    $page = $this->actingAs(User::factory()->create())->get(consentUrl(client: $client))->viewData('page')['props'];

    $response = $this->post($page['denyUrl'], [
        '_method' => 'DELETE',
        '_token' => $page['csrfToken'],
        'state' => $page['state'],
        'client_id' => $page['clientId'],
        'auth_token' => $page['authToken'],
    ]);

    $response->assertRedirectContains($client->redirect_uris[0]);
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query['error'])->toBe('access_denied');
});

test('invalid requests show an error page without redirecting to a supplied uri', function (array $overrides) {
    $response = $this->actingAs(User::factory()->create())
        ->withHeader('Accept', 'text/html')
        ->get(consentUrl($overrides));

    $response->assertStatus(401)->assertInertia(fn (Assert $page) => $page->component('oauth/Error')->where('status', 401)->etc());
    expect($response->isRedirect())->toBeFalse()->and($response->headers->get('Location'))->toBeNull();
})->with([
    'unknown client' => [['client_id' => '00000000-0000-4000-8000-000000000000']],
    'unregistered redirect uri' => [['redirect_uri' => 'https://evil.example/callback']],
]);

test('a missing client id shows an error page without a location header', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withHeader('Accept', 'text/html')
        ->get(consentUrl(['client_id' => null]));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)->and($response->headers->get('Location'))->toBeNull();
    $response->assertInertia(fn (Assert $page) => $page->component('oauth/Error')->etc());
});

test('missing pkce parameters show an error page for browsers', function () {
    $response = $this->actingAs(User::factory()->create())
        ->withHeader('Accept', 'text/html')
        ->get(consentUrl(['code_challenge_method' => null]));

    $response->assertStatus(400)->assertInertia(fn (Assert $page) => $page->component('oauth/Error')->where('status', 400)->etc());
    expect($response->headers->get('Location'))->toBeNull();
});

test('machine clients keep the json error response', function (array $overrides, int $status) {
    $response = $this->actingAs(User::factory()->create())
        ->withHeader('Accept', 'application/json')
        ->get(consentUrl($overrides));

    $response->assertStatus($status)->assertJsonStructure(['error']);
    expect($response->headers->get('Location'))->toBeNull();
})->with([
    'unregistered redirect uri' => [['redirect_uri' => 'https://evil.example/callback'], 401],
    'missing pkce' => [['code_challenge_method' => null], 400],
]);

test('approve and deny are throttled per user', function () {
    $user = User::factory()->create();

    foreach (range(1, AppServiceProvider::OAUTH_CONSENT_ATTEMPTS_PER_MINUTE) as $ignored) {
        $this->actingAs($user)->post('/oauth/authorize', ['auth_token' => 'x'])->assertForbidden();
    }

    $this->actingAs($user)->post('/oauth/authorize')->assertStatus(429);
    $this->actingAs($user)->delete('/oauth/authorize')->assertStatus(429);
});

test('guests are sent to the login and returned to the consent page afterwards', function () {
    $url = consentUrl();

    $this->get($url)->assertRedirect(route('login'));
    expect(session('url.intended'))->toContain('/oauth/authorize');
});

test('unverified users are sent to the verification notice', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(consentUrl())
        ->assertRedirect(route('verification.notice'));
});

test('the consent texts exist in every language', function (string $locale) {
    $ui = require lang_path("{$locale}/ui.php");

    expect($ui)->toHaveKeys(['oauth.authorize.title', 'oauth.authorize.unverified', 'oauth.authorize.approve', 'oauth.authorize.deny', 'oauth.scope.mcp:use', 'oauth.punycode.warning', 'oauth.punycode.badge', 'oauth.error.title', 'oauth.error.text', 'oauth.error.back']);
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);

test('a server error during authorization shows the server error page, not the invalid request text', function () {
    $user = User::factory()->create();
    $url = consentUrl();

    Passport::loadKeysFrom(sys_get_temp_dir().'/nexoma-missing-keys-'.bin2hex(random_bytes(4)));

    $this->actingAs($user)->get($url)
        ->assertStatus(500)
        ->assertInertia(fn (Assert $page) => $page
            ->component('oauth/Error')
            ->where('status', 500));
});
