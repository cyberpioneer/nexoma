<?php

use App\Mcp\McpAuditLogger;
use App\Mcp\Servers\NexomaServer;
use App\Mcp\Tools\NexomaTool;
use App\Mcp\Tools\SaveJobTool;
use App\Models\SavedJob;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Server\Testing\TestResponse;

const SAVE_JOB_DETAILS_URL = 'https://jobsuche.test/pc/v4/jobdetails/*';
const SAVE_JOB_REFNR = '10001-1234567890-S';

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('services.jobsuche.api_key', 'secret-test-key-123');
    Http::preventStrayRequests();
    Cache::flush();
    $this->user = User::factory()->create();
});

function fakeSaveJobDetails(): void
{
    Http::fake([SAVE_JOB_DETAILS_URL => Http::response([
        'referenznummer' => SAVE_JOB_REFNR,
        'stellenangebotsTitel' => 'Pflegefachkraft (m/w/d)',
        'stellenangebotsBeschreibung' => 'Nicht speichern.',
        'firma' => 'Muster Pflege GmbH',
        'stellenlokationen' => [['adresse' => ['plz' => '10999', 'ort' => 'Berlin']]],
    ])]);
}

function callSaveJob(User $user, array $arguments): TestResponse
{
    return NexomaServer::actingAs($user)->tool(SaveJobTool::class, $arguments);
}

test('tools/list shows save_job with write annotations and an explicit-request description', function () {
    actingAsMcpUser();

    $tool = collect(mcpRequest('tools/list')->assertOk()->json('result.tools'))->firstWhere('name', 'save_job');

    expect($tool['title'])->toBe('Save a job posting')
        ->and($tool['annotations'])->toMatchArray(['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true])
        ->and($tool['description'])->toContain('explicitly')->toContain('untrusted')
        ->and($tool['inputSchema']['required'])->toBe(['refnr'])
        ->and($tool['inputSchema']['properties']['note']['maxLength'])->toBe(SavedJob::MAX_NOTE_LENGTH);
});

test('a job is saved without a note and the excerpt comes from the server', function () {
    fakeSaveJobDetails();

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'title' => 'Vom Client', 'employer' => 'Fake'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('notice', NexomaTool::NOTICE)
            ->where('saved', true)
            ->where('created', true)
            ->where('refnr', SAVE_JOB_REFNR)
            ->where('title', 'Pflegefachkraft (m/w/d)')
            ->where('employer', 'Muster Pflege GmbH')
            ->where('location', '10999 Berlin')
            ->where('note', null)
            ->where('nexomaUrl', route('jobs.show', ['refnr' => SAVE_JOB_REFNR]))
            ->where('savedJobsUrl', route('saved-jobs.index')));

    $saved = SavedJob::query()->sole();
    expect($saved->user_id)->toBe($this->user->id)
        ->and($saved->title)->toBe('Pflegefachkraft (m/w/d)')
        ->and($saved->note)->toBeNull();
});

test('the response contains exactly the specified fields', function () {
    fakeSaveJobDetails();
    actingAsMcpUser($this->user);

    $payload = mcpRequest('tools/call', ['name' => 'save_job', 'arguments' => ['refnr' => SAVE_JOB_REFNR]])
        ->assertOk()->json('result.structuredContent');

    expect(array_keys($payload))->toBe(['notice', 'saved', 'created', 'refnr', 'title', 'employer', 'location', 'note', 'nexomaUrl', 'savedJobsUrl']);
});

test('a job is saved with a trimmed note', function () {
    fakeSaveJobDetails();

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => "  Bewerben bis Freitag \n"])
        ->assertStructuredContent(fn ($json) => $json->where('note', 'Bewerben bis Freitag')->where('created', true)->etc());

    expect(SavedJob::query()->sole()->note)->toBe('Bewerben bis Freitag');
});

test('saving again changes only the note without a duplicate or a second api call', function () {
    fakeSaveJobDetails();
    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => 'alt']);

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => 'neu'])
        ->assertStructuredContent(fn ($json) => $json->where('created', false)->where('note', 'neu')->etc());

    expect(SavedJob::query()->count())->toBe(1)->and(SavedJob::query()->sole()->note)->toBe('neu');
    Http::assertSentCount(1);
});

test('an omitted note keeps the existing one and an empty string removes it', function () {
    fakeSaveJobDetails();
    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => 'bleibt']);

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR])
        ->assertStructuredContent(fn ($json) => $json->where('note', 'bleibt')->etc());
    expect(SavedJob::query()->sole()->note)->toBe('bleibt');

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => '   '])
        ->assertStructuredContent(fn ($json) => $json->where('note', null)->etc());
    expect(SavedJob::query()->sole()->note)->toBeNull();
    Http::assertSentCount(1);
});

test('invalid input is rejected without saving or an api call', function (array $arguments) {
    callSaveJob($this->user, $arguments)->assertHasErrors();

    expect(SavedJob::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'path traversal' => [['refnr' => '../x']],
    'whitespace inside' => [['refnr' => '10001 123']],
    'too long refnr' => [['refnr' => str_repeat('a', 101)]],
    'missing refnr' => [[]],
    'note too long' => [['refnr' => SAVE_JOB_REFNR, 'note' => str_repeat('a', 1001)]],
    'note not a string' => [['refnr' => SAVE_JOB_REFNR, 'note' => 123]],
    'note array' => [['refnr' => SAVE_JOB_REFNR, 'note' => ['x']]],
    'note boolean' => [['refnr' => SAVE_JOB_REFNR, 'note' => true]],
]);

test('a note of exactly 1000 characters is accepted', function () {
    fakeSaveJobDetails();

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => str_repeat('ä', 1000)])->assertOk();

    expect(mb_strlen(SavedJob::query()->sole()->note))->toBe(1000);
});

test('an unavailable api saves nothing and reports upstream_unavailable', function () {
    Http::fake([SAVE_JOB_DETAILS_URL => Http::response('x', 500)]);

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => 'x'])
        ->assertHasErrors(['The job posting could not be retrieved, so it was not saved (it may no longer exist, or the service is unavailable).']);

    expect(SavedJob::query()->count())->toBe(0);
});

test('the per user limit blocks saving without an api call', function () {
    SavedJob::factory()->count(SavedJob::MAX_PER_USER)->for($this->user)->create();

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR])
        ->assertHasErrors(['The user has reached the maximum of 100 saved jobs. Nothing was saved; the user has to remove a saved job in Nexoma first.']);

    expect(SavedJob::query()->count())->toBe(SavedJob::MAX_PER_USER);
    Http::assertNothingSent();
});

test('at the limit an already saved job can still get its note changed', function () {
    SavedJob::factory()->count(SavedJob::MAX_PER_USER - 1)->for($this->user)->create();
    SavedJob::factory()->for($this->user)->create(['refnr' => SAVE_JOB_REFNR, 'note' => null]);

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => 'ok'])->assertOk();

    expect(SavedJob::query()->where('refnr', SAVE_JOB_REFNR)->sole()->note)->toBe('ok');
    Http::assertNothingSent();
});

test('the write rate limit stops the 31st call per user without writing or an api call', function () {
    RateLimiter::clear('mcp-save-job:'.$this->user->id);
    SavedJob::factory()->for($this->user)->create(['refnr' => SAVE_JOB_REFNR]);

    foreach (range(1, AppServiceProvider::SAVED_JOBS_ATTEMPTS_PER_MINUTE) as $ignored) {
        callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR])->assertOk();
    }

    callSaveJob($this->user, ['refnr' => 'OTHER-1', 'note' => 'x'])->assertHasErrors(['Too many save requests. Nothing was saved; please try again in a minute.']);

    expect(SavedJob::query()->count())->toBe(1);
    Http::assertNothingSent();

    fakeSaveJobDetails();
    callSaveJob(User::factory()->create(), ['refnr' => SAVE_JOB_REFNR])->assertOk();
    Http::assertSentCount(1);
});

test('saving only ever affects the token user', function () {
    fakeSaveJobDetails();
    $other = User::factory()->create();

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'user_id' => $other->id, 'user' => $other->email])->assertOk();

    expect($this->user->savedJobs()->count())->toBe(1)->and($other->savedJobs()->count())->toBe(0);
});

test('a token without the mcp scope is rejected before the tool runs', function () {
    fakeSaveJobDetails();
    actingAsMcpUser($this->user, []);

    mcpRequest('tools/call', ['name' => 'save_job', 'arguments' => ['refnr' => SAVE_JOB_REFNR]])->assertForbidden();

    expect(SavedJob::query()->count())->toBe(0);
    Http::assertNothingSent();
});

test('the audit entry contains neither the refnr nor the note', function () {
    $file = tempnam(sys_get_temp_dir(), 'mcp-audit-');
    config()->set('logging.channels.mcp_audit', ['driver' => 'single', 'path' => $file, 'level' => 'info']);
    fakeSaveJobDetails();

    callSaveJob($this->user, ['refnr' => SAVE_JOB_REFNR, 'note' => 'GEHEIMER-VERMERK'])->assertOk();

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $log = (string) file_get_contents($file);
    @unlink($file);

    preg_match('/mcp_audit (\{.*\})\s*$/', $lines[0], $matches);
    $entry = json_decode($matches[1], true);

    expect($lines)->toHaveCount(1)
        ->and(array_keys($entry))->toEqualCanonicalizing(McpAuditLogger::FIELDS)
        ->and($entry)->toMatchArray(['tool' => 'save_job', 'status' => 'ok', 'user_id' => $this->user->id])
        ->and($log)->not->toContain('GEHEIMER-VERMERK')->not->toContain(SAVE_JOB_REFNR);
});
