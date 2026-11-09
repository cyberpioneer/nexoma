<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

function verificationUrl(User $user, ?DateTimeInterface $expiresAt = null, ?string $hash = null): string
{
    return URL::temporarySignedRoute('verification.verify', $expiresAt ?? now()->addMinutes(60), [
        'id' => $user->getKey(),
        'hash' => $hash ?? sha1($user->getEmailForVerification()),
    ]);
}

test('verify email page is rendered as inertia page', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('verification.notice'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/VerifyEmail'));
});

test('verified users are redirected from the notice to the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('verification.notice'))
        ->assertRedirect('/dashboard');
});

test('a valid signed link verifies the user', function () {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(verificationUrl($user))
        ->assertRedirect('/dashboard?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertDispatched(Verified::class);
});

test('invalid verification links are forbidden and do not verify', function (string $case) {
    $user = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();

    $url = match ($case) {
        'expired' => verificationUrl($user, now()->subMinute()),
        'wrong hash' => verificationUrl($user, hash: sha1('other@example.com')),
        'tampered signature' => preg_replace('/signature=[a-f0-9]+/', 'signature=deadbeef', verificationUrl($user)),
        'unsigned' => route('verification.verify', ['id' => $user->getKey(), 'hash' => sha1($user->email)]),
        'other user' => verificationUrl($other),
    };

    $this->actingAs($user)->get($url)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
})->with(['expired', 'wrong hash', 'tampered signature', 'unsigned', 'other user']);

test('guests are redirected to login when opening a verification link', function () {
    $user = User::factory()->unverified()->create();

    $this->get(verificationUrl($user))->assertRedirect(route('login'));

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('the verification mail can be resent and is throttled', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    $this->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);

    for ($i = 1; $i < 6; $i++) {
        $this->post(route('verification.send'))->assertRedirect();
    }

    $this->post(route('verification.send'))->assertTooManyRequests();
});

test('resending requires authentication', function () {
    $this->post(route('verification.send'))->assertRedirect(route('login'));
});

test('the verification link expires after 60 minutes', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return abs((int) $query['expires'] - now()->addMinutes(60)->timestamp) <= 5;
    });
});
