<?php

use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;

const TEST_API_KEY = 'secret-test-key-123';
const SEARCH_URL = 'https://jobsuche.test/pc/v6/jobs*';

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('services.jobsuche.api_key', TEST_API_KEY);
    config()->set('services.jobsuche.timeout', 7);
    config()->set('services.jobsuche.connect_timeout', 3);

    Http::preventStrayRequests();
    Sleep::fake();
    Cache::flush();
});

function searchPayload(): array
{
    return [
        'ergebnisliste' => [['referenznummer' => '10001-1-S', 'stellenangebotsTitel' => 'Pflege']],
        'maxErgebnisse' => 1,
        'page' => 1,
        'size' => 100,
    ];
}

/**
 * Prueft, dass der API-Key in keinem Logeintrag vorkommt.
 */
function spyOnLogsWithoutKey(): void
{
    Log::shouldReceive('warning', 'error')
        ->zeroOrMoreTimes()
        ->withArgs(function (...$args): bool {
            expect(json_encode($args))->not->toContain(TEST_API_KEY);

            return true;
        });
}

it('sends a search with headers, path and default parameters', function () {
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);

    $result = app(JobsucheApiClient::class)->search('Pflege');

    expect($result['ergebnisliste'])->toHaveCount(1);

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://jobsuche.test/pc/v6/jobs?')
            && $request->header('X-API-Key') === [TEST_API_KEY]
            && $request->header('Accept') === ['application/json']
            && $query === ['was' => 'Pflege', 'page' => '1', 'size' => '100', 'veroeffentlichtseit' => '100'];
    });
});

it('sends optional and extra parameters only when set', function () {
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);

    app(JobsucheApiClient::class)->search(
        was: 'Pflege',
        page: 2,
        size: 10,
        veroeffentlichtseit: 7,
        wo: 'Köln',
        umkreis: 20,
        angebotsart: 4,
        extra: ['zeitarbeit' => false, 'arbeitgeber' => null, 'arbeitszeit' => 'vz;tz'],
    );

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query === [
            'zeitarbeit' => 'false',
            'arbeitszeit' => 'vz;tz',
            'was' => 'Pflege',
            'page' => '2',
            'size' => '10',
            'veroeffentlichtseit' => '7',
            'wo' => 'Köln',
            'umkreis' => '20',
            'angebotsart' => '4',
        ];
    });
});

it('accepts all allowed offer types', function (int $type) {
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);

    expect(app(JobsucheApiClient::class)->search('x', angebotsart: $type))->toBeArray();
})->with([1, 2, 4, 34]);

it('rejects an invalid offer type without sending a request', function () {
    Http::fake();

    expect(fn () => app(JobsucheApiClient::class)->search('x', angebotsart: 3))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
});

it('returns an empty list when the api reports no hits', function () {
    Http::fake([SEARCH_URL => Http::response(['maxErgebnisse' => 0, 'page' => 1, 'size' => 25])]);

    $result = app(JobsucheApiClient::class)->search('zzz');

    expect($result)->not->toBeNull()
        ->and($result['ergebnisliste'])->toBe([]);
});

it('retries exactly once after 500 ms on http 429 and succeeds', function () {
    Http::fake([SEARCH_URL => Http::sequence()->push('', 429)->push(searchPayload())]);

    $result = app(JobsucheApiClient::class)->search('Pflege');

    expect($result['ergebnisliste'])->toHaveCount(1);
    Http::assertSentCount(2);
    Sleep::assertSleptTimes(1);
    Sleep::assertSequence([Sleep::usleep(500_000)]);
});

it('returns null after a permanent 429 with exactly one retry', function () {
    Http::fake([SEARCH_URL => Http::response('', 429)]);
    spyOnLogsWithoutKey();

    expect(app(JobsucheApiClient::class)->search('Pflege'))->toBeNull();

    Http::assertSentCount(2);
});

it('returns null on server errors without retrying', function () {
    Http::fake([SEARCH_URL => Http::response('boom', 503)]);
    spyOnLogsWithoutKey();

    expect(app(JobsucheApiClient::class)->search('Pflege'))->toBeNull();

    Http::assertSentCount(1);
});

it('returns null on connection failures and does not leak the key into logs', function () {
    Http::fake([SEARCH_URL => fn () => throw new ConnectionException('cURL error 28: timeout with '.TEST_API_KEY)]);
    spyOnLogsWithoutKey();

    expect(app(JobsucheApiClient::class)->search('Pflege'))->toBeNull();
});

it('returns null for invalid json', function () {
    Http::fake([SEARCH_URL => Http::response('<html>nope</html>', 200)]);
    spyOnLogsWithoutKey();

    expect(app(JobsucheApiClient::class)->search('Pflege'))->toBeNull();
});

it('returns null when the result structure is missing or invalid', function (array $body) {
    Http::fake([SEARCH_URL => Http::response($body)]);
    spyOnLogsWithoutKey();

    expect(app(JobsucheApiClient::class)->search('Pflege'))->toBeNull();
})->with([
    'unexpected object' => [['foo' => 'bar']],
    'list is not an array' => [['ergebnisliste' => 'x']],
]);

it('logs failures without the api key', function () {
    Http::fake([SEARCH_URL => Http::response('boom', 500)]);

    Log::spy();

    app(JobsucheApiClient::class)->search('Pflege');

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context): bool => $context['status'] === 500
            && ! str_contains(json_encode([$message, $context]), TEST_API_KEY),
    );
});

it('fetches details with a base64 encoded path and caches the result for six hours', function () {
    Http::fake(['https://jobsuche.test/pc/v4/jobdetails/*' => Http::response(['stellenangebotsTitel' => 'Pflege'])]);
    $client = app(JobsucheApiClient::class);

    expect($client->details('10001-1002716922-S'))->toBe(['stellenangebotsTitel' => 'Pflege'])
        ->and($client->details('10001-1002716922-S'))->toBe(['stellenangebotsTitel' => 'Pflege'])
        ->and(Cache::has('jobsuche.details.10001-1002716922-S'))->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->url() === 'https://jobsuche.test/pc/v4/jobdetails/MTAwMDEtMTAwMjcxNjkyMi1T'
        && $request->header('X-API-Key') === [TEST_API_KEY]);
});

it('does not cache failed detail requests', function () {
    Http::fake(['https://jobsuche.test/pc/v4/jobdetails/*' => Http::sequence()
        ->push(['messages' => []], 404)
        ->push('garbage', 200)
        ->push([], 200)
        ->push(['messages' => [['code' => 'FEHLER']]], 200)
        ->push(['stellenangebotsTitel' => 'Pflege'])]);
    $client = app(JobsucheApiClient::class);

    expect($client->details('10001-1-S'))->toBeNull()
        ->and($client->details('10001-1-S'))->toBeNull()
        ->and($client->details('10001-1-S'))->toBeNull()
        ->and($client->details('10001-1-S'))->toBeNull()
        ->and(Cache::has('jobsuche.details.10001-1-S'))->toBeFalse()
        ->and($client->details('10001-1-S'))->toBe(['stellenangebotsTitel' => 'Pflege']);

    Http::assertSentCount(5);
});

it('rejects malformed reference numbers without a request', function (string $refnr) {
    Http::fake();

    expect(app(JobsucheApiClient::class)->details($refnr))->toBeNull();

    Http::assertNothingSent();
})->with(['empty' => '', 'slash' => 'a/../b', 'space' => 'a b', 'too long' => str_repeat('a', 101), 'trailing newline' => "abc\n"]);

it('does not leak the key into logs on detail failures', function (callable $fake) {
    Http::fake(['https://jobsuche.test/pc/v4/jobdetails/*' => $fake]);
    spyOnLogsWithoutKey();

    expect(app(JobsucheApiClient::class)->details('10001-1-S'))->toBeNull();
})->with([
    'connection' => [fn () => throw new ConnectionException('failed '.TEST_API_KEY)],
    'status 500' => [fn () => Http::response('x '.TEST_API_KEY, 500)],
    'no expected field' => [fn () => Http::response(['messages' => []], 200)],
]);

it('rejects out of range search arguments without a request', function (array $arguments) {
    Http::fake();

    expect(fn () => app(JobsucheApiClient::class)->search(...$arguments))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'page 0' => [['was' => 'x', 'page' => 0]],
    'size 0' => [['was' => 'x', 'size' => 0]],
    'size 101' => [['was' => 'x', 'size' => 101]],
    'days -1' => [['was' => 'x', 'veroeffentlichtseit' => -1]],
    'days 101' => [['was' => 'x', 'veroeffentlichtseit' => 101]],
    'radius -1' => [['was' => 'x', 'umkreis' => -1]],
    'radius 201' => [['was' => 'x', 'umkreis' => 201]],
    'was too long' => [['was' => str_repeat('a', 256)]],
    'wo too long' => [['was' => 'x', 'wo' => str_repeat('a', 256)]],
]);

it('accepts boundary search arguments', function () {
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);

    expect(app(JobsucheApiClient::class)->search(str_repeat('a', 255), 1, 100, 0, str_repeat('b', 255), 0))->toBeArray()
        ->and(app(JobsucheApiClient::class)->search('x', 1, 1, 100, null, 200))->toBeArray();
});

it('rejects unknown or invalid extra parameters', function (array $extra) {
    Http::fake();

    expect(fn () => app(JobsucheApiClient::class)->search('x', extra: $extra))
        ->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'unknown key' => [['foo' => 'bar']],
    'named parameter as extra' => [['was' => 'y']],
    'array value' => [['berufsfeld' => ['a', 'b']]],
    'object value' => [['arbeitgeber' => new stdClass]],
    'string for bool' => [['zeitarbeit' => 'true']],
    'befristung 3' => [['befristung' => 3]],
    'arbeitszeit unknown' => [['arbeitszeit' => 'vz;xx']],
    'arbeitszeit newline' => [['arbeitszeit' => "vz\n"]],
]);

it('accepts documented extra parameters', function () {
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);

    $result = app(JobsucheApiClient::class)->search('x', extra: [
        'berufsfeld' => 'Informatik', 'arbeitgeber' => 'Deutsche Bahn AG', 'zeitarbeit' => true,
        'behinderung' => true, 'corona' => false, 'befristung' => 2, 'arbeitszeit' => 'vz;tz;snw;ho;mj',
    ]);

    expect($result)->toBeArray();
});

it('refuses plain http outside local and testing without sending a request', function () {
    config()->set('services.jobsuche.base_url', 'http://jobsuche.test');
    app()->detectEnvironment(fn () => 'production');
    Http::fake();
    spyOnLogsWithoutKey();

    expect(app(JobsucheApiClient::class)->search('x'))->toBeNull()
        ->and(app(JobsucheApiClient::class)->details('10001-1-S'))->toBeNull();

    Http::assertNothingSent();
});

it('allows plain http in the testing environment', function () {
    config()->set('services.jobsuche.base_url', 'http://jobsuche.test');
    Http::fake(['http://jobsuche.test/*' => Http::response(searchPayload())]);

    expect(app(JobsucheApiClient::class)->search('x'))->toBeArray();
});

it('refuses an empty or non-http base url', function (?string $url) {
    config()->set('services.jobsuche.base_url', $url);
    Http::fake();

    expect(app(JobsucheApiClient::class)->search('x'))->toBeNull();

    Http::assertNothingSent();
})->with([null, '', 'ftp://jobsuche.test']);

it('uses the official base url and public key as defaults', function () {
    $config = require base_path('config/services.php');

    expect($config['jobsuche']['base_url'])->toBe('https://rest.arbeitsagentur.de/jobboerse/jobsuche-service')
        ->and($config['jobsuche']['api_key'])->toBe('jobboerse-jobsuche');
});

test('global quota stops outgoing calls after the limit and returns null without logging parameters', function () {
    config()->set('services.jobsuche.global_limit_per_minute', 2);
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);
    Log::spy();

    $client = new JobsucheApiClient;

    expect($client->search('Pflege'))->not->toBeNull()
        ->and($client->search('Pflege'))->not->toBeNull()
        ->and($client->search('Geheim-Suchbegriff'))->toBeNull();

    Http::assertSentCount(2);
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context = []): bool => str_contains($message, 'Kontingent')
            && $context === ['operation' => 'search'],
    )->once();
});

test('global quota does not affect cache hits for details', function () {
    config()->set('services.jobsuche.global_limit_per_minute', 1);
    Http::fake(['https://jobsuche.test/pc/v4/jobdetails/*' => Http::response(['referenznummer' => '10001-1-S', 'stellenangebotsTitel' => 'Pflege'])]);

    $client = new JobsucheApiClient;

    expect($client->details('10001-1-S'))->not->toBeNull();
    expect($client->details('10001-1-S'))->not->toBeNull()
        ->and($client->details('10001-1-S'))->not->toBeNull();
    expect($client->details('10002-2-S'))->toBeNull();

    Http::assertSentCount(1);
});

test('global quota is shared across calls and recovers after the window', function () {
    config()->set('services.jobsuche.global_limit_per_minute', 1);
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);

    $client = new JobsucheApiClient;

    expect($client->search('a'))->not->toBeNull()
        ->and($client->search('b'))->toBeNull();

    $this->travel(61)->seconds();

    expect($client->search('c'))->not->toBeNull();
    Http::assertSentCount(2);
});

test('an invalid global limit falls back to the default instead of disabling or blocking', function () {
    config()->set('services.jobsuche.global_limit_per_minute', 0);
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);

    expect((new JobsucheApiClient)->search('a'))->not->toBeNull();
});

test('a failing cache store makes the client return null instead of throwing', function () {
    Http::fake([SEARCH_URL => Http::response(searchPayload())]);
    RateLimiter::shouldReceive('attempt')->andThrow(new RuntimeException('cache down'));
    Log::spy();

    expect((new JobsucheApiClient)->search('a'))->toBeNull();
    Http::assertNothingSent();
});
