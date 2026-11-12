<?php

use App\Models\SavedJob;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('inertia.ssr.enabled', false);
});

test('an ai suggestion carries its summary, fit and distance in the list props', function () {
    $user = User::factory()->create();
    $job = SavedJob::factory()->aiMatcher()->for($user)->create(['ai_summary' => 'Kurzfassung der Stelle', 'temperature' => 8, 'distance_km' => 27]);

    $this->actingAs($user)->get(route('saved-jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('savedJobs', 1)
            ->where('savedJobs.0.id', $job->id)
            ->where('savedJobs.0.ai', ['summary' => 'Kurzfassung der Stelle', 'temperature' => 8, 'distanceKm' => 27])
            ->has('savedJobs.0', fn (Assert $row) => $row->hasAll(['id', 'refnr', 'title', 'employer', 'location', 'note', 'savedAt', 'ai', 'ai.summary', 'ai.temperature', 'ai.distanceKm'])));
});

test('a suggestion without a distance keeps the other ai fields', function () {
    $user = User::factory()->create();
    SavedJob::factory()->aiMatcher()->for($user)->create(['distance_km' => null, 'temperature' => 5, 'ai_summary' => 'Text']);

    $this->actingAs($user)->get(route('saved-jobs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('savedJobs.0.ai.distanceKm', null)
            ->where('savedJobs.0.ai.temperature', 5)
            ->where('savedJobs.0.ai.summary', 'Text'));
});

test('manually saved jobs keep exactly the release 1.1.0 props without ai fields', function () {
    $user = User::factory()->create();
    SavedJob::factory()->for($user)->create();

    $this->actingAs($user)->get(route('saved-jobs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('savedJobs.0', fn (Assert $row) => $row->hasAll(['id', 'refnr', 'title', 'employer', 'location', 'note', 'savedAt'])));
});

test('ai fields of other users and internal columns never reach the client', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    SavedJob::factory()->aiMatcher()->for($other)->create(['ai_summary' => 'FREMDE ZUSAMMENFASSUNG']);
    SavedJob::factory()->aiMatcher()->for($user)->create(['briefed_at' => now()]);

    $response = $this->actingAs($user)->get(route('saved-jobs.index'))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->has('savedJobs', 1));
    expect($response->getContent())->not->toContain('FREMDE ZUSAMMENFASSUNG')->not->toContain('briefed_at')->not->toContain('"source"');
});

test('a summary with markup is passed as plain text for escaped output', function () {
    $user = User::factory()->create();
    SavedJob::factory()->aiMatcher()->for($user)->create(['ai_summary' => '<img src=x onerror=alert(1)> Text']);

    $this->actingAs($user)->get(route('saved-jobs.index'))
        ->assertInertia(fn (Assert $page) => $page->where('savedJobs.0.ai.summary', '<img src=x onerror=alert(1)> Text'));

    expect(file_get_contents(resource_path('js/components/SavedJobItem.vue')))->not->toContain('v-html');
});
