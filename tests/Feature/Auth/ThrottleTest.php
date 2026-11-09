<?php

use App\Models\User;
use App\Providers\AppServiceProvider;

dataset('throttled auth routes', [
    'login' => ['/login'],
    'register' => ['/register'],
    'forgot password' => ['/forgot-password'],
    'reset password' => ['/reset-password'],
]);

test('the sixth submission with the same email and ip is blocked', function (string $uri) {
    for ($i = 0; $i < AppServiceProvider::AUTH_ATTEMPTS_PER_MINUTE; $i++) {
        expect($this->post($uri, ['email' => 'max@example.com'])->getStatusCode())->not->toBe(429);
    }

    $this->post($uri, ['email' => 'max@example.com'])->assertTooManyRequests();
})->with('throttled auth routes');

test('email variants share the same email and ip counter', function (string $uri) {
    $variants = ['max@example.com', 'MAX@example.com', ' max@example.com ', 'Max@Example.COM', 'max@EXAMPLE.com'];

    foreach ($variants as $variant) {
        expect($this->post($uri, ['email' => $variant])->getStatusCode())->not->toBe(429);
    }

    $this->post($uri, ['email' => 'max@example.com'])->assertTooManyRequests();
})->with('throttled auth routes');

test('the twenty-first submission from the same ip with changing emails is blocked', function (string $uri) {
    for ($i = 0; $i < AppServiceProvider::AUTH_IP_ATTEMPTS_PER_MINUTE; $i++) {
        expect($this->post($uri, ['email' => "user{$i}@example.com"])->getStatusCode())->not->toBe(429);
    }

    $this->post($uri, ['email' => 'another@example.com'])->assertTooManyRequests();
})->with('throttled auth routes');

test('requests without a string email are still limited per ip', function (string $uri) {
    for ($i = 0; $i < AppServiceProvider::AUTH_ATTEMPTS_PER_MINUTE; $i++) {
        expect($this->post($uri, ['email' => ['x']])->getStatusCode())->not->toBe(429);
    }

    $this->post($uri, ['email' => ['x']])->assertTooManyRequests();
})->with('throttled auth routes');

test('another ip is not affected by the limit', function () {
    for ($i = 0; $i < AppServiceProvider::AUTH_ATTEMPTS_PER_MINUTE; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->post('/login', ['email' => 'max@example.com']);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])->post('/login', ['email' => 'max@example.com'])->assertTooManyRequests();
    expect($this->withServerVariables(['REMOTE_ADDR' => '203.0.113.2'])->post('/login', ['email' => 'max@example.com'])->getStatusCode())->not->toBe(429);
});

test('guest pages are not throttled', function (string $uri) {
    for ($i = 0; $i < 25; $i++) {
        $this->get($uri)->assertOk();
    }
})->with(['/login', '/register', '/forgot-password', '/reset-password/token']);

test('verification routes carry the fortify throttle', function () {
    $middleware = collect(['verification.send', 'verification.verify'])
        ->map(fn (string $name) => app('router')->getRoutes()->getByName($name)->gatherMiddleware());

    expect($middleware->every(fn (array $list) => in_array('throttle:6,1', $list, true)))->toBeTrue();
});

test('password confirmation attempts are throttled for logged in users', function () {
    $this->actingAs(User::factory()->create());

    for ($i = 0; $i < AppServiceProvider::AUTH_ATTEMPTS_PER_MINUTE; $i++) {
        expect($this->post(route('password.confirm.store'), ['password' => 'wrong'])->getStatusCode())->not->toBe(429);
    }

    $this->post(route('password.confirm.store'), ['password' => 'wrong'])->assertTooManyRequests();
});
