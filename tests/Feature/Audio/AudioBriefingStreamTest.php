<?php

use App\Models\AudioBriefing;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Storage;

function streamSubscriber(): User
{
    $user = User::factory()->create();
    $user->forceFill(['stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();

    return $user;
}

function storedBriefing(User $user, string $content = 'RIFF0123456789abcdef'): AudioBriefing
{
    $briefing = AudioBriefing::factory()->for($user)->ready()->create();
    Storage::disk('local')->put($briefing->storage_path, $content);

    return $briefing;
}

beforeEach(function () {
    Storage::fake('local');
});

test('the owner streams the briefing with audio headers', function () {
    $user = streamSubscriber();
    $briefing = storedBriefing($user);

    $response = $this->actingAs($user)->get(route('audio-briefings.stream', $briefing));

    $response->assertOk()
        ->assertHeader('Content-Type', 'audio/wav')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Accept-Ranges', 'bytes');
    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('public')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline');
});

test('range requests are answered with partial content', function () {
    $user = streamSubscriber();
    $briefing = storedBriefing($user, '0123456789ABCDEFGHIJ');

    $response = $this->actingAs($user)->get(route('audio-briefings.stream', $briefing), ['Range' => 'bytes=2-5']);

    $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/20');
    expect($response->streamedContent())->toBe('2345');
});

test('another user gets 403 and nothing is leaked', function () {
    $briefing = storedBriefing(streamSubscriber());

    $this->actingAs(streamSubscriber())->get(route('audio-briefings.stream', $briefing))->assertForbidden();
    $this->actingAs(streamSubscriber())->get(route('audio-briefings.stream', $briefing), ['Range' => 'bytes=0-3'])->assertForbidden();
});

test('guests, unverified users and users without subscription are stopped', function () {
    $briefing = storedBriefing($owner = streamSubscriber());
    $url = route('audio-briefings.stream', $briefing);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get($url)->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->create())->get($url)->assertRedirect(route('billing.upgrade'));

    $owner->forceFill(['stripe_subscription_status' => 'canceled'])->save();
    $this->actingAs($owner->fresh())->get($url)->assertRedirect(route('billing.upgrade'));
});

test('briefings that are not ready or without a file answer 404', function () {
    $user = streamSubscriber();
    $pending = AudioBriefing::factory()->for($user)->create();
    $failed = AudioBriefing::factory()->for($user)->failed()->create();
    $missingFile = AudioBriefing::factory()->for($user)->ready()->create();

    foreach ([$pending, $failed, $missingFile] as $briefing) {
        $this->actingAs($user)->get(route('audio-briefings.stream', $briefing))->assertNotFound();
    }
});

test('a tampered storage path is never served', function (string $path) {
    $user = streamSubscriber();
    $briefing = AudioBriefing::factory()->for($user)->ready()->create();
    $briefing->forceFill(['storage_path' => $path])->save();
    Storage::disk('local')->put('secret.txt', 'secret');

    $this->actingAs($user)->get(route('audio-briefings.stream', $briefing))->assertNotFound();
})->with([
    'traversal' => 'audio-briefings/../secret.txt',
    'other directory' => 'secret.txt',
    'wrong extension' => 'audio-briefings/aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa.txt',
    'absolute' => '/etc/passwd',
]);

test('the route only accepts numeric ids', function () {
    $this->actingAs(streamSubscriber())->get('/audio-briefings/abc/stream')->assertNotFound();
});

test('the stream is rate limited per user', function () {
    $user = streamSubscriber();
    $briefing = storedBriefing($user);

    foreach (range(1, AppServiceProvider::AUDIO_STREAM_ATTEMPTS_PER_MINUTE) as $ignored) {
        $this->actingAs($user)->get(route('audio-briefings.stream', $briefing))->assertOk();
    }

    $this->actingAs($user)->get(route('audio-briefings.stream', $briefing))->assertTooManyRequests();
});

test('deleting a briefing removes its audio file', function () {
    $briefing = storedBriefing(streamSubscriber());
    $path = $briefing->storage_path;

    $briefing->delete();

    Storage::disk('local')->assertMissing($path);
});

test('deleting a user removes the audio files of all their briefings', function () {
    $user = streamSubscriber();
    $paths = [storedBriefing($user)->storage_path, storedBriefing($user)->storage_path];
    $foreign = storedBriefing(streamSubscriber());

    $user->delete();

    foreach ($paths as $path) {
        Storage::disk('local')->assertMissing($path);
    }
    Storage::disk('local')->assertExists($foreign->storage_path);
    expect(AudioBriefing::query()->count())->toBe(1);
});
