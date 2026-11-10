<?php

use App\Models\SavedJob;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;

const SAVED_TEST_REFNR = '10001-1234567890-S';

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('services.jobsuche.api_key', 'secret-test-key-123');
    config()->set('inertia.ssr.enabled', false);

    Http::preventStrayRequests();
    Cache::flush();
});

/**
 * Faked die Detail-Antwort der BA fuer eine Stelle (inkl. nicht freigegebener Felder).
 */
function fakeSavedJobDetails(string $title = 'Pflegefachkraft (m/w/d)'): void
{
    Http::fake([
        'https://jobsuche.test/pc/v4/jobdetails/*' => Http::response([
            'referenznummer' => SAVED_TEST_REFNR,
            'stellenangebotsTitel' => $title,
            'stellenangebotsBeschreibung' => 'Lange Beschreibung, die nicht gespeichert werden darf',
            'firma' => 'Muster Pflege GmbH',
            'hauptberuf' => 'Altenpflege',
            'stellenlokationen' => [['adresse' => ['plz' => '10999', 'ort' => 'Berlin']]],
        ]),
    ]);
}

test('guests are sent to the login and unverified users to the verification notice', function () {
    $savedJob = SavedJob::factory()->create();

    foreach ([
        ['get', route('saved-jobs.index')],
        ['post', route('saved-jobs.store')],
        ['patch', route('saved-jobs.update', $savedJob)],
        ['delete', route('saved-jobs.destroy', $savedJob)],
    ] as [$method, $url]) {
        auth()->forgetGuards();
        $this->{$method}($url)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())->{$method}($url)->assertRedirect(route('verification.notice'));
    }

    Http::assertNothingSent();
    expect(SavedJob::query()->count())->toBe(1);
});

test('a job can be saved without a note and the excerpt comes from the server', function () {
    fakeSavedJobDetails();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('jobs.index'))
        ->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR, 'title' => 'Vom Client', 'employer' => 'Vom Client', 'user_id' => 999])
        ->assertRedirect(route('jobs.index'))
        ->assertSessionHasNoErrors();

    $saved = $user->savedJobs()->sole();

    expect($saved->refnr)->toBe(SAVED_TEST_REFNR)
        ->and($saved->title)->toBe('Pflegefachkraft (m/w/d)')
        ->and($saved->employer)->toBe('Muster Pflege GmbH')
        ->and($saved->location)->toBe('10999 Berlin')
        ->and($saved->note)->toBeNull()
        ->and($saved->user_id)->toBe($user->id)
        ->and(array_keys($saved->getAttributes()))->not->toContain('description');
});

test('a job can be saved with a trimmed note', function () {
    fakeSavedJobDetails();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR, 'note' => "  Anrufen\nbis Freitag  "])
        ->assertSessionHasNoErrors();

    expect($user->savedJobs()->sole()->note)->toBe("Anrufen\nbis Freitag");
});

test('saving the same reference number again creates no duplicate and only updates the note', function () {
    fakeSavedJobDetails();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR, 'note' => 'erste'])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR, 'note' => 'zweite'])->assertSessionHasNoErrors();

    expect($user->savedJobs()->count())->toBe(1)
        ->and($user->savedJobs()->sole()->note)->toBe('zweite');

    $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR])->assertSessionHasNoErrors();

    expect($user->savedJobs()->sole()->note)->toBe('zweite');
    Http::assertSentCount(1);
});

test('two users can save the same job independently', function () {
    fakeSavedJobDetails();

    foreach ([User::factory()->create(), User::factory()->create()] as $user) {
        auth()->forgetGuards();
        $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR])->assertSessionHasNoErrors();
    }

    expect(SavedJob::query()->count())->toBe(2);
});

test('nothing is saved and a clear error is shown when the BA does not answer', function () {
    Http::fake(['https://jobsuche.test/*' => Http::response('', 503)]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR])
        ->assertSessionHasErrors(['refnr' => __('ui.saved.error.unavailable')]);

    expect($user->savedJobs()->count())->toBe(0);
});

test('the per-user limit is enforced without calling the BA', function () {
    $user = User::factory()->create();
    SavedJob::factory()->count(SavedJob::MAX_PER_USER)->for($user)->create();

    $this->actingAs($user)
        ->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR])
        ->assertSessionHasErrors(['refnr' => __('ui.saved.error.limit', ['max' => SavedJob::MAX_PER_USER])]);

    expect($user->savedJobs()->count())->toBe(SavedJob::MAX_PER_USER);
    Http::assertNothingSent();
});

test('the limit does not block updating the note of an already saved job', function () {
    $user = User::factory()->create();
    SavedJob::factory()->count(SavedJob::MAX_PER_USER - 1)->for($user)->create();
    SavedJob::factory()->for($user)->create(['refnr' => SAVED_TEST_REFNR]);

    $this->actingAs($user)
        ->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR, 'note' => 'neu'])
        ->assertSessionHasNoErrors();

    expect($user->savedJobs()->where('refnr', SAVED_TEST_REFNR)->sole()->note)->toBe('neu');
});

test('store validates the reference number and the note', function (array $input, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('saved-jobs.store'), $input)->assertSessionHasErrors($field);

    expect(SavedJob::query()->count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'missing refnr' => [[], 'refnr'],
    'refnr with slash' => [['refnr' => '../etc/passwd'], 'refnr'],
    'refnr too long' => [['refnr' => str_repeat('a', 101)], 'refnr'],
    'refnr with newline' => [['refnr' => "abc\n"], 'refnr'],
    'refnr as array' => [['refnr' => ['abc']], 'refnr'],
    'note too long' => [['refnr' => SAVED_TEST_REFNR, 'note' => str_repeat('x', 1001)], 'note'],
    'note as array' => [['refnr' => SAVED_TEST_REFNR, 'note' => ['x']], 'note'],
    'note as integer' => [['refnr' => SAVED_TEST_REFNR, 'note' => 12], 'note'],
]);

test('a note of exactly 1000 characters is accepted', function () {
    fakeSavedJobDetails();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('saved-jobs.store'), ['refnr' => SAVED_TEST_REFNR, 'note' => str_repeat('ä', 1000)])
        ->assertSessionHasNoErrors();

    expect(mb_strlen((string) $user->savedJobs()->sole()->note))->toBe(1000);
});

test('the note can be changed and removed', function () {
    $user = User::factory()->create();
    $saved = SavedJob::factory()->withNote('alt')->for($user)->create();

    $this->actingAs($user)->patch(route('saved-jobs.update', $saved), ['note' => " neu \n zwei "])->assertSessionHasNoErrors();
    expect($saved->refresh()->note)->toBe("neu \n zwei");

    $this->actingAs($user)->patch(route('saved-jobs.update', $saved), ['note' => ''])->assertSessionHasNoErrors();
    expect($saved->refresh()->note)->toBeNull();
});

test('the note update changes only the note and validates it', function () {
    $user = User::factory()->create();
    $saved = SavedJob::factory()->withNote('alt')->for($user)->create(['title' => 'Titel']);

    $this->actingAs($user)->patch(route('saved-jobs.update', $saved), ['note' => 'neu', 'title' => 'X', 'refnr' => 'Y', 'user_id' => 99]);
    expect($saved->refresh()->title)->toBe('Titel')->and($saved->user_id)->toBe($user->id)->and($saved->refnr)->not->toBe('Y');

    $this->actingAs($user)->patch(route('saved-jobs.update', $saved), ['note' => str_repeat('x', 1001)])->assertSessionHasErrors('note');
    $this->actingAs($user)->patch(route('saved-jobs.update', $saved), ['note' => ['x']])->assertSessionHasErrors('note');
    $this->actingAs($user)->patch(route('saved-jobs.update', $saved), [])->assertSessionHasErrors('note');
    expect($saved->refresh()->note)->toBe('neu');
});

test('a saved job can be deleted', function () {
    $user = User::factory()->create();
    $saved = SavedJob::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('saved-jobs.destroy', $saved))->assertRedirect();

    expect(SavedJob::query()->count())->toBe(0);
});

test('foreign and unknown entries answer 404 for update and delete and stay untouched', function () {
    $owner = User::factory()->create();
    $foreign = SavedJob::factory()->withNote('privat')->for($owner)->create();
    $other = User::factory()->create();

    $this->actingAs($other)->patch(route('saved-jobs.update', $foreign), ['note' => 'gehackt'])->assertNotFound();
    $this->actingAs($other)->delete(route('saved-jobs.destroy', $foreign))->assertNotFound();
    $this->actingAs($other)->patch(route('saved-jobs.update', 999999), ['note' => 'x'])->assertNotFound();
    $this->actingAs($other)->delete(route('saved-jobs.destroy', 999999))->assertNotFound();

    expect($foreign->refresh()->note)->toBe('privat');
    $this->assertModelExists($foreign);
});

test('non numeric ids do not match a route', function () {
    $this->actingAs(User::factory()->create())->delete('/saved-jobs/abc')->assertNotFound();
});

test('the list shows only own entries with whitelisted fields, newest first', function () {
    $user = User::factory()->create();
    $old = SavedJob::factory()->for($user)->create(['created_at' => now()->subDays(2)]);
    $new = SavedJob::factory()->withNote("a\nb")->for($user)->create(['created_at' => now()]);
    SavedJob::factory()->withNote('fremd')->create();

    $this->actingAs($user)->get(route('saved-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SavedJobs/Index')
            ->has('savedJobs', 2)
            ->where('savedJobs.0.id', $new->id)
            ->where('savedJobs.1.id', $old->id)
            ->where('savedJobs.0.note', "a\nb")
            ->where('savedJobs.0.savedAt', now()->format('Y-m-d'))
            ->has('savedJobs.0', fn (Assert $job) => $job->hasAll(['id', 'refnr', 'title', 'employer', 'location', 'note', 'savedAt'])),
        );
});

test('the list is capped at the per-user maximum', function () {
    $user = User::factory()->create();
    SavedJob::factory()->count(SavedJob::MAX_PER_USER + 5)->for($user)->create();

    $this->actingAs($user)->get(route('saved-jobs.index'))
        ->assertInertia(fn (Assert $page) => $page->has('savedJobs', SavedJob::MAX_PER_USER));
});

test('the job pages carry only the own saved state', function () {
    fakeSavedJobDetails();
    $user = User::factory()->create();
    SavedJob::factory()->withNote('meins')->for($user)->create(['refnr' => SAVED_TEST_REFNR]);
    SavedJob::factory()->withNote('fremd')->create(['refnr' => SAVED_TEST_REFNR]);
    $other = User::factory()->create();

    $this->actingAs($user)->get(route('jobs.show', SAVED_TEST_REFNR))
        ->assertInertia(fn (Assert $page) => $page
            ->where('saved.note', 'meins')
            ->has('saved', fn (Assert $saved) => $saved->hasAll(['id', 'note'])));

    $this->actingAs($user)->get(route('jobs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('saved', 1)
            ->where('saved.0.refnr', SAVED_TEST_REFNR)
            ->where('saved.0.note', 'meins'));

    auth()->forgetGuards();
    $this->actingAs($other)->get(route('jobs.show', SAVED_TEST_REFNR))
        ->assertInertia(fn (Assert $page) => $page->where('saved', null));

    $this->actingAs($other)->get(route('jobs.index'))
        ->assertInertia(fn (Assert $page) => $page->has('saved', 0));
});

test('the saved jobs limiter blocks after the limit per user', function () {
    expect(RateLimiter::limiter('saved-jobs'))->not->toBeNull()
        ->and(AppServiceProvider::SAVED_JOBS_ATTEMPTS_PER_MINUTE)->toBe(30);

    $user = User::factory()->create();

    for ($i = 0; $i < AppServiceProvider::SAVED_JOBS_ATTEMPTS_PER_MINUTE; $i++) {
        $this->actingAs($user)->get(route('saved-jobs.index'))->assertOk();
    }

    $this->actingAs($user)->get(route('saved-jobs.index'))->assertTooManyRequests();
});

test('deleting a user removes the saved jobs', function () {
    $user = User::factory()->create();
    SavedJob::factory()->count(2)->for($user)->create();

    $user->delete();

    expect(SavedJob::query()->count())->toBe(0);
});
