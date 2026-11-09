<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Route::middleware('web')->get('/_test/info', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
        'host' => $request->getHost(),
    ]));
    Route::middleware('throttle:1,1')->get('/_test/throttled', fn () => 'ok');
});

function assertSecurityHeaders(TestResponse $response): void
{
    $response->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Permissions-Policy', SecurityHeaders::PERMISSIONS_POLICY);
}

function useProduction(): void
{
    config(['app.url' => 'https://nexoma.example']);
    app()->detectEnvironment(fn (): string => 'production');
}

test('security headers are present on web, oauth and mcp responses of every status', function () {
    assertSecurityHeaders($this->get('/'));                                   // 200 HTML
    assertSecurityHeaders($this->get('/login'));                              // 200 HTML
    assertSecurityHeaders($this->get('/dashboard'));                          // 302 Gast
    assertSecurityHeaders($this->get('/does-not-exist'));                     // 404
    assertSecurityHeaders($this->getJson('/dashboard'));                      // 401 JSON
    assertSecurityHeaders($this->postJson('/mcp', ['jsonrpc' => '2.0']));     // 401 JSON
    assertSecurityHeaders($this->getJson('/mcp'));                            // 405
    assertSecurityHeaders($this->postJson('/oauth/token', []));               // 4xx JSON
    assertSecurityHeaders($this->getJson('/.well-known/oauth-authorization-server'));
    assertSecurityHeaders($this->get('/oauth/authorize'));                    // Weiterleitung/Abweisung

    $this->get('/_test/throttled')->assertOk();
    assertSecurityHeaders($this->get('/_test/throttled')->assertTooManyRequests());
});

test('security headers also cover a 403 response', function () {
    Route::middleware('web')->get('/_test/forbidden', fn () => abort(403));

    assertSecurityHeaders($this->get('/_test/forbidden')->assertForbidden());
});

test('existing framing headers from other packages are not overwritten', function () {
    Route::middleware('web')->get('/_test/framing', fn () => response('x')->withHeaders([
        'Referrer-Policy' => 'same-origin',
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
    ]));

    $this->get('/_test/framing')
        ->assertHeader('Referrer-Policy', 'same-origin')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'");
});

test('hsts is sent only in production over https', function () {
    $this->get('/_test/info')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://nexoma.example/_test/info')->assertHeaderMissing('Strict-Transport-Security');

    useProduction();

    $this->get('http://nexoma.example/_test/info')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://nexoma.example/_test/info')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

test('without TRUSTED_PROXIES forwarded headers are ignored', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.test'])
        ->getJson('/_test/info')
        ->assertOk()
        ->assertJson(['ip' => '10.0.0.5', 'secure' => false, 'host' => 'localhost']);
});

test('with TRUSTED_PROXIES the client ip and scheme are read from a listed proxy', function () {
    config(['app.trusted_proxies' => '10.0.0.0/8, 192.168.1.1']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
        ->getJson('/_test/info')
        ->assertJson(['ip' => '203.0.113.9', 'secure' => true]);
});

test('with TRUSTED_PROXIES forwarded headers from an unlisted sender are ignored', function () {
    config(['app.trusted_proxies' => '10.0.0.0/8, 192.168.1.1']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
        ->getJson('/_test/info')
        ->assertJson(['ip' => '198.51.100.7', 'secure' => false]);
});

test('a wildcard is only honoured as the explicit value', function () {
    $headers = ['X-Forwarded-For' => '203.0.113.9'];

    foreach (['**', 'REMOTE_ADDR', '   ', ','] as $value) {
        config(['app.trusted_proxies' => $value]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->withHeaders($headers)->getJson('/_test/info')
            ->assertJson(['ip' => '198.51.100.7']);
    }

    config(['app.trusted_proxies' => '*']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->withHeaders($headers)->getJson('/_test/info')
        ->assertJson(['ip' => '203.0.113.9']);
});

test('an untrusted forwarded ip cannot dodge the ip based limiter', function () {
    foreach (['203.0.113.1', '203.0.113.2', '203.0.113.3'] as $spoofed) {
        $response = $this->withHeaders(['X-Forwarded-For' => $spoofed])->getJson('/_test/throttled');
    }

    $response->assertTooManyRequests();
});

test('in production a foreign host header is rejected and the app url host is accepted', function () {
    useProduction();

    assertSecurityHeaders($this->get('https://evil.test/_test/info')->assertStatus(400));
    $this->get('https://nexoma.example/_test/info')->assertOk();
});

test('trusted hosts can be listed explicitly and then replace the app url host', function () {
    useProduction();
    config(['app.trusted_hosts' => ['app.nexoma.example']]);

    $this->get('https://app.nexoma.example/_test/info')->assertOk();
    Request::setTrustedHosts([]);
    $this->get('https://nexoma.example/_test/info')->assertStatus(400);
});

test('host checks are inactive outside production', function () {
    config(['app.url' => 'https://nexoma.example']);

    $this->get('http://other.test/_test/info')->assertOk()->assertJson(['host' => 'other.test']);
});

test('session cookie settings are safe by default', function () {
    $session = require config_path('session.php');

    expect($session['http_only'])->toBeTrue()
        ->and($session['same_site'])->toBeIn(['lax', 'strict']);
});

test('session cookie is secure by default in production', function () {
    $previous = $_ENV['APP_ENV'] ?? null;
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'production';
    putenv('APP_ENV=production');

    try {
        $session = require config_path('session.php');
    } finally {
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $previous;
        putenv('APP_ENV='.$previous);
    }

    expect($session['secure'])->toBeTrue();
});

test('a forge host is not trusted without TRUSTED_PROXIES', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
        ->getJson('http://x.on-forge.com/_test/info')
        ->assertOk()
        ->assertJson(['ip' => '10.0.0.5', 'secure' => false]);
});

test('in production an app url without host and empty trusted hosts rejects every host', function () {
    useProduction();
    config(['app.url' => 'not a url', 'app.trusted_hosts' => []]);

    assertSecurityHeaders($this->get('https://nexoma.example/_test/info')->assertStatus(400));
});
