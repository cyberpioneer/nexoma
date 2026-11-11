<?php

use App\Models\CareerEntry;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Die Vue-Seite `Profile/Edit` liefert TASK-005; hier werden nur die Props geprueft.
    config(['inertia.testing.ensure_pages_exist' => false]);
});

function premiumUser(): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'stripe_subscription_status' => 'active',
        'stripe_current_period_end' => now()->addMonth(),
    ])->save();

    return $user;
}

/**
 * @return array<string, mixed>
 */
function validEntry(array $overrides = []): array
{
    return array_merge([
        'what' => 'Pflegefachmann',
        'where' => 'Klinikum Nord',
        'from_date' => '2015-09-01',
        'to_date' => '2019-08-31',
        'qualification_or_role' => 'Examen',
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function validProfile(array $overrides = []): array
{
    return array_merge([
        'preferred_language' => 'uk',
        'bio' => 'Erfahrene Pflegekraft mit Schwerpunkt Intensivpflege.',
        'search_location' => 'Bad Mergentheim',
        'search_radius' => 25,
    ], $overrides);
}

test('every profile and career route is paywalled', function () {
    $user = User::factory()->create();
    $entry = CareerEntry::factory()->for($user)->create();

    $this->actingAs($user)->get(route('profile.show'))->assertRedirect(route('billing.upgrade'));
    $this->actingAs($user)->putJson(route('profile.update'), validProfile())->assertStatus(402);
    $this->actingAs($user)->postJson(route('career-entries.store'), validEntry())->assertStatus(402);
    $this->actingAs($user)->putJson(route('career-entries.update', $entry), validEntry())->assertStatus(402);
    $this->actingAs($user)->deleteJson(route('career-entries.destroy', $entry))->assertStatus(402);

    expect($user->fresh()->bio)->toBeNull()
        ->and($user->careerEntries()->count())->toBe(1);
});

test('guests and unverified users cannot reach the profile', function () {
    $this->get(route('profile.show'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('profile.show'))->assertRedirect(route('verification.notice'));
});

test('the profile page shows only the own whitelisted data', function () {
    $user = premiumUser();
    $user->forceFill(validProfile())->save();
    CareerEntry::factory()->for($user)->create(['what' => 'Eigener Eintrag']);
    $other = premiumUser();
    CareerEntry::factory()->for($other)->create(['what' => 'Fremder Eintrag']);

    $response = $this->actingAs($user)->get(route('profile.show'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Profile/Edit')
        ->where('profile', validProfile())
        ->has('careerEntries', 1)
        ->where('careerEntries.0.what', 'Eigener Eintrag')
        ->has('careerEntries.0', fn (Assert $entry) => $entry->hasAll(['id', 'what', 'where', 'from_date', 'to_date', 'qualification_or_role']))
        ->where('maxCareerEntries', CareerEntry::MAX_PER_USER)
        ->where('maxBioLength', User::BIO_MAX_LENGTH)
        ->where('searchRadii', User::SEARCH_RADII));

    expect($response->getContent())->not->toContain('Fremder Eintrag')->not->toContain($other->email);
});

test('a subscriber saves the profile', function () {
    $user = premiumUser();

    $this->actingAs($user)->put(route('profile.update'), validProfile())->assertRedirect()->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->preferred_language)->toBe('uk')
        ->and($user->bio)->toBe('Erfahrene Pflegekraft mit Schwerpunkt Intensivpflege.')
        ->and($user->search_location)->toBe('Bad Mergentheim')
        ->and($user->search_radius)->toBe(25);
});

test('the bio limit counts characters and empty values are stored as null', function () {
    $user = premiumUser();

    $this->actingAs($user)->put(route('profile.update'), validProfile(['bio' => str_repeat('ü', 420)]))->assertSessionHasNoErrors();
    expect(mb_strlen($user->fresh()->bio))->toBe(420);

    $this->actingAs($user)->put(route('profile.update'), validProfile(['bio' => str_repeat('ü', 421)]))->assertSessionHasErrors('bio');

    $this->actingAs($user)->put(route('profile.update'), validProfile(['bio' => '   ', 'search_location' => '', 'search_radius' => null]))->assertSessionHasNoErrors();
    $user->refresh();
    expect($user->bio)->toBeNull()->and($user->search_location)->toBeNull()->and($user->search_radius)->toBeNull();
});

test('invalid profile values are rejected', function (array $overrides, string $field) {
    $user = premiumUser();

    $this->actingAs($user)->put(route('profile.update'), validProfile($overrides))->assertSessionHasErrors($field);

    expect($user->fresh()->preferred_language)->toBeNull();
})->with([
    'unknown language' => [['preferred_language' => 'fr'], 'preferred_language'],
    'language as array' => [['preferred_language' => ['de']], 'preferred_language'],
    'missing language' => [['preferred_language' => null], 'preferred_language'],
    'radius not allowed' => [['search_radius' => 7], 'search_radius'],
    'radius as text' => [['search_radius' => 'abc'], 'search_radius'],
    'location too long' => [['search_location' => str_repeat('a', 121)], 'search_location'],
    'bio as array' => [['bio' => ['x']], 'bio'],
]);

test('all supported languages and radii are accepted', function () {
    $user = premiumUser();

    foreach (array_keys(config('app.supported_locales')) as $language) {
        $this->actingAs($user)->put(route('profile.update'), validProfile(['preferred_language' => $language]))->assertSessionHasNoErrors();
    }

    foreach (User::SEARCH_RADII as $radius) {
        $this->actingAs($user)->put(route('profile.update'), validProfile(['search_radius' => $radius]))->assertSessionHasNoErrors();
    }

    expect(User::SEARCH_RADII)->toBe([5, 10, 25, 50, 100, 200]);
});

test('the profile update cannot change protected user fields', function () {
    $user = premiumUser();
    $before = $user->only(['name', 'email', 'password', 'locale', 'consent_version', 'stripe_customer_id', 'stripe_subscription_status', 'email_verified_at']);

    $this->actingAs($user)->put(route('profile.update'), validProfile([
        'name' => 'Hacker',
        'email' => 'hacker@example.test',
        'password' => 'new-password',
        'locale' => 'ru',
        'consent_version' => 'x',
        'stripe_customer_id' => 'cus_evil',
        'stripe_subscription_status' => 'canceled',
        'email_verified_at' => null,
        'id' => 999,
    ]))->assertSessionHasNoErrors();

    expect($user->fresh()->only(array_keys($before)))->toEqual($before)
        ->and($user->fresh()->preferred_language)->toBe('uk');
});

test('a subscriber creates an entry that always belongs to the logged in user', function () {
    $user = premiumUser();
    $other = premiumUser();

    $this->actingAs($user)->post(route('career-entries.store'), validEntry(['user_id' => $other->id]))->assertRedirect()->assertSessionHasNoErrors();

    $entry = CareerEntry::query()->sole();
    expect($entry->user_id)->toBe($user->id)
        ->and($entry->where)->toBe('Klinikum Nord')
        ->and($entry->from_date->format('Y-m-d'))->toBe('2015-09-01')
        ->and($other->careerEntries()->count())->toBe(0);
});

test('an ongoing entry may omit the end date', function () {
    $user = premiumUser();

    $this->actingAs($user)->post(route('career-entries.store'), validEntry(['to_date' => null]))->assertSessionHasNoErrors();

    expect($user->careerEntries()->sole()->to_date)->toBeNull();
});

test('invalid career entries are rejected', function (array $overrides, string $field) {
    $user = premiumUser();

    $this->actingAs($user)->post(route('career-entries.store'), validEntry($overrides))->assertSessionHasErrors($field);

    expect($user->careerEntries()->count())->toBe(0);
})->with([
    'missing what' => [['what' => ''], 'what'],
    'what too long' => [['what' => str_repeat('a', 151)], 'what'],
    'missing where' => [['where' => null], 'where'],
    'where too long' => [['where' => str_repeat('a', 151)], 'where'],
    'role too long' => [['qualification_or_role' => str_repeat('a', 201)], 'qualification_or_role'],
    'missing role' => [['qualification_or_role' => ''], 'qualification_or_role'],
    'bad date format' => [['from_date' => '01.09.2015'], 'from_date'],
    'future start' => [['from_date' => '2999-01-01', 'to_date' => null], 'from_date'],
    'implausible start' => [['from_date' => '1800-01-01'], 'from_date'],
    'end before start' => [['to_date' => '2010-01-01'], 'to_date'],
    'future end' => [['to_date' => '2999-01-01'], 'to_date'],
    'array as text' => [['what' => ['x']], 'what'],
]);

test('the number of entries per user is limited by the policy', function () {
    $user = premiumUser();
    CareerEntry::factory()->count(CareerEntry::MAX_PER_USER)->for($user)->create();

    $this->actingAs($user)->post(route('career-entries.store'), validEntry())->assertForbidden();

    expect($user->careerEntries()->count())->toBe(CareerEntry::MAX_PER_USER);
});

test('a user updates and deletes own entries', function () {
    $user = premiumUser();
    $entry = CareerEntry::factory()->for($user)->create();

    $this->actingAs($user)->put(route('career-entries.update', $entry), validEntry(['what' => 'Geändert', 'user_id' => 999]))->assertSessionHasNoErrors();
    $entry->refresh();
    expect($entry->what)->toBe('Geändert')->and($entry->user_id)->toBe($user->id);

    $this->actingAs($user)->delete(route('career-entries.destroy', $entry))->assertRedirect();
    expect(CareerEntry::query()->count())->toBe(0);
});

test('foreign entries cannot be changed or deleted even with a subscription', function () {
    $user = premiumUser();
    $victim = premiumUser();
    $entry = CareerEntry::factory()->for($victim)->create(['what' => 'Original']);

    $this->actingAs($user)->putJson(route('career-entries.update', $entry), validEntry(['what' => 'Übernommen']))->assertForbidden();
    $this->actingAs($user)->deleteJson(route('career-entries.destroy', $entry))->assertForbidden();

    $entry->refresh();
    expect($entry->what)->toBe('Original')->and($entry->user_id)->toBe($victim->id);
});

test('the policy allows only the owner', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $entry = CareerEntry::factory()->for($owner)->create();

    foreach (['view', 'update', 'delete'] as $ability) {
        expect(Gate::forUser($owner)->allows($ability, $entry))->toBeTrue()
            ->and(Gate::forUser($stranger)->allows($ability, $entry))->toBeFalse();
    }
});

test('career entries cannot be mass assigned to another user and the where column works', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $entry = $owner->careerEntries()->create(validEntry(['user_id' => $stranger->id]));

    expect($entry->user_id)->toBe($owner->id)
        ->and(CareerEntry::query()->where('where', 'Klinikum Nord')->count())->toBe(1);
});

test('deleting a user removes the career entries', function () {
    $user = User::factory()->create();
    CareerEntry::factory()->count(2)->for($user)->create();

    $user->delete();

    expect(CareerEntry::query()->count())->toBe(0);
});

test('the profile routes are rate limited', function () {
    $user = premiumUser();

    foreach (range(1, AppServiceProvider::CAREER_PROFILE_ATTEMPTS_PER_MINUTE) as $attempt) {
        $this->actingAs($user)->put(route('profile.update'), validProfile())->assertRedirect();
    }

    $this->actingAs($user)->put(route('profile.update'), validProfile())->assertTooManyRequests();
});
