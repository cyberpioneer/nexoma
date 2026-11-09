<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests see the welcome page without a user', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user', null));
});

test('a signed in user sees the welcome page with the own user in the shared props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('auth.user.id', $user->id));
});

test('the welcome page translations exist and differ in every locale', function (string $locale) {
    $this->withSession(['locale' => $locale])->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', $locale)
            ->where('translations', fn ($translations): bool => collect($translations)->only([
                'home.title', 'home.lead', 'home.cta.register', 'home.point1.text', 'skip.link', 'footer.note',
            ])->every(fn (string $text): bool => $text !== '' && ! str_starts_with($text, 'home.'))));
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);

test('the pages using the layouts render the expected components', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard'));

    foreach (['login' => 'auth/Login', 'register' => 'auth/Register', 'password.request' => 'auth/ForgotPassword'] as $route => $component) {
        auth()->logout();
        $this->get(route($route))->assertInertia(fn (Assert $page) => $page->component($component));
    }
});
