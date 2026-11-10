<?php

use Inertia\Testing\AssertableInertia as Assert;

/**
 * Alle Platzhalter (:name, :seconds, ...) eines Textes.
 *
 * @return list<string>
 */
function placeholdersOf(string $text): array
{
    preg_match_all('/:[a-z_]+/', $text, $matches);

    return array_values(array_unique($matches[0]));
}

/**
 * Flacht verschachtelte Uebersetzungsarrays zu "a.b.c" => Text ab.
 *
 * @param  array<string, mixed>  $messages
 * @return array<string, string>
 */
function flattenMessages(array $messages, string $prefix = ''): array
{
    $flat = [];

    foreach ($messages as $key => $value) {
        is_array($value)
            ? $flat += flattenMessages($value, $prefix.$key.'.')
            : $flat[$prefix.$key] = $value;
    }

    return $flat;
}

dataset('locales', ['de', 'en', 'ru', 'uk', 'es', 'it']);
dataset('translation files', ['ui', 'auth', 'passwords', 'validation']);

test('every locale defines the same keys and placeholders as de in every file', function (string $file) {
    $reference = flattenMessages(require lang_path("de/{$file}.php"));

    foreach (['de', 'en', 'ru', 'uk', 'es', 'it'] as $locale) {
        $messages = flattenMessages(require lang_path("{$locale}/{$file}.php"));

        expect(array_keys($messages))->toEqualCanonicalizing(array_keys($reference), "{$locale}/{$file}.php");

        foreach ($messages as $key => $text) {
            expect($text)->toBeString()->not->toBe('', "{$locale}/{$file}.php {$key}");
            expect(placeholdersOf($text))->toEqualCanonicalizing(placeholdersOf($reference[$key]), "placeholders of {$locale}/{$file}.php {$key}");
        }
    }
})->with('translation files');

test('the attribute names cover all fields used in this project', function (string $locale) {
    $attributes = (require lang_path("{$locale}/validation.php"))['attributes'];

    expect($attributes)->toHaveKeys([
        'name', 'email', 'password', 'password_confirmation', 'was', 'wo', 'umkreis', 'angebotsart', 'page', 'size',
        'veroeffentlichtseit', 'berufsfeld', 'arbeitgeber', 'zeitarbeit', 'befristung', 'arbeitszeit', 'locale', 'note', 'consent',
    ]);
})->with('locales');

test('the mail json files share the same keys and placeholders', function () {
    $reference = json_decode((string) file_get_contents(lang_path('de.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach (['ru', 'uk', 'es', 'it'] as $locale) {
        $messages = json_decode((string) file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);

        expect(array_keys($messages))->toEqualCanonicalizing(array_keys($reference));

        foreach ($messages as $key => $text) {
            expect(placeholdersOf($text))->toEqualCanonicalizing(placeholdersOf($key), "{$locale}.json {$key}");
        }
    }
});

test('validation messages and attributes are translated', function (string $locale) {
    app()->setLocale($locale);

    $validator = validator(['email' => 'x'], ['email' => ['email'], 'name' => ['required']]);
    $validator->fails();
    $messages = $validator->errors()->all();

    expect($messages)->toHaveCount(2);

    foreach ($messages as $message) {
        expect($message)->not->toContain('validation.')->not->toContain(':attribute');
    }

    $attributes = trans('validation.attributes', [], $locale);
    expect(implode(' ', $messages))->toContain($attributes['email'])->toContain($attributes['name']);
})->with('locales');

test('pages are rendered without missing translation keys in every locale', function (string $locale) {
    $routes = ['login', 'register', 'password.request'];

    foreach ($routes as $routeName) {
        $this->withSession(['locale' => $locale])
            ->get(route($routeName))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', $locale)
                ->where('translations', function ($translations): bool {
                    foreach ($translations as $key => $text) {
                        if ($text === $key || $text === '') {
                            return false;
                        }
                    }

                    return count($translations) > 0;
                }));
    }
})->with('locales');
