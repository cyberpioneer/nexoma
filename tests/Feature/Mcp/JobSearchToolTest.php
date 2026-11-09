<?php

use App\Mcp\Servers\NexomaServer;
use App\Mcp\Tools\JobSearchTool;
use App\Models\User;
use App\Services\Jobsuche\JobDataMapper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const MCP_SEARCH_URL = 'https://jobsuche.test/pc/v6/jobs*';

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('services.jobsuche.api_key', 'secret-test-key-123');
    Http::preventStrayRequests();
    $this->user = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function mcpApiSearchResponse(int $total = 1): array
{
    return [
        'ergebnisliste' => [[
            'stellenangebotsart' => 'AUSBILDUNG',
            'stellenangebotsTitel' => 'Pflegefachkraft (m/w/d)',
            'hauptberuf' => 'Altenpflege',
            'firma' => 'Muster Pflege GmbH',
            'referenznummer' => '10001-1234567890-S',
            'entfernung' => 4,
            'veroeffentlichungszeitraum' => ['von' => '2026-11-02'],
            'eintrittszeitraum' => ['von' => '2026-12-01'],
            'stellenlokationen' => [['adresse' => ['plz' => '50667', 'ort' => 'Koeln', 'strasse' => 'Geheimweg 1']]],
            'internesFeld' => 'darf-nicht-im-client-landen',
        ]],
        'maxErgebnisse' => $total,
        'facetten' => [['berufsfeld' => ['counts' => []]]],
    ];
}

test('a search returns the notice and only whitelisted fields', function () {
    Http::fake([MCP_SEARCH_URL => Http::response(mcpApiSearchResponse(1))]);

    $response = NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Pflege', 'wo' => 'Köln', 'umkreis' => 20, 'angebotsart' => 4]);

    $response->assertOk()->assertStructuredContent([
        'notice' => JobSearchTool::NOTICE,
        'total' => 1,
        'page' => 1,
        'pageSize' => 10,
        'hasMore' => false,
        'jobs' => [[
            'refnr' => '10001-1234567890-S',
            'title' => 'Pflegefachkraft (m/w/d)',
            'profession' => 'Altenpflege',
            'employer' => 'Muster Pflege GmbH',
            'location' => '50667 Koeln',
            'distance' => 4,
            'publishedAt' => '2026-11-02',
            'startDate' => '2026-12-01',
            'offerType' => 'AUSBILDUNG',
            'nexomaUrl' => route('jobs.show', ['refnr' => '10001-1234567890-S']),
        ]],
    ]);
    $response->assertDontSee(['darf-nicht-im-client-landen', 'Geheimweg', 'facetten', 'secret-test-key-123']);
    Http::assertSent(fn (Request $request): bool => $request['was'] === 'Pflege' && $request['wo'] === 'Köln'
        && (string) $request['umkreis'] === '20' && (string) $request['angebotsart'] === '4' && (string) $request['size'] === '10');
});

test('the page size defaults to 10 and is capped at 25', function () {
    Http::fake([MCP_SEARCH_URL => Http::response(mcpApiSearchResponse(1))]);

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Pflege', 'size' => 25])->assertOk();
    Http::assertSent(fn (Request $request): bool => (string) $request['size'] === '25');

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Pflege', 'size' => 26])->assertHasErrors(['size']);
    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Pflege', 'size' => 100])->assertHasErrors(['size']);
    Http::assertSentCount(1);
});

test('hasMore reflects the remaining results', function () {
    Http::fake([MCP_SEARCH_URL => Http::response(mcpApiSearchResponse(40))]);

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Pflege', 'page' => 2, 'size' => 10])
        ->assertStructuredContent(fn ($json) => $json->where('hasMore', true)->where('page', 2)->where('pageSize', 10)->etc());
});

test('numeric strings are accepted and unknown arguments are ignored', function () {
    Http::fake([MCP_SEARCH_URL => Http::response(mcpApiSearchResponse(1))]);

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, [
        'was' => '  Pflege  ', 'umkreis' => '20', 'angebotsart' => '4', 'size' => '5', 'wo' => '', 'api_key' => 'x', 'extra' => ['befristung' => 1],
    ])->assertOk();

    Http::assertSent(fn (Request $request): bool => $request['was'] === 'Pflege' && (string) $request['umkreis'] === '20'
        && (string) $request['size'] === '5' && ! isset($request['wo']) && ! isset($request['api_key']) && ! isset($request['befristung']));
});

test('invalid input is rejected with an english message and no api call', function (array $arguments, string $message) {
    app()->setLocale('de');

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, $arguments)->assertHasErrors([$message]);

    Http::assertNothingSent();
})->with([
    'umkreis not numeric' => [['was' => 'x', 'umkreis' => 'abc'], 'must be an integer'],
    'umkreis too large' => [['was' => 'x', 'umkreis' => 201], 'must be between 0 and 200'],
    'angebotsart unknown' => [['was' => 'x', 'angebotsart' => 3], 'is invalid'],
    'was missing' => [[], 'is required'],
    'was empty' => [['was' => '  '], 'is required'],
    'was too long' => [['was' => str_repeat('a', 256)], 'greater than 255'],
    'boolean as number' => [['was' => 'x', 'umkreis' => true], 'must be an integer'],
    'array as string' => [['was' => ['a']], 'must be a string'],
]);

test('error messages are english and the locale is restored', function (string $locale) {
    app()->setLocale($locale);

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => ''])
        ->assertHasErrors(['is required']);

    expect(app()->getLocale())->toBe($locale)->and(app('translator')->getLocale())->toBe($locale);
})->with(['de', 'ru']);

test('no results are not an error', function () {
    Http::fake([MCP_SEARCH_URL => Http::response(['maxErgebnisse' => 0])]);

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Nichts'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('total', 0)->where('jobs', [])->where('hasMore', false)->etc());
});

test('an unavailable upstream yields a neutral error without details', function (callable $fake) {
    Http::fake([MCP_SEARCH_URL => $fake]);

    $response = NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Pflege'])
        ->assertHasErrors(['The job service is temporarily unavailable. Please try again later.']);

    $response->assertDontSee(['jobsuche.test', 'secret-test-key-123', '500']);
})->with([
    'http 500' => [fn () => Http::response('boom', 500)],
    'connection failure' => [fn () => throw new ConnectionException('cURL error https://jobsuche.test secret-test-key-123')],
]);

test('an unexpected exception yields a generic message even in debug mode', function () {
    config()->set('app.debug', true);
    $this->mock(JobDataMapper::class)->shouldReceive('searchResults')->andThrow(new RuntimeException('internal-secret-detail'));
    Http::fake([MCP_SEARCH_URL => Http::response(mcpApiSearchResponse(1))]);

    NexomaServer::actingAs($this->user)->tool(JobSearchTool::class, ['was' => 'Pflege'])
        ->assertHasErrors(['An internal server error occurred.'])
        ->assertDontSee('internal-secret-detail');
});
