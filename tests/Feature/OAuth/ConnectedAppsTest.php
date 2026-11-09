<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Token;

test('the list shows the active connections of the user with whitelisted fields', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient(['https://claude.ai/api/mcp/auth_callback'], 'Claude');
    issueOAuthTokens($user, $client);

    $this->actingAs($user)->get(route('connected-apps.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ConnectedApps/Index')
            ->has('apps', 1)
            ->where('apps.0.id', $client->getKey())
            ->where('apps.0.name', 'Claude')
            ->where('apps.0.redirectHosts', [['host' => 'https://claude.ai', 'punycode' => false]])
            ->where('apps.0.connectedAt', now()->format('Y-m-d'))
            ->where('apps.0.scopes', ['mcp:use'])
            ->has('apps.0', 5));
});

test('an empty list is rendered when nothing is connected', function () {
    $this->actingAs(User::factory()->create())->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page->component('ConnectedApps/Index')->where('apps', []));
});

test('revoked tokens and expired tokens without an active refresh token are not listed', function () {
    $user = User::factory()->create();
    issueOAuthTokens($user);
    $user->tokens()->sole()->revoke();
    issueOAuthTokens($user, createPublicOAuthClient(name: 'Expired'));
    $expired = $user->tokens()->where('revoked', false)->with('refreshToken')->sole();
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();
    $expired->refreshToken->forceFill(['expires_at' => now()->subMinute()])->save();
    issueOAuthTokens($user, createPublicOAuthClient(name: 'RefreshRevoked'));
    $refreshRevoked = $user->tokens()->where('revoked', false)->where('expires_at', '>', now())->with('refreshToken')->sole();
    $refreshRevoked->forceFill(['expires_at' => now()->subMinute()])->save();
    $refreshRevoked->refreshToken->revoke();

    $this->actingAs($user)->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page->where('apps', []));
});

test('a connection with an expired access token but an active refresh token is listed and revocable', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient(name: 'Idle App');
    $tokens = issueOAuthTokens($user, $client);
    $user->tokens()->sole()->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->actingAs($user)->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page->has('apps', 1)->where('apps.0.name', 'Idle App')->etc());

    $this->actingAs($user)->delete(route('connected-apps.destroy', $client->getKey()))
        ->assertRedirect(route('connected-apps.index'));

    $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $client->getKey(),
    ])->assertStatus(400);
    $this->actingAs($user)->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page->where('apps', []));
});

test('all redirect hosts of a client are listed', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient(['https://one.example/cb', 'http://localhost:8080/cb', 'https://one.example/other'], 'Multi');
    issueOAuthTokens($user, $client);

    $this->actingAs($user)->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page->where('apps.0.redirectHosts', [
            ['host' => 'https://one.example', 'punycode' => false],
            ['host' => 'http://localhost:8080', 'punycode' => false],
        ])->etc());
});

test('only the own connections are visible', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    issueOAuthTokens($other, createPublicOAuthClient(name: 'Foreign App'));

    $this->actingAs($user)->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page->where('apps', []));
    expect($this->actingAs($user)->get(route('connected-apps.index'))->getContent())->not->toContain('Foreign App');
});

test('destroy revokes access and refresh tokens of the user for the client only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $client = createPublicOAuthClient();
    $mine = issueOAuthTokens($user, $client);
    issueOAuthTokens($other, $client);
    issueOAuthTokens($user, createPublicOAuthClient(name: 'Second'));

    $this->actingAs($user)->delete(route('connected-apps.destroy', $client->getKey()))
        ->assertRedirect(route('connected-apps.index'));

    $revoked = $user->tokens()->where('client_id', $client->getKey())->with('refreshToken')->sole();
    expect($revoked->revoked)->toBeTrue()
        ->and($revoked->refreshToken->revoked)->toBeTrue()
        ->and($other->tokens()->where('client_id', $client->getKey())->sole()->revoked)->toBeFalse()
        ->and($user->tokens()->where('client_id', '!=', $client->getKey())->sole()->revoked)->toBeFalse();

    $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $mine['refresh_token'],
        'client_id' => $client->getKey(),
    ])->assertStatus(400);
});

test('a revoked access token is rejected as bearer token', function () {
    Route::middleware('auth:api')->get('/_test/oauth-me', fn () => response()->json(['ok' => true]));
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $tokens = issueOAuthTokens($user, $client);

    $this->actingAs($user)->delete(route('connected-apps.destroy', $client->getKey()));
    auth()->forgetGuards();

    $this->withToken($tokens['access_token'])->getJson('/_test/oauth-me')->assertUnauthorized();
});

test('a foreign or unknown client id yields 404 and changes nothing', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $client = createPublicOAuthClient();
    issueOAuthTokens($other, $client);

    $this->actingAs($user)->delete(route('connected-apps.destroy', $client->getKey()))->assertNotFound();
    $this->actingAs($user)->delete(route('connected-apps.destroy', '00000000-0000-4000-8000-000000000000'))->assertNotFound();
    $this->actingAs($user)->delete('/connected-apps/not-a-uuid')->assertNotFound();

    expect(Token::query()->where('revoked', true)->count())->toBe(0);
});

test('guests and unverified users cannot manage connected apps', function () {
    $id = '00000000-0000-4000-8000-000000000000';

    $this->delete(route('connected-apps.destroy', $id))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->delete(route('connected-apps.destroy', $id))
        ->assertRedirect(route('verification.notice'));
});

test('the connected apps routes are throttled per user', function () {
    $user = User::factory()->create();
    RateLimiter::clear('connected-apps');

    foreach (range(1, 20) as $ignored) {
        $this->actingAs($user)->get(route('connected-apps.index'))->assertOk();
    }

    $this->actingAs($user)->get(route('connected-apps.index'))->assertTooManyRequests();
});

test('the connected apps page offers the address of the mcp endpoint for the connection guide', function () {
    $this->actingAs(User::factory()->create())->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mcpUrl', url('/mcp')));
});
