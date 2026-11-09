<?php

use Inertia\Testing\AssertableInertia as Assert;

test('de and en define the same ui keys', function () {
    $de = require lang_path('de/ui.php');
    $en = require lang_path('en/ui.php');

    expect(array_keys($de))->toEqualCanonicalizing(array_keys($en))
        ->and(array_keys($de))->toContain('auth.login.title', 'auth.verify.link_sent', 'nav.logout', 'dashboard.title', 'common.processing');
});

test('de and en define the framework auth and password keys', function (string $locale) {
    foreach (['failed', 'password', 'throttle'] as $key) {
        expect(trans("auth.{$key}", [], $locale))->not->toBe("auth.{$key}");
    }

    foreach (['reset', 'sent', 'throttled', 'token', 'user'] as $key) {
        expect(trans("passwords.{$key}", [], $locale))->not->toBe("passwords.{$key}");
    }
})->with(['de', 'en']);

test('neutral messages do not reveal whether an account exists', function (string $locale) {
    expect(trans('passwords.throttled', [], $locale))->toBe(trans('passwords.sent', [], $locale))
        ->and(trans('passwords.user', [], $locale))->toBe(trans('passwords.token', [], $locale));
})->with(['de', 'en']);

test('translations are shared per locale and differ between de and en', function () {
    $titles = [];

    foreach (['de', 'en'] as $locale) {
        app()->setLocale($locale);

        $this->get(route('login'))->assertInertia(function (Assert $page) use ($locale, &$titles) {
            $page->where('locale', $locale)
                ->where('translations', function ($translations) use (&$titles, $locale): bool {
                    $titles[$locale] = collect($translations)->get('auth.login.title');

                    return is_string($titles[$locale]);
                });
        });
    }

    expect($titles['de'])->not->toBe($titles['en']);
});

test('every translation key used in the vue files exists in de and en', function () {
    $files = array_merge(
        glob(resource_path('js/{pages,layouts,components}/{,auth/}*.vue'), GLOB_BRACE) ?: [],
    );
    $used = [];

    foreach ($files as $file) {
        preg_match_all("/\bt\('([A-Za-z0-9_.-]+)'/", (string) file_get_contents($file), $matches);
        $used = [...$used, ...$matches[1]];
    }

    $de = array_keys(require lang_path('de/ui.php'));
    $en = array_keys(require lang_path('en/ui.php'));

    expect(array_unique($used))->not->toBeEmpty()
        ->and(array_diff($used, $de))->toBeEmpty()
        ->and(array_diff($used, $en))->toBeEmpty();
});
