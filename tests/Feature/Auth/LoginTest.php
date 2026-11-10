<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;

test('login page is rendered as inertia page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Login'));
});

test('a user can log in and the session id changes', function () {
    $user = User::factory()->create();
    $this->get(route('login'));
    $before = session()->getId();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($before);
});

test('login finds the user regardless of email case and whitespace', function () {
    $user = User::factory()->create(['email' => 'max@example.com']);

    $this->post(route('login.store'), ['email' => ' Max@Example.COM ', 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('the remember cookie is only set when requested', function () {
    $user = User::factory()->create();
    $cookie = Auth::guard('web')->getRecallerName();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'remember' => '1'])
        ->assertCookie($cookie);

    Auth::guard('web')->logout();
    $this->flushSession();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertCookieMissing($cookie);
});

test('unknown email and wrong password produce the identical error', function () {
    $user = User::factory()->create();

    $unknown = $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'password']);
    $unknown->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $wrong = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    $wrong->assertSessionHasErrors(['email' => trans('auth.failed')]);

    expect($wrong->getStatusCode())->toBe($unknown->getStatusCode());
    $this->assertGuest();
});

test('login rejects non-string credentials', function () {
    $this->post(route('login.store'), ['email' => ['a@example.com'], 'password' => ['x']])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated users are redirected away from guest pages', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertRedirect(route('dashboard'));
})->with(['login', 'register', 'password.request']);

test('failed login stays on the login page even when the previous url is the home page', function () {
    $user = User::factory()->create();

    $this->withSession(['_previous' => ['url' => url('/')]])
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});
