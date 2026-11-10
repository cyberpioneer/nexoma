<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

beforeEach(function () {
    // Produktionsregeln (in anderen Umgebungen gelten nur die Standardregeln); die Pruefung auf bekannte Leaks
    // (Have I Been Pwned) wird ohne echten Netzwerkzugriff beantwortet.
    $this->app->detectEnvironment(fn (): string => 'production');
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

function passesPasswordPolicy(string $password): bool
{
    return Validator::make(['password' => $password], ['password' => [Password::default()]])->passes();
}

test('production policy accepts a password with exactly 8 characters', function () {
    expect(passesPasswordPolicy('Ab3$efgh'))->toBeTrue();
});

test('production policy rejects a password with 7 characters', function () {
    expect(passesPasswordPolicy('Ab3$efg'))->toBeFalse();
});

test('production policy still requires mixed case, numbers and symbols', function () {
    expect(passesPasswordPolicy('abcdefgh'))->toBeFalse()
        ->and(passesPasswordPolicy('Abcdefgh'))->toBeFalse()
        ->and(passesPasswordPolicy('Abcdefg1'))->toBeFalse();
});
