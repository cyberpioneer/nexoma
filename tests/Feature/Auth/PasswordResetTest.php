<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

test('forgot password page is rendered as inertia page', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/ForgotPassword'));
});

test('reset page receives token and email', function () {
    $this->get(route('password.reset', ['token' => 'abc123', 'email' => 'max@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/ResetPassword')
            ->where('token', 'abc123')
            ->where('email', 'max@example.com'));
});

test('reset page ignores a non-string email query', function () {
    $this->get(route('password.reset', ['token' => 'abc123']).'?email[]=x')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('email', ''));
});

test('the reset link is only sent to existing users but the response is identical', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'max@example.com']);

    $known = $this->post(route('password.email'), ['email' => 'Max@Example.com']);
    $unknown = $this->post(route('password.email'), ['email' => 'nobody@example.com']);

    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertSentTimes(ResetPassword::class, 1);

    expect($unknown->getStatusCode())->toBe($known->getStatusCode())
        ->and($unknown->headers->get('Location'))->toBe($known->headers->get('Location'));
    $known->assertSessionHasNoErrors()->assertSessionHas('status', trans('passwords.sent'));
    $unknown->assertSessionHasNoErrors()->assertSessionHas('status', trans('passwords.sent'));
});

test('a throttled reset request gets the neutral response', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);
    $this->post(route('password.email'), ['email' => $user->email])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', trans('passwords.sent'));

    Notification::assertSentTimes(ResetPassword::class, 1);
});

test('an invalid email format is a validation error', function (mixed $email) {
    $this->post(route('password.email'), ['email' => $email])
        ->assertSessionHasErrors('email');
})->with(['not-an-email', '', [['a@example.com']]]);

test('the password can be reset with a valid token and the token is single-use', function () {
    $user = User::factory()->create(['remember_token' => 'old-remember-token']);
    $oldHash = $user->password;
    $token = Password::createToken($user);
    $payload = [
        'token' => $token,
        'email' => strtoupper($user->email),
        'password' => 'new-secret-password-1',
        'password_confirmation' => 'new-secret-password-1',
    ];

    $this->post(route('password.update'), $payload)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();
    expect($user->password)->not->toBe($oldHash)
        ->and($user->password)->not->toBe('new-secret-password-1')
        ->and(Hash::check('new-secret-password-1', $user->password))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->remember_token)->not->toBe('old-remember-token');

    $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'new-secret-password-1'])
        ->assertRedirect('/dashboard');
});

test('reset fails for invalid token, other email and weak password', function (string $case) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $token = Password::createToken($user);

    $payload = [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password-1',
        'password_confirmation' => 'new-secret-password-1',
    ];

    match ($case) {
        'invalid token' => $payload['token'] = 'invalid-token',
        'foreign email' => $payload['email'] = $other->email,
        'weak password' => $payload = [...$payload, 'password' => 'short', 'password_confirmation' => 'short'],
        'unknown email' => $payload['email'] = 'nobody@example.com',
    };

    $this->post(route('password.update'), $payload)->assertSessionHasErrors();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with(['invalid token', 'foreign email', 'weak password', 'unknown email']);

test('an expired token is rejected', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->travel(config('auth.passwords.users.expire') + 1)->minutes();

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-secret-password-1',
        'password_confirmation' => 'new-secret-password-1',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('an existing session of the user becomes invalid after a password reset', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $oldSession = session()->all();
    $this->get(route('dashboard'))->assertOk();

    $this->flushSession();
    app('auth')->forgetGuards();

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => 'new-secret-password-1',
        'password_confirmation' => 'new-secret-password-1',
    ])->assertSessionHasNoErrors();

    app('auth')->forgetGuards();
    $this->flushSession()->withSession($oldSession)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('the session of a user stays valid without a password change', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $oldSession = session()->all();

    app('auth')->forgetGuards();
    $this->flushSession()->withSession($oldSession)->get(route('dashboard'))->assertOk();
});

test('failed password forms stay on their own page even when the previous url is the home page', function () {
    $this->withSession(['_previous' => ['url' => url('/')]])
        ->post(route('password.email'), ['email' => 'not-an-email'])
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');

    $this->withSession(['_previous' => ['url' => url('/')]])
        ->post(route('password.update'), ['token' => 'abc123', 'email' => 'max@example.com', 'password' => 'x', 'password_confirmation' => 'y'])
        ->assertRedirect(route('password.reset', ['token' => 'abc123', 'email' => 'max@example.com']))
        ->assertSessionHasErrors('email');
});
