<?php

use App\Models\AudioBriefing;
use App\Models\SavedJob;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function dashboardSubscriber(): User
{
    $user = User::factory()->create();
    $user->forceFill(['stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();

    return $user;
}

test('the dashboard counts only the own saved jobs of every source', function () {
    $user = User::factory()->create();
    SavedJob::factory()->for($user)->count(2)->create();
    $matcher = $user->savedJobs()->make(['refnr' => '10001-1-S', 'title' => 'KI']);
    $matcher->forceFill(['source' => 'ai_matcher', 'ai_summary' => 'x', 'temperature' => 5])->save();
    SavedJob::factory()->count(4)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('savedJobsCount', 3));
});

test('users without a subscription get no audio section at all', function () {
    $user = User::factory()->create();
    AudioBriefing::factory()->for($user)->ready()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('audio', null)->where('savedJobsCount', 0));
});

test('a subscriber without briefings gets a zero count and no player', function () {
    $this->actingAs(dashboardSubscriber())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('audio.count', 0)->where('audio.latest', null));
});

test('the newest ready briefing is offered and only ready ones are counted', function () {
    $user = dashboardSubscriber();
    AudioBriefing::factory()->for($user)->ready()->create(['created_at' => now()->subDays(3)]);
    $newest = AudioBriefing::factory()->for($user)->ready()->create(['created_at' => now()->subDay(), 'duration_ms' => 61_500]);
    AudioBriefing::factory()->for($user)->create(['created_at' => now()]);
    AudioBriefing::factory()->for($user)->failed()->create(['created_at' => now()]);
    AudioBriefing::factory()->ready()->create(['created_at' => now()]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('audio.count', 2)
            ->where('audio.latest.id', $newest->id)
            ->where('audio.latest.durationMs', 61_500)
            ->where('audio.latest.createdAt', now()->subDay()->format('Y-m-d')));
});

test('the dashboard never exposes the speech text, the file path or other users data', function () {
    $user = dashboardSubscriber();
    $own = AudioBriefing::factory()->for($user)->ready()->create(['speech_text' => 'GEHEIMER-SPRECHTEXT']);
    $foreign = AudioBriefing::factory()->ready()->create(['speech_text' => 'FREMDER-TEXT']);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page->where('audio.latest', fn ($latest) => array_keys($latest->toArray()) === ['id', 'durationMs', 'createdAt']));
    expect($response->getContent())->not->toContain('GEHEIMER-SPRECHTEXT', 'FREMDER-TEXT', $own->storage_path, $foreign->storage_path);
});

test('the dashboard stays reachable for verified users and closed for others', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();
});

test('the new dashboard texts exist in every language', function (string $locale) {
    $keys = [
        'dashboard.stats.title', 'dashboard.stats.saved_jobs', 'dashboard.stats.briefings', 'dashboard.briefing.title',
        'dashboard.briefing.created', 'dashboard.briefing.empty', 'dashboard.briefing.play', 'dashboard.briefing.stop',
        'dashboard.briefing.progress', 'dashboard.briefing.speed', 'dashboard.briefing.speed_option',
        'dashboard.briefing.error', 'dashboard.briefing.ai_note',
    ];

    $this->actingAs(User::factory()->create())->withSession(['locale' => $locale])->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('translations', function ($translations) use ($keys): bool {
            foreach ($keys as $key) {
                if (! isset($translations[$key]) || $translations[$key] === '' || $translations[$key] === $key) {
                    return false;
                }
            }

            return true;
        }));
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);
