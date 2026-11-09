<?php

use App\Services\OAuth\RedirectUriDisplay;

test('redirect uris are reduced to scheme, host and non-default port', function (mixed $uri, string $host, bool $punycode) {
    expect(RedirectUriDisplay::describe($uri))->toBe(['host' => $host, 'punycode' => $punycode]);
})->with([
    'https' => ['https://Claude.AI/api/cb?x=1', 'https://claude.ai', false],
    'default port' => ['https://claude.ai:443/cb', 'https://claude.ai', false],
    'http with port' => ['http://localhost:8080/cb', 'http://localhost:8080', false],
    'userinfo is dropped' => ['https://claude.ai@evil.example/cb', 'https://evil.example', false],
    'punycode' => ['https://xn--clude-6qa.ai/cb', 'https://xn--clude-6qa.ai', true],
    'rohes unicode' => ['https://clаude.ai/cb', 'https://clаude.ai', true],
    'punycode subdomain' => ['https://xn--abc.example.com/cb', 'https://xn--abc.example.com', true],
    'scheme without host' => ['app://callback', 'app://callback', false],
    'scheme only' => ['myapp:/cb', 'myapp://', false],
    'not a string' => [null, '', false],
    'garbage' => ['/relative/path', '', false],
]);

test('all redirect uris are described without duplicates', function () {
    expect(RedirectUriDisplay::describeAll(['https://a.example/1', 'https://a.example/2', 'http://b.example:81/x']))
        ->toBe([
            ['host' => 'https://a.example', 'punycode' => false],
            ['host' => 'http://b.example:81', 'punycode' => false],
        ]);
});
