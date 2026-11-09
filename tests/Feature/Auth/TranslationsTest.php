<?php

use Inertia\Testing\AssertableInertia as Assert;

const SUPPORTED_LOCALES = ['de', 'en', 'ru', 'uk', 'es', 'it'];

test('all supported locales define the same ui keys', function () {
    $de = require lang_path('de/ui.php');

    foreach (SUPPORTED_LOCALES as $locale) {
        expect(array_keys(require lang_path("{$locale}/ui.php")))->toEqualCanonicalizing(array_keys($de), $locale);
    }

    expect(array_keys($de))->toContain('auth.login.title', 'auth.verify.link_sent', 'nav.logout', 'dashboard.title', 'common.processing');
});

test('all supported locales define the framework auth and password keys', function (string $locale) {
    foreach (['failed', 'password', 'throttle'] as $key) {
        expect(trans("auth.{$key}", [], $locale))->not->toBe("auth.{$key}");
    }

    foreach (['reset', 'sent', 'throttled', 'token', 'user'] as $key) {
        expect(trans("passwords.{$key}", [], $locale))->not->toBe("passwords.{$key}");
    }
})->with(SUPPORTED_LOCALES);

test('neutral messages do not reveal whether an account exists', function (string $locale) {
    expect(trans('passwords.throttled', [], $locale))->toBe(trans('passwords.sent', [], $locale))
        ->and(trans('passwords.user', [], $locale))->toBe(trans('passwords.token', [], $locale));
})->with(SUPPORTED_LOCALES);

test('translations are shared per locale and differ between the locales', function () {
    $titles = [];

    foreach (SUPPORTED_LOCALES as $locale) {
        $this->withSession(['locale' => $locale])->get(route('login'))->assertInertia(function (Assert $page) use ($locale, &$titles) {
            $page->where('locale', $locale)
                ->where('translations', function ($translations) use (&$titles, $locale): bool {
                    $titles[$locale] = collect($translations)->get('auth.login.title');

                    return is_string($titles[$locale]);
                });
        });
    }

    expect(array_unique($titles))->toHaveCount(count(SUPPORTED_LOCALES));
});

test('every translation key used in the vue files exists in every supported locale', function () {
    $files = array_merge(
        glob(resource_path('js/{pages,layouts,components}/{,auth/}*.vue'), GLOB_BRACE) ?: [],
    );
    $used = [];

    foreach ($files as $file) {
        preg_match_all("/\bt\('([A-Za-z0-9_.-]+)'/", (string) file_get_contents($file), $matches);
        $used = [...$used, ...$matches[1]];
    }

    expect(array_unique($used))->not->toBeEmpty();

    foreach (SUPPORTED_LOCALES as $locale) {
        $defined = array_keys(require lang_path("{$locale}/ui.php"));

        expect(array_diff($used, $defined))->toBeEmpty($locale);
    }
});
