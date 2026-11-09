<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function enableProductionUrlGeneration(): void
{
    config(['app.url' => 'https://nexoma.example']);

    // Die Umgebung wird nur kurz umgestellt: Die CSRF-Pruefung wird im Testlauf ueber `testing` uebersprungen.
    app()->detectEnvironment(fn (): string => 'production');
    (fn () => $this->configureUrlGeneration())->call(new AppServiceProvider(app()));
    app()->detectEnvironment(fn (): string => 'testing');
}

test('in production the reset link uses APP_URL even with a spoofed host header', function () {
    Notification::fake();
    $user = User::factory()->create();
    enableProductionUrlGeneration();

    $this->post('http://evil.test/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'https://nexoma.example/reset-password/') && ! str_contains($url, 'evil.test');
    });
});

test('in production the verification link uses APP_URL over https', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    enableProductionUrlGeneration();

    $this->actingAs($user)->post('http://evil.test/email/verification-notification');

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'https://nexoma.example/email/verify/') && ! str_contains($url, 'evil.test');
    });
});
