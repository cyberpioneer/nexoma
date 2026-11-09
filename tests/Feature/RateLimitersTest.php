<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    foreach (['jobsuche', 'jobdetails', 'auth', 'mcp'] as $name) {
        Route::middleware('throttle:'.$name)->any('/_test/'.$name, fn () => 'ok');
    }
});

test('named limiters are registered', function (string $name) {
    expect(RateLimiter::limiter($name))->not->toBeNull();
})->with(['jobsuche', 'jobdetails', 'auth', 'mcp']);

test('limiters block with 429 after the limit', function (string $name, int $limit) {
    for ($i = 0; $i < $limit; $i++) {
        $this->postJson('/_test/'.$name, ['email' => 'a@example.test'])->assertOk();
    }

    $this->postJson('/_test/'.$name, ['email' => 'a@example.test'])->assertTooManyRequests();
})->with([
    ['jobsuche', AppServiceProvider::JOBSUCHE_ATTEMPTS_PER_MINUTE],
    ['jobdetails', AppServiceProvider::JOBDETAILS_ATTEMPTS_PER_MINUTE],
    ['auth', AppServiceProvider::AUTH_ATTEMPTS_PER_MINUTE],
    ['mcp', AppServiceProvider::MCP_ATTEMPTS_PER_MINUTE],
]);

test('limit values match the specification', function () {
    expect(AppServiceProvider::JOBSUCHE_ATTEMPTS_PER_MINUTE)->toBe(30)
        ->and(AppServiceProvider::JOBDETAILS_ATTEMPTS_PER_MINUTE)->toBe(60)
        ->and(AppServiceProvider::AUTH_ATTEMPTS_PER_MINUTE)->toBe(5)
        ->and(AppServiceProvider::AUTH_IP_ATTEMPTS_PER_MINUTE)->toBe(20)
        ->and(AppServiceProvider::MCP_ATTEMPTS_PER_MINUTE)->toBe(60);
});

test('jobsuche and mcp limit per user, not shared between users', function (string $name, int $limit) {
    [$first, $second] = User::factory()->count(2)->create()->all();

    for ($i = 0; $i < $limit; $i++) {
        $this->actingAs($first)->getJson('/_test/'.$name)->assertOk();
    }

    $this->actingAs($first)->getJson('/_test/'.$name)->assertTooManyRequests();
    $this->actingAs($second)->getJson('/_test/'.$name)->assertOk();
})->with([
    ['jobsuche', AppServiceProvider::JOBSUCHE_ATTEMPTS_PER_MINUTE],
    ['mcp', AppServiceProvider::MCP_ATTEMPTS_PER_MINUTE],
]);

test('guests are limited per ip address', function () {
    for ($i = 0; $i < AppServiceProvider::JOBSUCHE_ATTEMPTS_PER_MINUTE; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->getJson('/_test/jobsuche')->assertOk();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->getJson('/_test/jobsuche')->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->getJson('/_test/jobsuche')->assertOk();
});

test('auth limiter is keyed by email and ip, case-insensitive', function () {
    for ($i = 0; $i < AppServiceProvider::AUTH_ATTEMPTS_PER_MINUTE; $i++) {
        $this->postJson('/_test/auth', ['email' => $i % 2 === 0 ? 'A@Example.test' : 'a@example.test'])->assertOk();
    }

    $this->postJson('/_test/auth', ['email' => 'a@example.test'])->assertTooManyRequests();
    $this->postJson('/_test/auth', ['email' => 'other@example.test'])->assertOk();
    $this->postJson('/_test/auth', ['email' => ['x']])->assertOk();
});

test('auth limiter also limits per ip regardless of the email', function () {
    for ($i = 0; $i < AppServiceProvider::AUTH_IP_ATTEMPTS_PER_MINUTE; $i++) {
        $this->postJson('/_test/auth', ['email' => "user{$i}@example.test"])->assertOk();
    }

    $this->postJson('/_test/auth', ['email' => 'other@example.test'])->assertTooManyRequests();
});

test('auth limiter returns an email and an ip limit with distinct key prefixes', function () {
    $request = Request::create('/x', 'POST', ['email' => 'A@Example.test'], server: ['REMOTE_ADDR' => '203.0.113.5']);

    $limits = Arr::wrap(RateLimiter::limiter('auth')($request));

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->key)->toStartWith('email:')
        ->and($limits[1]->key)->toBe('ip:203.0.113.5');
});
