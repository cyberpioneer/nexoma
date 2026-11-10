<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function registrationPayload(array $overrides = []): array
{
    return [
        'name' => 'Max Mustermann',
        'email' => 'max@example.com',
        'password' => 'secret-password-1',
        'password_confirmation' => 'secret-password-1',
        ...$overrides,
    ];
}

test('registration page is rendered as inertia page', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
});

test('a user can register, is logged in and receives the verification mail', function () {
    Notification::fake();
    $registered = false;
    Event::listen(Registered::class, function () use (&$registered): void {
        $registered = true;
    });

    $this->post(route('register.store'), registrationPayload(['email' => '  Max@Example.COM ']))
        ->assertRedirect('/dashboard');

    $user = User::where('email', 'max@example.com')->firstOrFail();

    expect($registered)->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->password)->not->toBe('secret-password-1')
        ->and(Hash::check('secret-password-1', $user->password))->toBeTrue();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('registration regenerates the session id', function () {
    $this->get(route('register'));
    $before = session()->getId();

    $this->post(route('register.store'), registrationPayload());

    expect(session()->getId())->not->toBe($before);
});

test('email_verified_at from the request is ignored', function () {
    $this->post(route('register.store'), registrationPayload(['email_verified_at' => now()->toDateTimeString()]));

    expect(User::firstOrFail()->email_verified_at)->toBeNull();
});

test('registration is rejected for invalid input', function (array $overrides, string $field) {
    $this->post(route('register.store'), registrationPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(User::count())->toBe(0);
    $this->assertGuest();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'array email' => [['email' => ['a@example.com']], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'missing confirmation' => [['password_confirmation' => ''], 'password'],
    'mismatching confirmation' => [['password_confirmation' => 'other-password-1'], 'password'],
    'overlong password' => [['password' => str_repeat('a', 256), 'password_confirmation' => str_repeat('a', 256)], 'password'],
]);

test('duplicate emails are rejected regardless of case', function (string $email) {
    User::factory()->create(['email' => 'max@example.com']);

    $this->post(route('register.store'), registrationPayload(['email' => $email]))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(1);
})->with(['max@example.com', 'MAX@Example.com', ' max@example.com ']);

test('the failed registration response does not leak the password', function () {
    $response = $this->from(route('register'))
        ->followingRedirects()
        ->post(route('register.store'), registrationPayload(['name' => '']));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('auth/Register')->missing('password'));
    expect($response->getContent())->not->toContain('secret-password-1');
});

test('failed registration stays on the register page even when the previous url is the home page', function () {
    $this->withSession(['_previous' => ['url' => url('/')]])
        ->post(route('register.store'), registrationPayload(['password_confirmation' => 'does-not-match']))
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});
