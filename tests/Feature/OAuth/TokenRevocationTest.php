<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

beforeEach(function () {
    Route::middleware('auth:api')->get('/_test/oauth-me', fn (Request $request) => response()->json(['id' => $request->user()->getKey()]));
});

function resetPasswordFor(User $user): TestResponse
{
    // Der Reset erfolgt anonym; actingAs() aus dem Ablauf davor darf nicht mehr wirken.
    auth()->forgetGuards();

    return test()->post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'a-new-password-123',
        'password_confirmation' => 'a-new-password-123',
    ]);
}

test('a password reset revokes all tokens of the user and leaves other users untouched', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $client = createPublicOAuthClient();

    $first = issueOAuthTokens($user, $client);
    $second = issueOAuthTokens($user, $client);
    $others = issueOAuthTokens($other, $client);

    resetPasswordFor($user)->assertRedirect(route('login'));

    expect(Passport::token()->newQuery()->where('user_id', $user->getKey())->where('revoked', false)->count())->toBe(0)
        ->and(Passport::token()->newQuery()->where('user_id', $other->getKey())->where('revoked', false)->count())->toBe(1);

    $accessTokenIds = Passport::token()->newQuery()->where('user_id', $user->getKey())->pluck('id');
    expect(Passport::refreshToken()->newQuery()->whereIn('access_token_id', $accessTokenIds)->where('revoked', false)->count())->toBe(0)
        ->and(Passport::refreshToken()->newQuery()->where('revoked', false)->count())->toBe(1);

    foreach ([$first, $second] as $tokens) {
        $this->withToken($tokens['access_token'])->getJson('/_test/oauth-me')->assertUnauthorized();
        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token'], 'client_id' => $client->getKey(),
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    $this->withToken($others['access_token'])->getJson('/_test/oauth-me')->assertOk()->assertJson(['id' => $other->getKey()]);
});

test('a password reset revokes unused authorization codes of the user', function () {
    $user = User::factory()->create();
    $client = createPublicOAuthClient();
    $pkce = pkcePair();
    $code = authorizeAndGetCode($user, $client, $pkce['challenge']);

    resetPasswordFor($user);

    $this->postJson('/oauth/token', [
        'grant_type' => 'authorization_code', 'client_id' => $client->getKey(), 'redirect_uri' => $client->redirect_uris[0],
        'code' => $code, 'code_verifier' => $pkce['verifier'],
    ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
});

test('a failed password reset revokes nothing', function () {
    $user = User::factory()->create();
    $tokens = issueOAuthTokens($user);

    auth()->forgetGuards();
    $this->post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => $user->email,
        'password' => 'a-new-password-123',
        'password_confirmation' => 'does-not-match',
    ])->assertSessionHasErrors('password');

    $this->withToken($tokens['access_token'])->getJson('/_test/oauth-me')->assertOk();
});
