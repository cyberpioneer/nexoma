<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.locale' => 'de']);
});

/**
 * Ruft eine Seite ab und erwartet die angegebene aufgeloeste Sprache.
 *
 * @param  array<string, string>  $headers
 */
function expectResolvedLocale(string $expected, array $headers = [], ?User $user = null, array $session = []): void
{
    config(['app.locale' => 'de']);

    // Der Test-Client sendet sonst standardmaessig "Accept-Language: en-us,en;q=0.5".
    $headers += ['Accept-Language' => ''];

    $test = test();

    if ($user !== null) {
        $test->actingAs($user);
    }

    $test->withSession($session)
        ->get($user !== null ? route('dashboard') : route('login'), $headers)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('locale', $expected));

    expect(app()->getLocale())->toBe($expected);
}

test('the configured locales are exactly the six ui languages', function () {
    expect(array_keys(config('app.supported_locales')))->toBe(['de', 'en', 'ru', 'uk', 'es', 'it'])
        ->and(config('app.fallback_locale'))->toBe('en');
});

test('the default locale applies without any hint', function () {
    expectResolvedLocale('de');
});

test('a supported session locale overrides the default and sets the carbon locale', function (string $locale) {
    expectResolvedLocale($locale, session: [SetLocale::SESSION_KEY => $locale]);

    expect(Carbon::getLocale())->toBe($locale);
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);

test('the session locale beats the accept-language header', function () {
    expectResolvedLocale('es', ['Accept-Language' => 'ru'], session: [SetLocale::SESSION_KEY => 'es']);
});

test('the stored user locale beats session and header', function () {
    $user = User::factory()->withLocale('it')->create();

    expectResolvedLocale('it', ['Accept-Language' => 'ru'], $user, [SetLocale::SESSION_KEY => 'es']);
});

test('a user without a stored locale falls back to the session', function () {
    expectResolvedLocale('es', ['Accept-Language' => 'ru'], User::factory()->create(), [SetLocale::SESSION_KEY => 'es']);
});

test('invalid locale values are ignored', function (mixed $sessionValue) {
    expectResolvedLocale('ru', ['Accept-Language' => 'ru'], session: [SetLocale::SESSION_KEY => $sessionValue]);
})->with([
    'unknown code' => 'fr',
    'path traversal' => '../../etc/passwd',
    'uppercase code' => 'DE',
    'array' => [['de']],
    'empty' => '',
]);

test('an invalid stored user locale is ignored', function () {
    $user = User::factory()->withLocale('xx')->create();

    expectResolvedLocale('uk', ['Accept-Language' => 'uk'], $user);
});

test('accept-language is parsed safely', function (string $header, string $expected) {
    expectResolvedLocale($expected, ['Accept-Language' => $header]);
})->with([
    'single language' => ['ru', 'ru'],
    'region is reduced to the language' => ['de-DE', 'de'],
    'underscore region' => ['es_ES', 'es'],
    'case insensitive' => ['UK-ua', 'uk'],
    'quality values decide' => ['de;q=0.4,it;q=0.9,en;q=0.8', 'it'],
    'unsupported languages are skipped' => ['fr-FR,fr;q=0.9,es;q=0.8', 'es'],
    'q zero means not acceptable' => ['ru;q=0,en;q=0.1', 'en'],
    'only unsupported languages' => ['fr,pl;q=0.5', 'de'],
    'wildcard only' => ['*', 'de'],
    'garbage' => ['!!!;;;q=abc,,,<script>', 'de'],
    'very long garbage' => [str_repeat('x-', 3000), 'de'],
]);

test('only the first accept-language entries are evaluated', function () {
    $header = implode(',', array_map(fn (int $i): string => "x{$i}", range(1, 20))).',ru';

    expectResolvedLocale('de', ['Accept-Language' => $header]);
});

test('the html lang attribute follows the locale', function (string $locale) {
    $this->withSession([SetLocale::SESSION_KEY => $locale])
        ->get(route('login'))
        ->assertOk()
        ->assertSee('<html lang="'.$locale.'"', false);
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);
