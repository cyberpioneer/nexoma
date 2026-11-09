<?php

use App\Models\User;

test('a user can log out via post and the session is invalidated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $before = session()->getId();

    $this->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
    expect(session()->getId())->not->toBe($before);
});

test('logout flags the inertia history to be cleared', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertSessionHas('inertia.clear_history', true);
});

test('logout is not available via get', function () {
    $this->actingAs(User::factory()->create())
        ->get('/logout')
        ->assertMethodNotAllowed();
});

test('logout requires authentication', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));
});
