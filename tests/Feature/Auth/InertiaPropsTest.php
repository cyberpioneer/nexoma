<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests get no user in the shared props', function () {
    $this->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

test('the shared user contains only whitelisted fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->has('auth.user.email_verified_at')
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token')
            ->missing('auth.user.created_at')
            ->missing('auth.user.updated_at')
            ->has('auth.user', 4));
});

test('the password hash is nowhere in the response', function () {
    $user = User::factory()->create();

    $content = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect($content)->not->toContain($user->password)
        ->and($content)->not->toContain((string) $user->remember_token);
});

test('an unverified user has a null verification date in the props', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.email_verified_at', null));
});

test('status locale and translations are shared', function () {
    $this->withSession(['status' => 'verification-link-sent'])
        ->get(route('login'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('status', 'verification-link-sent')
            ->where('locale', app()->getLocale())
            ->where('translations', fn ($translations) => collect($translations)->get('auth.login.title') === trans('ui.auth.login.title')));
});
