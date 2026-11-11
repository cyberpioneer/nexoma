<?php

use App\Mcp\Servers\NexomaServer;
use App\Mcp\Tools\SaveJobTool;
use App\Models\SavedJob;
use App\Models\SeenJobReference;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

const AI_FIELDS_REFNR = '10001-1234567890-S';

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('services.jobsuche.api_key', 'secret-test-key-123');
    config()->set('inertia.ssr.enabled', false);
    Http::preventStrayRequests();
    Cache::flush();
});

function fakeAiFieldsJobDetails(): void
{
    Http::fake(['https://jobsuche.test/pc/v4/jobdetails/*' => Http::response([
        'referenznummer' => AI_FIELDS_REFNR,
        'stellenangebotsTitel' => 'Pflegefachkraft (m/w/d)',
        'firma' => 'Muster Pflege GmbH',
        'stellenlokationen' => [['adresse' => ['plz' => '10999', 'ort' => 'Berlin']]],
    ])]);
}

test('a manual save through the web always stores source manual and ignores ai fields from the request', function () {
    fakeAiFieldsJobDetails();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('saved-jobs.store'), [
        'refnr' => AI_FIELDS_REFNR,
        'source' => 'ai_matcher',
        'ai_summary' => 'Vom Client',
        'temperature' => 10,
        'distance_km' => 5,
        'briefed_at' => now()->toDateTimeString(),
    ])->assertRedirect();

    $saved = $user->savedJobs()->sole()->fresh();

    expect($saved->source)->toBe('manual')
        ->and($saved->ai_summary)->toBeNull()
        ->and($saved->temperature)->toBeNull()
        ->and($saved->distance_km)->toBeNull()
        ->and($saved->briefed_at)->toBeNull();
});

test('a manual save through the mcp tool always stores source manual', function () {
    fakeAiFieldsJobDetails();
    $user = User::factory()->create();

    NexomaServer::actingAs($user)
        ->tool(SaveJobTool::class, ['refnr' => AI_FIELDS_REFNR, 'source' => 'ai_matcher', 'temperature' => 9])
        ->assertOk();

    $saved = $user->savedJobs()->sole()->fresh();

    expect($saved->source)->toBe('manual')->and($saved->temperature)->toBeNull();
});

test('source defaults to manual on the model and in the database', function () {
    $user = User::factory()->create();

    expect($user->savedJobs()->create(['refnr' => 'A-1'])->source)->toBe('manual');

    DB::table('saved_jobs')->insert(['user_id' => $user->id, 'refnr' => 'A-2', 'created_at' => now(), 'updated_at' => now()]);

    expect(SavedJob::query()->where('refnr', 'A-2')->sole()->source)->toBe('manual');
});

test('source and the ai fields cannot be mass assigned', function () {
    $user = User::factory()->create();

    $saved = $user->savedJobs()->create([
        'refnr' => 'A-3',
        'source' => 'ai_matcher',
        'ai_summary' => 'x',
        'temperature' => 5,
        'distance_km' => 3,
        'briefed_at' => now(),
    ]);

    $saved->refresh();
    expect($saved->source)->toBe('manual')->and($saved->ai_summary)->toBeNull()->and($saved->temperature)->toBeNull()->and($saved->briefed_at)->toBeNull();
});

test('the matcher stores a valid ai assessment', function () {
    $saved = SavedJob::factory()->aiMatcher()->create(['temperature' => 10, 'ai_summary' => str_repeat('ä', 255), 'distance_km' => 0]);

    $saved->refresh();
    expect($saved->source)->toBe('ai_matcher')
        ->and($saved->temperature)->toBe(10)
        ->and(mb_strlen($saved->ai_summary))->toBe(255)
        ->and($saved->distance_km)->toBe(0)
        ->and($saved->briefed_at)->toBeNull();

    $saved->forceFill(['briefed_at' => now(), 'temperature' => 1])->save();
    expect($saved->fresh()->briefed_at)->not->toBeNull()->and($saved->fresh()->temperature)->toBe(1);
});

test('invalid values are rejected before they reach the database', function (array $attributes) {
    $user = User::factory()->create();
    $saved = new SavedJob(['refnr' => 'A-9']);
    $saved->user_id = $user->id;
    $saved->forceFill($attributes);

    expect(fn () => $saved->save())->toThrow(InvalidArgumentException::class);
    expect(SavedJob::query()->count())->toBe(0);
})->with([
    'unknown source' => [['source' => 'import']],
    'temperature zero' => [['source' => 'ai_matcher', 'temperature' => 0]],
    'temperature eleven' => [['source' => 'ai_matcher', 'temperature' => 11]],
    'summary too long' => [['source' => 'ai_matcher', 'ai_summary' => str_repeat('a', 256)]],
    'negative distance' => [['source' => 'ai_matcher', 'distance_km' => -1]],
    'manual with summary' => [['source' => 'manual', 'ai_summary' => 'x']],
    'manual with temperature' => [['source' => 'manual', 'temperature' => 5]],
    'manual with briefed_at' => [['source' => 'manual', 'briefed_at' => '2026-01-01 00:00:00']],
]);

test('ai matches do not use up the manual limit and manual saves are limited on their own', function () {
    fakeAiFieldsJobDetails();
    $user = User::factory()->create();
    SavedJob::factory()->aiMatcher()->count(SavedJob::MAX_PER_USER)->for($user)->create();

    $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => AI_FIELDS_REFNR])->assertRedirect()->assertSessionHasNoErrors();
    expect($user->savedJobs()->manual()->count())->toBe(1);

    SavedJob::factory()->count(SavedJob::MAX_PER_USER - 1)->for($user)->create();
    $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => '10001-0000000001-S'])->assertSessionHasErrors('refnr');
    expect($user->savedJobs()->manual()->count())->toBe(SavedJob::MAX_PER_USER);
});

test('saving an already matched job manually changes only the note and keeps its origin', function () {
    fakeAiFieldsJobDetails();
    $user = User::factory()->create();
    SavedJob::factory()->aiMatcher()->for($user)->create(['refnr' => AI_FIELDS_REFNR, 'temperature' => 7]);

    $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => AI_FIELDS_REFNR, 'note' => 'Bewerben!'])->assertRedirect();

    $saved = $user->savedJobs()->sole()->fresh();
    expect($saved->source)->toBe('ai_matcher')->and($saved->temperature)->toBe(7)->and($saved->note)->toBe('Bewerben!');
    Http::assertNothingSent();
});

test('only references that are new for the user are returned', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    SavedJob::factory()->for($user)->create(['refnr' => 'R-saved-manual']);
    SavedJob::factory()->aiMatcher()->for($user)->create(['refnr' => 'R-saved-ai']);
    SeenJobReference::factory()->for($user)->create(['refnr' => 'R-seen']);
    SeenJobReference::factory()->for($other)->create(['refnr' => 'R-new-for-user']);
    SavedJob::factory()->for($other)->create(['refnr' => 'R-other-saved']);

    expect(SeenJobReference::unseenFor($user, ['R-saved-manual', 'R-new-1', 'R-saved-ai', 'R-seen', 'R-new-for-user', 'R-new-1', 'R-other-saved', '', 7]))
        ->toBe(['R-new-1', 'R-new-for-user', 'R-other-saved'])
        ->and(SeenJobReference::unseenFor($user, []))->toBe([]);
});

test('remembered references are idempotent, survive the deletion of the saved job and stay per user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $saved = SavedJob::factory()->aiMatcher()->for($user)->create(['refnr' => 'R-1']);

    SeenJobReference::rememberFor($user, ['R-1', 'R-2', 'R-2', '', str_repeat('x', 101)]);
    SeenJobReference::rememberFor($user, ['R-2', 'R-3']);

    expect($user->seenJobReferences()->orderBy('refnr')->pluck('refnr')->all())->toBe(['R-1', 'R-2', 'R-3']);

    $saved->delete();

    expect(SeenJobReference::unseenFor($user, ['R-1', 'R-2', 'R-3', 'R-4']))->toBe(['R-4'])
        ->and(SeenJobReference::unseenFor($other, ['R-1', 'R-2']))->toBe(['R-1', 'R-2']);
});

test('the database keeps references unique per user and removes them with the user', function () {
    $user = User::factory()->create();
    SeenJobReference::factory()->for($user)->create(['refnr' => 'R-dup']);

    expect(fn () => SeenJobReference::factory()->for($user)->create(['refnr' => 'R-dup']))->toThrow(UniqueConstraintViolationException::class);

    SavedJob::factory()->for($user)->create(['refnr' => 'R-dup']);
    expect(fn () => SavedJob::factory()->aiMatcher()->for($user)->create(['refnr' => 'R-dup']))->toThrow(UniqueConstraintViolationException::class);

    $user->delete();

    expect(SeenJobReference::query()->count())->toBe(0)->and(SavedJob::query()->count())->toBe(0);
});
