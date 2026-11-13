<?php

use App\Http\Controllers\AudioBriefingController;
use App\Models\AudioBriefing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function briefingSubscriber(): User
{
    $user = User::factory()->create();
    $user->forceFill(['stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();

    return $user;
}

test('guests and unverified users cannot open the list', function () {
    $this->get(route('saved-briefings.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('saved-briefings.index'))
        ->assertRedirect(route('verification.notice'));
});

test('users without a subscription are sent to the upgrade page', function () {
    $user = User::factory()->create();
    AudioBriefing::factory()->for($user)->ready()->create();

    $this->actingAs($user)->get(route('saved-briefings.index'))->assertRedirect(route('billing.upgrade'));
});

test('the list shows only own ready briefings, newest first, with whitelisted fields', function () {
    $user = briefingSubscriber();
    $old = AudioBriefing::factory()->for($user)->ready()->create(['created_at' => now()->subDays(3)]);
    $new = AudioBriefing::factory()->for($user)->ready()->create(['created_at' => now()->subDay()]);
    AudioBriefing::factory()->for($user)->failed()->create();
    AudioBriefing::factory()->for($user)->create(['status' => AudioBriefing::STATUS_PENDING]);
    AudioBriefing::factory()->ready()->create();

    $this->actingAs($user)->get(route('saved-briefings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SavedBriefings/Index')
            ->has('briefings', 2)
            ->where('briefings.0.id', $new->id)
            ->where('briefings.1.id', $old->id)
            ->has('briefings.0', fn (Assert $item) => $item->hasAll(['id', 'durationMs', 'createdAt']))
            ->where('pagination.total', 2));
});

test('no internal fields of a briefing reach the client', function () {
    $user = briefingSubscriber();
    $briefing = AudioBriefing::factory()->for($user)->ready()->create(['speech_text' => 'GEHEIMER-SPRECHTEXT']);

    $response = $this->actingAs($user)->get(route('saved-briefings.index'))->assertOk();

    expect($response->getContent())->not->toContain('GEHEIMER-SPRECHTEXT')
        ->not->toContain((string) $briefing->storage_path)
        ->not->toContain($briefing->content_hash);
});

test('the list is paginated to a fixed page size', function () {
    $user = briefingSubscriber();
    AudioBriefing::factory()->for($user)->ready()->count(AudioBriefingController::PER_PAGE + 3)->create();

    $this->actingAs($user)->get(route('saved-briefings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('briefings', AudioBriefingController::PER_PAGE)
            ->where('pagination.lastPage', 2)
            ->where('pagination.previousUrl', null)
            ->whereNot('pagination.nextUrl', null));

    $this->actingAs($user)->get(route('saved-briefings.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('briefings', 3)->where('pagination.currentPage', 2));
});

test('a subscriber without briefings gets an empty list', function () {
    $this->actingAs(briefingSubscriber())->get(route('saved-briefings.index'))
        ->assertInertia(fn (Assert $page) => $page->has('briefings', 0)->where('pagination.total', 0));
});
