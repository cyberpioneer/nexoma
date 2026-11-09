<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('all ui languages are offered with their native names and nothing else', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->has('availableLocales', 6)
        ->where('availableLocales', [
            ['code' => 'de', 'name' => 'Deutsch'],
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'ru', 'name' => 'Русский'],
            ['code' => 'uk', 'name' => 'Українська'],
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'it', 'name' => 'Italiano'],
        ]));
});

test('the locale props expose no user data', function () {
    $user = User::factory()->withLocale('ru')->create();

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('locale', 'ru')
        ->missing('auth.user.locale')
        ->has('auth.user', 4));
});

test('only the ui translation group is shared', function () {
    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->where('translations', fn ($translations): bool => collect($translations)->keys()->every(
            fn (string $key): bool => array_key_exists($key, trans('ui')),
        )));
});
