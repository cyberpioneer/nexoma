<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeStripeHttpClient;

beforeEach(function () {
    config([
        'services.stripe.secret' => 'sk_test_fake_secret',
        'services.stripe.price_id' => 'price_nexoma_premium',
        'services.stripe.grace_days' => 3,
    ]);
    $this->stripe = FakeStripeHttpClient::install();
});

afterEach(function () {
    FakeStripeHttpClient::uninstall();
});

function fakeCheckoutFlow(FakeStripeHttpClient $stripe): void
{
    $stripe->respond('POST', '/v1/customers', ['id' => 'cus_123', 'object' => 'customer'])
        ->respond('POST', '/v1/checkout/sessions', ['id' => 'cs_1', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_1'])
        ->respond('POST', '/v1/billing_portal/sessions', ['id' => 'bps_1', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/bps_1']);
}

test('checkout creates a customer and a subscription session with server controlled values', function () {
    fakeCheckoutFlow($this->stripe);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('billing.checkout'), [
            'price' => 'price_evil',
            'customer' => 'cus_foreign',
            'mode' => 'payment',
            'success_url' => 'https://evil.test/',
            'line_items' => [['price' => 'price_evil', 'quantity' => 99]],
        ])
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_1');

    $session = $this->stripe->requestsTo('/v1/checkout/sessions')[0]['params'];

    expect($session['mode'])->toBe('subscription')
        ->and($session['customer'])->toBe('cus_123')
        ->and($session['client_reference_id'])->toBe((string) $user->id)
        ->and($session['line_items'])->toBe([['price' => 'price_nexoma_premium', 'quantity' => 1]])
        ->and($session['success_url'])->toBe(route('billing.success'))
        ->and($session['cancel_url'])->toBe(route('billing.cancel'))
        ->and($user->fresh()->stripe_customer_id)->toBe('cus_123');
});

test('an existing stripe customer is reused', function () {
    fakeCheckoutFlow($this->stripe);
    $user = User::factory()->create();
    $user->forceFill(['stripe_customer_id' => 'cus_existing'])->save();

    $this->actingAs($user)->post(route('billing.checkout'))->assertRedirect();

    expect($this->stripe->requestsTo('/v1/customers'))->toBeEmpty()
        ->and($this->stripe->requestsTo('/v1/checkout/sessions')[0]['params']['customer'])->toBe('cus_existing');
});

test('a user with an active subscription is not sent to stripe again', function () {
    fakeCheckoutFlow($this->stripe);
    $user = User::factory()->create();
    $user->forceFill(['stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();

    $this->actingAs($user)->post(route('billing.checkout'))
        ->assertRedirect(route('billing.upgrade'))
        ->assertSessionHas('status', __('ui.billing.already_subscribed'));

    expect($this->stripe->requests)->toBeEmpty();
});

test('a user with an overdue subscription is sent to the customer portal instead of a second checkout', function () {
    fakeCheckoutFlow($this->stripe);
    $user = User::factory()->create();
    $user->forceFill(['stripe_customer_id' => 'cus_9', 'stripe_subscription_id' => 'sub_9', 'stripe_subscription_status' => 'past_due'])->save();

    $this->actingAs($user)->post(route('billing.checkout'))->assertRedirect('https://billing.stripe.com/p/session/bps_1');

    expect($this->stripe->requestsTo('/v1/checkout/sessions'))->toBeEmpty();
});

test('checkout without stripe configuration fails closed without calling stripe', function (string $missing) {
    config(['services.stripe.'.$missing => null]);

    $this->actingAs(User::factory()->create())->post(route('billing.checkout'))
        ->assertRedirect(route('billing.upgrade'))
        ->assertSessionHasErrors(['billing' => __('ui.billing.unavailable')]);

    expect($this->stripe->requests)->toBeEmpty();
})->with(['secret', 'price_id']);

test('a stripe api error is reported generically without leaking details', function () {
    $this->stripe->respond('POST', '/v1/customers', ['error' => ['type' => 'api_error', 'message' => 'internal sk_test_fake_secret detail']], 500);

    $response = $this->actingAs(User::factory()->create())->post(route('billing.checkout'));

    $response->assertRedirect(route('billing.upgrade'))->assertSessionHasErrors(['billing' => __('ui.billing.unavailable')]);
    expect(json_encode(session()->all()))->not->toContain('sk_test_fake_secret');
});

test('the customer portal needs an existing stripe customer', function () {
    fakeCheckoutFlow($this->stripe);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('billing.portal'))
        ->assertRedirect(route('billing.upgrade'))
        ->assertSessionHasErrors(['billing' => __('ui.billing.no_subscription')]);

    $user->forceFill(['stripe_customer_id' => 'cus_77'])->save();

    $this->actingAs($user)->post(route('billing.portal'))->assertRedirect('https://billing.stripe.com/p/session/bps_1');

    expect($this->stripe->requestsTo('/v1/billing_portal/sessions')[0]['params']['customer'])->toBe('cus_77');
});

test('a non https stripe url is rejected', function () {
    $this->stripe->respond('POST', '/v1/customers', ['id' => 'cus_1', 'object' => 'customer'])
        ->respond('POST', '/v1/checkout/sessions', ['id' => 'cs_1', 'object' => 'checkout.session', 'url' => 'javascript:alert(1)']);

    $this->actingAs(User::factory()->create())->post(route('billing.checkout'))
        ->assertRedirect(route('billing.upgrade'))
        ->assertSessionHasErrors('billing');
});

test('the return pages only show a message and never activate the subscription', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('billing.success'))
        ->assertRedirect(route('billing.upgrade'))
        ->assertSessionHas('status', __('ui.billing.success_pending'));
    $this->actingAs($user)->get(route('billing.cancel'))
        ->assertRedirect(route('billing.upgrade'))
        ->assertSessionHas('status', __('ui.billing.canceled'));

    expect($user->fresh()->hasActiveSubscription())->toBeFalse();

    $user->forceFill(['stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();

    $this->actingAs($user)->get(route('billing.success'))->assertSessionHas('status', __('ui.billing.success_active'));
});

test('after the return from stripe the upgrade page asks the client to wait for the webhook', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('billing.success'))->assertSessionHas('billing_pending', true);
    $this->actingAs($user)->followingRedirects()->get(route('billing.success'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Upgrade')
            ->where('isSubscribed', false)
            ->where('awaitingConfirmation', true)
            ->etc());

    $this->actingAs($user)->get(route('billing.upgrade'))
        ->assertInertia(fn (Assert $page) => $page->where('awaitingConfirmation', false)->etc());

    $user->forceFill(['stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();

    $this->actingAs($user)->get(route('billing.success'))->assertSessionHas('billing_pending', false);
});

test('the polling of the upgrade page stays below the rate limit of the billing routes', function () {
    preg_match('/POLL_DELAYS_MS = \[([^\]]+)\]/', (string) file_get_contents(resource_path('js/pages/Billing/Upgrade.vue')), $matches);
    $delays = array_map('intval', explode(',', $matches[1] ?? ''));

    // Rueckkehr (success) und Weiterleitung (upgrade) zaehlen ebenfalls gegen das Limit.
    expect($delays)->not->toBeEmpty()
        ->and(count($delays) + 2)->toBeLessThan(AppServiceProvider::BILLING_ATTEMPTS_PER_MINUTE)
        ->and(array_sum($delays))->toBeGreaterThanOrEqual(30000)
        ->and(min($delays))->toBeGreaterThanOrEqual(3000);
});

test('billing routes require a verified login', function () {
    $this->post(route('billing.checkout'))->assertRedirect(route('login'));
    $this->post(route('billing.portal'))->assertRedirect(route('login'));
    $this->get(route('billing.success'))->assertRedirect(route('login'));
    $this->get(route('billing.cancel'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->post(route('billing.checkout'))->assertRedirect(route('verification.notice'));

    expect($this->stripe->requests)->toBeEmpty();
});

test('billing endpoints are rate limited per user', function () {
    $this->actingAs(User::factory()->create());

    foreach (range(1, AppServiceProvider::BILLING_ATTEMPTS_PER_MINUTE) as $attempt) {
        $this->get(route('billing.cancel'))->assertRedirect(route('billing.upgrade'));
    }

    $this->get(route('billing.cancel'))->assertTooManyRequests();
});

test('hasActiveSubscription is true only for paid statuses within the period plus grace', function (?string $status, ?int $periodEndDays, bool $expected) {
    $user = User::factory()->make([]);
    $user->stripe_subscription_status = $status;
    $user->stripe_current_period_end = $periodEndDays === null ? null : now()->addDays($periodEndDays);

    expect($user->hasActiveSubscription())->toBe($expected);
})->with([
    'no subscription' => [null, null, false],
    'active, period running' => ['active', 20, true],
    'trialing' => ['trialing', 5, true],
    'active, period unknown' => ['active', null, false],
    'active, expired within grace' => ['active', -2, true],
    'active, expired beyond grace' => ['active', -4, false],
    'past due' => ['past_due', 20, false],
    'canceled' => ['canceled', 20, false],
    'unpaid' => ['unpaid', 20, false],
    'incomplete' => ['incomplete', 20, false],
]);

test('subscription columns cannot be mass assigned and are not exposed to the client', function () {
    $user = User::query()->create([
        'name' => 'Test Person',
        'email' => 'mass@example.test',
        'password' => 'secret-password',
        'stripe_customer_id' => 'cus_x',
        'stripe_subscription_id' => 'sub_x',
        'stripe_subscription_status' => 'active',
        'stripe_current_period_end' => now()->addYear(),
    ]);

    expect($user->fresh()->hasActiveSubscription())->toBeFalse()
        ->and($user->fresh()->stripe_customer_id)->toBeNull()
        ->and(array_keys($user->toArray()))->not->toContain('stripe_customer_id', 'stripe_subscription_status');

    $subscriber = User::factory()->create();
    $subscriber->forceFill(['stripe_customer_id' => 'cus_visible', 'stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();

    $this->actingAs($subscriber)->get(route('billing.upgrade'))
        ->assertInertia(fn ($page) => $page->where('auth.user', fn ($shared) => ! str_contains(json_encode($shared), 'stripe')));
});
