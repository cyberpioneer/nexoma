<?php

use App\Mcp\McpAuditLogger;
use App\Mcp\Servers\NexoraServer;
use App\Mcp\Tools\JobDetailsTool;
use App\Mcp\Tools\JobSearchOptionsTool;
use App\Mcp\Tools\JobSearchTool;
use App\Models\User;
use App\Services\Jobsuche\JobDataMapper;
use Illuminate\Support\Facades\Http;

const MCP_SENTINEL_SEARCH = 'ZZ-GEHEIM-123';
const MCP_SENTINEL_REFNR = 'REFNR-GEHEIM-456';
const MCP_SENTINEL_AGENT = 'SentinelAgent/7.7';
const MCP_SENTINEL_EMAIL = 'sentinel.mail@example.test';

beforeEach(function () {
    $this->auditFile = tempnam(sys_get_temp_dir(), 'mcp-audit-');
    config()->set('logging.channels.mcp_audit', ['driver' => 'single', 'path' => $this->auditFile, 'level' => 'info']);
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    Http::preventStrayRequests();
});

afterEach(function () {
    @unlink($this->auditFile);
});

/**
 * Liest die Kontexte der Audit-Eintraege.
 *
 * @return list<array<string, mixed>>
 */
function mcpAuditEntries(string $file): array
{
    $entries = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        preg_match('/mcp_audit (\{.*\})\s*$/', $line, $matches);
        $entries[] = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }

    return $entries;
}

test('each tool call writes exactly one entry with the allowed fields and no input or secrets', function () {
    $user = User::factory()->create(['email' => MCP_SENTINEL_EMAIL]);
    $client = createPublicOAuthClient();
    $tokens = issueOAuthTokens($user, $client);
    auth()->forgetGuards();
    Http::fake(['*' => Http::response(['maxErgebnisse' => 0])]);

    mcpRequest('tools/call', ['name' => 'job_search', 'arguments' => ['was' => MCP_SENTINEL_SEARCH, 'wo' => MCP_SENTINEL_SEARCH]], [
        'Authorization' => 'Bearer '.$tokens['access_token'],
        'User-Agent' => MCP_SENTINEL_AGENT,
    ])->assertOk();

    $entries = mcpAuditEntries($this->auditFile);

    expect($entries)->toHaveCount(1)
        ->and(array_keys($entries[0]))->toEqualCanonicalizing(McpAuditLogger::FIELDS)
        ->and($entries[0])->toMatchArray([
            'event' => 'tool_call', 'user_id' => $user->getKey(), 'client_id' => $client->getKey(), 'tool' => 'job_search', 'status' => 'ok',
        ])
        ->and($entries[0]['duration_ms'])->toBeInt();

    $log = (string) file_get_contents($this->auditFile);

    foreach ([MCP_SENTINEL_SEARCH, MCP_SENTINEL_AGENT, MCP_SENTINEL_EMAIL, $tokens['access_token'], 'Bearer', 'Authorization', '127.0.0.1'] as $secret) {
        expect($log)->not->toContain($secret);
    }
});

test('every tool and status combination is audited with only the allowed keys', function (string $tool, array $arguments, string $status) {
    $user = User::factory()->create(['email' => MCP_SENTINEL_EMAIL]);

    match ($status) {
        'upstream_unavailable' => Http::fake(['*' => Http::response('x', 500)]),
        'error' => $this->mock(JobDataMapper::class)->shouldReceive('searchResults', 'jobDetails')->andThrow(new RuntimeException(MCP_SENTINEL_SEARCH)),
        default => Http::fake(['*' => Http::response(['maxErgebnisse' => 0, 'referenznummer' => 'A-1', 'stellenangebotsTitel' => 't'])]),
    };
    if ($status === 'error') {
        Http::fake(['*' => Http::response(['maxErgebnisse' => 0, 'referenznummer' => 'A-1', 'stellenangebotsTitel' => 't'])]);
    }

    NexoraServer::actingAs($user)->tool($tool, $arguments);

    $entries = mcpAuditEntries($this->auditFile);

    expect($entries)->toHaveCount(1)
        ->and(array_keys($entries[0]))->toEqualCanonicalizing(McpAuditLogger::FIELDS)
        ->and($entries[0]['status'])->toBe($status)
        ->and($entries[0]['user_id'])->toBe($user->getKey());

    $log = (string) file_get_contents($this->auditFile);
    expect($log)->not->toContain(MCP_SENTINEL_SEARCH)->not->toContain(MCP_SENTINEL_REFNR)->not->toContain(MCP_SENTINEL_EMAIL);
})->with([
    'search ok' => [JobSearchTool::class, ['was' => MCP_SENTINEL_SEARCH], 'ok'],
    'search invalid' => [JobSearchTool::class, ['was' => MCP_SENTINEL_SEARCH, 'umkreis' => 999], 'invalid_input'],
    'search upstream' => [JobSearchTool::class, ['was' => MCP_SENTINEL_SEARCH], 'upstream_unavailable'],
    'search error' => [JobSearchTool::class, ['was' => MCP_SENTINEL_SEARCH], 'error'],
    'details ok' => [JobDetailsTool::class, ['refnr' => MCP_SENTINEL_REFNR], 'ok'],
    'details invalid' => [JobDetailsTool::class, ['refnr' => '../'.MCP_SENTINEL_REFNR], 'invalid_input'],
    'details upstream' => [JobDetailsTool::class, ['refnr' => MCP_SENTINEL_REFNR], 'upstream_unavailable'],
    'details error' => [JobDetailsTool::class, ['refnr' => MCP_SENTINEL_REFNR], 'error'],
    'options ok' => [JobSearchOptionsTool::class, [], 'ok'],
]);

test('denied access is audited with ids and status only', function (bool $unverified, string $status) {
    $user = User::factory()->create(['email' => MCP_SENTINEL_EMAIL, 'email_verified_at' => $unverified ? null : now()]);
    actingAsMcpUser($user, $unverified ? ['mcp:use'] : []);

    mcpRequest('tools/list', headers: ['User-Agent' => MCP_SENTINEL_AGENT])->assertForbidden();

    $entries = mcpAuditEntries($this->auditFile);

    expect($entries)->toHaveCount(1)
        ->and(array_keys($entries[0]))->toEqualCanonicalizing(McpAuditLogger::FIELDS)
        ->and($entries[0])->toMatchArray(['event' => 'access_denied', 'status' => $status, 'user_id' => $user->getKey(), 'tool' => null]);

    expect((string) file_get_contents($this->auditFile))->not->toContain(MCP_SENTINEL_EMAIL)->not->toContain(MCP_SENTINEL_AGENT);
})->with([
    'unverified' => [true, 'unverified'],
    'missing scope' => [false, 'missing_scope'],
]);

test('requests without a valid token write no audit entry', function () {
    mcpRequest('tools/list', headers: ['Authorization' => 'Bearer '.MCP_SENTINEL_AGENT])->assertUnauthorized();

    expect(file_get_contents($this->auditFile))->toBe('');
});
