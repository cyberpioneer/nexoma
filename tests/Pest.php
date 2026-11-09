<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Legt einen oeffentlichen OAuth-Client (Authorization Code + Refresh, wie bei der dynamischen Registrierung) an.
 *
 * @param  list<string>  $redirectUris
 */
function createPublicOAuthClient(array $redirectUris = ['https://claude.ai/api/mcp/auth_callback'], string $name = 'Test App'): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, $redirectUris, confidential: false);
}

/**
 * Erzeugt ein PKCE-Paar (Verifier und S256-Challenge).
 *
 * @return array{verifier: string, challenge: string}
 */
function pkcePair(): array
{
    $verifier = Str::random(64);

    return [
        'verifier' => $verifier,
        'challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
    ];
}

/**
 * Query fuer GET /oauth/authorize.
 *
 * @param  array<string, string|null>  $overrides
 * @return array<string, string>
 */
function authorizationQuery(Client $client, string $challenge, array $overrides = []): array
{
    return array_filter([
        'client_id' => $client->getKey(),
        'redirect_uri' => $client->redirect_uris[0],
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'state-123',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
        ...$overrides,
    ], fn (?string $value): bool => $value !== null);
}

/**
 * Ersetzt die Consent-Ansicht durch eine JSON-Antwort mit dem Autorisierungs-Token der Sitzung.
 */
function useJsonAuthorizationView(): void
{
    Passport::authorizationView(fn (array $parameters) => response()->json([
        'authToken' => $parameters['authToken'],
        'client' => $parameters['client']->getKey(),
    ]));
}

/**
 * Durchlaeuft Consent und gibt den Autorisierungscode zurueck (Benutzer muss angemeldet sein).
 */
function authorizeAndGetCode(User $user, Client $client, string $challenge): string
{
    useJsonAuthorizationView();

    $authorize = test()->actingAs($user)
        ->get('/oauth/authorize?'.http_build_query(authorizationQuery($client, $challenge)));

    // Hat der Benutzer dem Client bereits zugestimmt, ueberspringt Passport die Consent-Seite.
    if ($authorize->isRedirect()) {
        $location = $authorize->headers->get('Location');
    } else {
        $location = test()->post('/oauth/authorize', [
            'state' => 'state-123',
            'client_id' => $client->getKey(),
            'auth_token' => $authorize->assertOk()->json('authToken'),
        ])->assertRedirectContains($client->redirect_uris[0])->headers->get('Location');
    }

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['state'])->toBe('state-123');

    return $query['code'];
}

/**
 * Fuehrt den kompletten Authorization-Code-Ablauf mit PKCE aus und gibt die Token-Antwort zurueck.
 *
 * @return array{token_type: string, expires_in: int, access_token: string, refresh_token: string}
 */
function issueOAuthTokens(User $user, ?Client $client = null): array
{
    $client ??= createPublicOAuthClient();
    $pkce = pkcePair();
    $code = authorizeAndGetCode($user, $client, $pkce['challenge']);

    return test()->postJson('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => $client->redirect_uris[0],
        'code' => $code,
        'code_verifier' => $pkce['verifier'],
    ])->assertOk()->json();
}
