<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

function subscribedUser(): User
{
    $user = User::factory()->create();
    $user->forceFill([
        'stripe_customer_id' => 'cus_paywall_'.$user->id,
        'stripe_subscription_id' => 'sub_paywall_'.$user->id,
        'stripe_subscription_status' => 'active',
        'stripe_current_period_end' => now()->addMonth(),
    ])->save();

    return $user;
}

beforeEach(function () {
    Route::middleware(['web', 'auth', 'verified', 'subscribed'])->get('/_test/premium', fn () => response()->json(['premium' => true]));
    Route::middleware(['web', 'auth', 'verified', 'subscribed'])->post('/_test/premium', fn () => response()->json(['premium' => true]));
});

test('a user without subscription is redirected to the upgrade page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/_test/premium')->assertRedirect(route('billing.upgrade'));
    $this->actingAs($user)->post('/_test/premium')->assertRedirect(route('billing.upgrade'));
});

test('json clients receive 402 without further details', function () {
    $this->actingAs(User::factory()->create())->getJson('/_test/premium')
        ->assertStatus(402)
        ->assertExactJson(['message' => __('ui.billing.subscription_required')]);
});

test('inertia navigation is redirected and the request headers cannot bypass the paywall', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()), 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/_test/premium')->assertRedirect(route('billing.upgrade'));

    foreach (['X-Subscribed' => '1', 'X-Premium' => 'true', 'Authorization' => 'Bearer premium'] as $header => $value) {
        $this->actingAs($user)->withHeaders([$header => $value])->get('/_test/premium')->assertRedirect(route('billing.upgrade'));
    }

    $this->actingAs($user)->get('/_test/premium?subscribed=1&stripe_subscription_status=active')->assertRedirect(route('billing.upgrade'));
});

test('inactive subscription states are blocked', function (string $status, int $periodEndDays) {
    $user = User::factory()->create();
    $user->forceFill(['stripe_subscription_status' => $status, 'stripe_current_period_end' => now()->addDays($periodEndDays)])->save();

    $this->actingAs($user)->getJson('/_test/premium')->assertStatus(402);
})->with([
    'canceled' => ['canceled', 10],
    'past due' => ['past_due', 10],
    'active but expired' => ['active', -10],
]);

test('a subscriber passes', function () {
    $this->actingAs(subscribedUser())->getJson('/_test/premium')->assertOk()->assertJson(['premium' => true]);
    $this->actingAs(subscribedUser())->get('/_test/premium')->assertOk();
});

test('guests and unverified users are stopped before the paywall', function () {
    $this->get('/_test/premium')->assertRedirect(route('login'));
    $this->getJson('/_test/premium')->assertUnauthorized();
    $this->actingAs(User::factory()->unverified()->create())->get('/_test/premium')->assertRedirect(route('verification.notice'));
});

test('release 1.1.0 features stay free for verified users without subscription', function (string $routeName, array $parameters) {
    $url = route($routeName, $parameters);

    $this->actingAs(User::factory()->create())->get($url)->assertOk();
})->with([
    'dashboard' => ['dashboard', []],
    'job search' => ['jobs.index', []],
    'saved jobs' => ['saved-jobs.index', []],
    'connected apps' => ['connected-apps.index', []],
    'upgrade page' => ['billing.upgrade', []],
]);

test('only the intended premium routes carry the paywall middleware and no release 1.1.0 route does', function () {
    $paywalled = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => ! str_starts_with($route->uri(), '_test/')
            && in_array('subscribed', $route->gatherMiddleware(), true))
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->sort()->values()->all();

    // Neue Premium-Routen (ab TASK-004) hier bewusst ergaenzen; Routen aus Release 1.1.0 duerfen nie erscheinen.
    $expected = [
        'DELETE career-entries/{careerEntry}',
        'GET|HEAD profile',
        'POST career-entries',
        'POST profile/optimize-bio',
        'PUT career-entries/{careerEntry}',
        'PUT profile',
    ];

    expect($paywalled)->toBe($expected);
});

test('the purchase and management endpoints are reachable without subscription', function () {
    foreach (['billing.upgrade', 'billing.checkout', 'billing.portal', 'billing.success', 'billing.cancel'] as $name) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('auth', 'verified')
            ->not->toContain('subscribed');
    }
});

test('premium route prefixes must carry the paywall middleware', function () {
    $premiumPrefixes = ['profile', 'career-entries', 'saved-briefings', 'audio-briefings'];

    $unprotected = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route): bool => collect($premiumPrefixes)->contains(
            fn (string $prefix): bool => $route->uri() === $prefix || str_starts_with($route->uri(), $prefix.'/'),
        ))
        ->reject(fn (LaravelRoute $route): bool => in_array('subscribed', $route->gatherMiddleware(), true))
        ->map(fn (LaravelRoute $route): string => implode('|', $route->methods()).' '.$route->uri())
        ->values()->all();

    expect($unprotected)->toBe([]);
});

test('the upgrade page exposes only whitelisted props', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('billing.upgrade'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Upgrade')
            ->where('isSubscribed', false)
            ->where('canManage', false));

    $subscriber = subscribedUser();

    $response = $this->actingAs($subscriber)->get(route('billing.upgrade'));
    $response->assertInertia(fn (Assert $page) => $page
        ->where('isSubscribed', true)
        ->where('canManage', true));

    expect($response->getContent())->not->toContain('cus_paywall')->not->toContain('sub_paywall');
});
