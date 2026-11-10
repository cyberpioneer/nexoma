<?php

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['app.locale' => 'de']);
});

test('a guest can switch the locale, it is stored in the session and the user returns to the previous page', function () {
    $this->from(route('login'))
        ->post(route('locale.update'), ['locale' => 'uk'])
        ->assertRedirect(route('login'))
        ->assertSessionHas(SetLocale::SESSION_KEY, 'uk');

    $this->assertGuest();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('locale', 'uk'));
});

test('a logged-in user stores the locale on the account and in the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => 'it'])
        ->assertRedirect()
        ->assertSessionHas(SetLocale::SESSION_KEY, 'it');

    expect($user->fresh()->locale)->toBe('it');

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('locale', 'it'));
});

test('an invalid locale is rejected and nothing is stored', function (mixed $value) {
    $user = User::factory()->withLocale('en')->create();

    $this->actingAs($user)
        ->post(route('locale.update'), ['locale' => $value])
        ->assertSessionHasErrors('locale')
        ->assertSessionMissing(SetLocale::SESSION_KEY);

    expect($user->fresh()->locale)->toBe('en');
})->with([
    'unknown' => 'fr',
    'uppercase' => 'DE',
    'sql' => "de' OR '1'='1",
    'array' => [['de']],
    'empty' => '',
    'null' => null,
]);

test('an invalid locale yields 422 for json requests', function () {
    $this->postJson(route('locale.update'), ['locale' => 'xx'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('locale');

    $this->postJson(route('locale.update'))->assertUnprocessable();
});

test('the locale endpoint is throttled per ip for guests', function () {
    for ($i = 0; $i < AppServiceProvider::LOCALE_ATTEMPTS_PER_MINUTE; $i++) {
        $this->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
    }

    $this->post(route('locale.update'), ['locale' => 'en'])->assertTooManyRequests();
});

test('the locale endpoint is throttled per user, not shared between users', function () {
    [$first, $second] = User::factory()->count(2)->create()->all();

    for ($i = 0; $i < AppServiceProvider::LOCALE_ATTEMPTS_PER_MINUTE; $i++) {
        $this->actingAs($first)->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
    }

    $this->actingAs($first)->post(route('locale.update'), ['locale' => 'en'])->assertTooManyRequests();
    $this->actingAs($second)->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();
});

test('the locale route has no auth middleware but is throttled', function () {
    $middleware = Route::getRoutes()->getByName('locale.update')->gatherMiddleware();

    expect($middleware)->toContain('throttle:locale')
        ->and($middleware)->not->toContain('auth');
});

test('a new user receives the resolved locale', function (array $headers, array $session, string $expected) {
    Notification::fake();

    $this->withSession($session)->post(route('register.store'), [
        'name' => 'Max Mustermann',
        'email' => 'max@example.com',
        'password' => 'secret-password-1',
        'password_confirmation' => 'secret-password-1',
        'consent' => '1',
    ], $headers)->assertRedirect('/dashboard');

    expect(User::where('email', 'max@example.com')->firstOrFail()->locale)->toBe($expected);
})->with([
    'session' => [[], [SetLocale::SESSION_KEY => 'ru'], 'ru'],
    'accept-language' => [['Accept-Language' => 'es-ES,es;q=0.9'], [], 'es'],
    'default' => [['Accept-Language' => ''], [], 'de'],
]);

test('a locale in the registration request is never assigned', function () {
    Notification::fake();

    $this->withSession([SetLocale::SESSION_KEY => 'en'])->post(route('register.store'), [
        'name' => 'Max Mustermann',
        'email' => 'max@example.com',
        'password' => 'secret-password-1',
        'password_confirmation' => 'secret-password-1',
        'consent' => '1',
        'locale' => 'ru',
    ]);

    expect(User::firstOrFail()->locale)->toBe('en');
});

test('preferredLocale returns only supported locales', function (?string $stored, ?string $expected) {
    expect(User::factory()->make(['locale' => $stored])->preferredLocale())->toBe($expected);
})->with([
    'supported' => ['ru', 'ru'],
    'none' => [null, null],
    'unsupported' => ['fr', null],
    'empty' => ['', null],
]);

test('mails to a user are rendered in the stored user locale', function (string $locale, string $verifySubject, string $resetSubject) {
    $user = User::factory()->withLocale($locale)->create();
    $transport = Mail::mailer('array')->getSymfonyTransport();

    $user->sendEmailVerificationNotification();
    $user->sendPasswordResetNotification('token');

    $messages = $transport->messages();

    expect($messages)->toHaveCount(2)
        ->and($messages[0]->getOriginalMessage()->getSubject())->toBe($verifySubject)
        ->and($messages[1]->getOriginalMessage()->getSubject())->toBe($resetSubject)
        ->and(app()->getLocale())->toBe('de');
})->with([
    'ru' => ['ru', 'Подтвердите адрес электронной почты', 'Сброс пароля'],
    'uk' => ['uk', 'Підтвердьте адресу електронної пошти', 'Скидання пароля'],
    'es' => ['es', 'Confirma tu correo electrónico', 'Restablece tu contraseña'],
    'it' => ['it', 'Conferma il tuo indirizzo e-mail', 'Reimposta la tua password'],
    'de' => ['de', 'E-Mail-Adresse bestätigen', 'Passwort zurücksetzen'],
    'en' => ['en', 'Verify your email address', 'Reset your password'],
]);
