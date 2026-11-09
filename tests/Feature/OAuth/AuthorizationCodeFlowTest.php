<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

beforeEach(function () {
    Route::middleware('auth:api')->get('/_test/oauth-me', fn (Request $request) => response()->json(['id' => $request->user()->getKey()]));
});

test('the authorization code flow with S256 issues an access and a refresh token', function () {
    $user = User::factory()->create();

    $tokens = issueOAuthTokens($user);

    expect($tokens['token_type'])->toBe('Bearer')
        ->and($tokens['expires_in'])->toBeBetween(3590, 3600)
        ->and($tokens['access_token'])->not->toBeEmpty()
        ->and($tokens['refresh_token'])->not->toBeEmpty();

    $this->withToken($tokens['access_token'])->getJson('/_test/oauth-me')
        ->assertOk()
        ->assertJson(['id' => $user->getKey()]);
});

test('the issued access token carries only the mcp:use scope', function () {
    $user = User::factory()->create();

    issueOAuthTokens($user);

    $token = $user->tokens()->sole();

    expect($token->scopes)->toBe(['mcp:use'])
        ->and($token->revoked)->toBeFalse()
        ->and(abs($token->expires_at->diffInMinutes(now(), true) - 60))->toBeLessThan(2);
});

test('a client without a scope parameter receives the default scope only', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $pkce = pkcePair();
    useJsonAuthorizationView();

    $authToken = $this->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, $pkce['challenge'], ['scope' => null])))
        ->assertOk()
        ->json('authToken');

    $location = $this->post('/oauth/authorize', ['state' => 'state-123', 'client_id' => $client->getKey(), 'auth_token' => $authToken])
        ->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $client->getKey(), 'redirect_uri' => $client->redirect_uris[0],
        'code' => $query['code'], 'code_verifier' => $pkce['verifier'],
    ])->assertOk();

    expect($user->tokens()->sole()->scopes)->toBe(['mcp:use']);
});

test('a refresh issues a new pair and rotates the old refresh token', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $first = issueOAuthTokens($user, $client);

    $second = $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token', 'refresh_token' => $first['refresh_token'], 'client_id' => $client->getKey(),
    ])->assertOk()->json();

    expect($second['access_token'])->not->toBe($first['access_token'])
        ->and($second['refresh_token'])->not->toBe($first['refresh_token']);

    // Altes Refresh-Token ist ungueltig (Rotation), altes Access-Token ist widerrufen.
    $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token', 'refresh_token' => $first['refresh_token'], 'client_id' => $client->getKey(),
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');

    $this->withToken($first['access_token'])->getJson('/_test/oauth-me')->assertUnauthorized();
    $this->withToken($second['access_token'])->getJson('/_test/oauth-me')->assertOk();
});

test('a wrong code verifier is rejected', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $code = authorizeAndGetCode($user, $client, pkcePair()['challenge']);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $client->getKey(), 'redirect_uri' => $client->redirect_uris[0],
        'code' => $code, 'code_verifier' => pkcePair()['verifier'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

test('a missing code verifier is rejected', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $code = authorizeAndGetCode($user, $client, pkcePair()['challenge']);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $client->getKey(), 'redirect_uri' => $client->redirect_uris[0],
        'code' => $code,
    ])->assertStatus(400);
});

test('an authorization code can be used only once', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $pkce = pkcePair();
    $code = authorizeAndGetCode($user, $client, $pkce['challenge']);
    $payload = [
        'grant_type' => 'authorization_code', 'client_id' => $client->getKey(), 'redirect_uri' => $client->redirect_uris[0],
        'code' => $code, 'code_verifier' => $pkce['verifier'],
    ];

    $this->postJson('/oauth/token', $payload)->assertOk();
    $this->postJson('/oauth/token', $payload)->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

test('a public client must send a code challenge', function () {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();

    $response = $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, 'x', ['code_challenge' => null, 'code_challenge_method' => null])));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and($response->headers->has('Location'))->toBeFalse();
    expect($response->json('error'))->toBe('invalid_request');
});

test('an invalid code challenge method is rejected', function () {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();

    $response = $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'], ['code_challenge_method' => 'S512'])));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and($response->json('error'))->toBe('invalid_request');
});

test('only the S256 code challenge method is accepted', function (array $overrides) {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();

    $response = $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'], $overrides)));

    $response->assertStatus(400)->assertJsonPath('error', 'invalid_request');
    expect($response->headers->has('Location'))->toBeFalse();
})->with([
    'plain' => [['code_challenge_method' => 'plain']],
    'method missing' => [['code_challenge_method' => null]],
    'lowercase s256' => [['code_challenge_method' => 's256']],
    'S512' => [['code_challenge_method' => 'S512']],
    'empty method' => [['code_challenge_method' => '']],
    'challenge missing' => [['code_challenge' => null]],
]);

test('a code challenge method sent as an array is rejected', function () {
    $client = createPublicOAuthClient();
    $query = http_build_query(authorizationQuery($client, pkcePair()['challenge'], ['code_challenge_method' => null])).'&code_challenge_method[]=S256';

    $this->actingAs(User::factory()->create())->get('/oauth/authorize?'.$query)
        ->assertStatus(400)->assertJsonPath('error', 'invalid_request');
});

test('guests with a weak code challenge are rejected before the login redirect', function () {
    $client = createPublicOAuthClient();

    $this->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'], ['code_challenge_method' => 'plain'])))
        ->assertStatus(400)->assertJsonPath('error', 'invalid_request');

    $this->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'], ['code_challenge_method' => null])))
        ->assertStatus(400);
});

test('the S256 check also applies after the login redirect', function () {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();
    $user = User::factory()->create();
    $url = '/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge']));

    $this->get($url)->assertRedirect(route('login'));
    $intended = session('url.intended');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($intended);

    $this->get($intended)->assertOk()->assertJsonStructure(['authToken']);

    // Eine nachtraeglich abgeschwaechte Anfrage derselben Sitzung wird ebenfalls abgewiesen.
    $this->get(str_replace('code_challenge_method=S256', 'code_challenge_method=plain', $intended))->assertStatus(400);
});

test('a rejected authorization request cannot be approved into a code', function () {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();
    $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'], ['code_challenge_method' => 'plain'])))
        ->assertStatus(400);

    $response = $this->post('/oauth/authorize', ['state' => 'state-123', 'client_id' => $client->getKey(), 'auth_token' => 'anything']);

    expect($response->headers->get('Location') ?? '')->not->toContain('code=')
        ->and(Passport::authCode()->newQuery()->count())->toBe(0);
});

test('a previously granted scope no longer skips the consent page', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    issueOAuthTokens($user, $client);
    useJsonAuthorizationView();

    $this->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'])))
        ->assertOk()
        ->assertJsonStructure(['authToken']);

    // Ein vom Client mitgesendetes prompt (z. B. none) kann die Consent-Seite nicht umgehen.
    $this->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'], ['prompt' => 'none'])))
        ->assertOk()
        ->assertJsonStructure(['authToken']);
});

test('an unregistered redirect uri never produces a redirect', function () {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();

    $response = $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'], ['redirect_uri' => 'https://evil.example/cb'])));

    expect($response->getStatusCode())->toBeGreaterThanOrEqual(400)
        ->and($response->headers->has('Location'))->toBeFalse();
    $response->assertJsonPath('error', 'invalid_client');
});

test('approving without a valid auth token is rejected', function () {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();
    $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'])))
        ->assertOk();

    $response = $this->post('/oauth/authorize', ['state' => 'state-123', 'client_id' => $client->getKey(), 'auth_token' => 'wrong']);

    expect($response->headers->get('Location') ?? '')->not->toContain('code=');
});

test('denying redirects to the registered uri with access_denied', function () {
    $client = createPublicOAuthClient();
    useJsonAuthorizationView();
    $authToken = $this->actingAs(User::factory()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'])))
        ->json('authToken');

    $response = $this->delete('/oauth/authorize', ['state' => 'state-123', 'client_id' => $client->getKey(), 'auth_token' => $authToken]);

    $response->assertRedirectContains($client->redirect_uris[0]);
    expect($response->headers->get('Location'))->toContain('error=access_denied')->not->toContain('code=');
});

test('approve and deny reject requests without a csrf token', function (string $method) {
    $client = createPublicOAuthClient();
    $this->actingAs(User::factory()->create());

    // Im Testlauf wird die CSRF-Pruefung uebersprungen; fuer diese Anfrage gilt daher Produktion.
    app()->detectEnvironment(fn (): string => 'production');
    $this->call($method, '/oauth/authorize', ['client_id' => $client->getKey(), 'auth_token' => 'x'])->assertStatus(419);
    app()->detectEnvironment(fn (): string => 'testing');
})->with(['POST', 'DELETE']);

test('guests are sent to the login and returned to the authorization request afterwards', function () {
    $client = createPublicOAuthClient();
    $url = '/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge']));
    $user = User::factory()->create();

    $this->get($url)->assertRedirect(route('login'));

    $intended = session('url.intended');
    expect($intended)->toStartWith(url('/oauth/authorize?'))->toContain('client_id='.$client->getKey());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($intended);
});

test('unverified users are sent to the verification notice', function () {
    $client = createPublicOAuthClient();

    $this->actingAs(User::factory()->unverified()->create())
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, pkcePair()['challenge'])))
        ->assertRedirect(route('verification.notice'));
});

test('unsupported grant types are rejected with a precise error', function (string $grantType, array $extra) {
    $client = createPublicOAuthClient();

    $this->postJson('/oauth/token', ['grant_type' => $grantType, 'client_id' => $client->getKey(), ...$extra])
        ->assertStatus(400)
        ->assertJsonPath('error', 'unsupported_grant_type')
        ->assertJsonMissingPath('access_token');
})->with([
    'password' => ['password', ['username' => 'a@b.test', 'password' => 'password']],
    'device code' => ['urn:ietf:params:oauth:grant-type:device_code', ['device_code' => 'x']],
    'implicit style' => ['token', []],
]);

test('registered clients cannot use the client credentials grant', function () {
    $public = createPublicOAuthClient();
    $confidential = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Conf', ['https://claude.ai/cb'], confidential: true);

    // league/oauth2-server aktiviert ClientCredentialsGrant in Passport immer; gesperrt wird ueber die grant_types des Clients.
    $this->postJson('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $public->getKey()])
        ->assertStatus(400)->assertJsonPath('error', 'unauthorized_client')->assertJsonMissingPath('access_token');
    $this->postJson('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $confidential->getKey(), 'client_secret' => $confidential->plainSecret])
        ->assertStatus(400)->assertJsonPath('error', 'unauthorized_client')->assertJsonMissingPath('access_token');
});

test('the token response is not cacheable', function () {
    $client = createPublicOAuthClient();
    $pkce = pkcePair();
    $code = authorizeAndGetCode(User::factory()->create(), $client, $pkce['challenge']);

    $response = $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $client->getKey(), 'redirect_uri' => $client->redirect_uris[0],
        'code' => $code, 'code_verifier' => $pkce['verifier'],
    ])->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Pragma'))->toBe('no-cache');
});

test('the password grant reports itself as unsupported', function () {
    $client = createPublicOAuthClient();

    $this->postJson('/oauth/token', ['grant_type' => 'password', 'client_id' => $client->getKey(), 'username' => 'a@b.test', 'password' => 'x'])
        ->assertStatus(400)
        ->assertJsonPath('error', 'unsupported_grant_type');
});

test('the token endpoint works without a session and sets no cookie', function () {
    $response = $this->postJson('/oauth/token', ['grant_type' => 'authorization_code']);

    expect($response->headers->getCookies())->toBeEmpty();
});
