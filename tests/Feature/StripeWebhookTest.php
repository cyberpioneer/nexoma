<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeStripeHttpClient;

const WEBHOOK_SECRET = 'whsec_test_secret';

beforeEach(function () {
    config([
        'services.stripe.secret' => 'sk_test_fake_secret',
        'services.stripe.price_id' => 'price_nexoma_premium',
        'services.stripe.webhook_secret' => WEBHOOK_SECRET,
        'services.stripe.grace_days' => 3,
    ]);
    $this->stripe = FakeStripeHttpClient::install();
    $this->user = User::factory()->create();
    $this->user->forceFill(['stripe_customer_id' => 'cus_1'])->save();
});

afterEach(function () {
    FakeStripeHttpClient::uninstall();
});

/**
 * @return array<string, mixed>
 */
function eventPayload(string $type = 'customer.subscription.updated', string $subscriptionId = 'sub_1', string $status = 'active'): array
{
    return [
        'id' => 'evt_1',
        'object' => 'event',
        'type' => $type,
        'created' => time(),
        'data' => ['object' => ['id' => $subscriptionId, 'object' => 'subscription', 'status' => $status]],
    ];
}

/**
 * @return array<string, mixed>
 */
function subscriptionBody(string $id = 'sub_1', string $status = 'active', string $customer = 'cus_1', string $price = 'price_nexoma_premium', ?int $periodEnd = null): array
{
    return [
        'id' => $id,
        'object' => 'subscription',
        'status' => $status,
        'customer' => $customer,
        'items' => ['object' => 'list', 'data' => [
            ['id' => 'si_1', 'object' => 'subscription_item', 'current_period_end' => $periodEnd ?? now()->addMonth()->getTimestamp(), 'price' => ['id' => $price, 'object' => 'price']],
        ]],
    ];
}

function signedHeaders(string $payload, string $secret = WEBHOOK_SECRET, ?int $timestamp = null): array
{
    $timestamp ??= time();

    return ['Stripe-Signature' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret)];
}

function postWebhook(mixed $test, array $event, ?array $headers = null): TestResponse
{
    $payload = json_encode($event);

    return $test->call('POST', route('stripe.webhook'), [], [], [], array_merge(
        ['CONTENT_TYPE' => 'application/json'],
        collect($headers ?? signedHeaders($payload))->mapWithKeys(fn ($value, $name) => ['HTTP_'.strtoupper(str_replace('-', '_', $name)) => $value])->all(),
    ), $payload);
}

test('the webhook route is public without session or csrf but throttled', function () {
    $middleware = Route::getRoutes()->getByName('stripe.webhook')->gatherMiddleware();

    expect($middleware)->not->toContain('web', 'auth')
        ->and($middleware)->toContain('throttle:stripe-webhook');
});

test('a valid subscription event syncs the state from stripe, not from the payload', function () {
    $periodEnd = now()->addMonth()->getTimestamp();
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody(periodEnd: $periodEnd));

    postWebhook($this, eventPayload())->assertOk();

    $user = $this->user->fresh();
    expect($user->stripe_subscription_id)->toBe('sub_1')
        ->and($user->stripe_subscription_status)->toBe('active')
        ->and($user->stripe_current_period_end->getTimestamp())->toBe($periodEnd)
        ->and($user->hasActiveSubscription())->toBeTrue();
});

test('the payload status is ignored when stripe reports another status', function () {
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody(status: 'canceled'));

    postWebhook($this, eventPayload(status: 'active'))->assertOk();

    expect($this->user->fresh()->hasActiveSubscription())->toBeFalse()
        ->and($this->user->fresh()->stripe_subscription_status)->toBe('canceled');
});

test('a deleted subscription removes access', function () {
    $this->user->forceFill(['stripe_subscription_id' => 'sub_1', 'stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody(status: 'canceled'));

    postWebhook($this, eventPayload('customer.subscription.deleted', status: 'canceled'))->assertOk();

    expect($this->user->fresh()->hasActiveSubscription())->toBeFalse();
});

test('requests without a valid signature are rejected and change nothing', function (string $case) {
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody());
    $payload = json_encode(eventPayload());

    $headers = match ($case) {
        'missing header' => [],
        'empty header' => ['Stripe-Signature' => ''],
        'garbage header' => ['Stripe-Signature' => 'nonsense'],
        'wrong secret' => signedHeaders($payload, 'whsec_other'),
        'stale timestamp' => signedHeaders($payload, WEBHOOK_SECRET, time() - 3600),
        'tampered body' => signedHeaders(str_replace('sub_1', 'sub_2', $payload)),
    };

    postWebhook($this, eventPayload(), $headers)->assertStatus(400);

    expect($this->stripe->requests)->toBeEmpty()
        ->and($this->user->fresh()->stripe_subscription_status)->toBeNull();
})->with(['missing header', 'empty header', 'garbage header', 'wrong secret', 'stale timestamp', 'tampered body']);

test('without a configured signing secret the webhook fails closed', function (mixed $secret) {
    config(['services.stripe.webhook_secret' => $secret]);
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody());

    postWebhook($this, eventPayload(), signedHeaders(json_encode(eventPayload()), ''))->assertStatus(500);

    expect($this->stripe->requests)->toBeEmpty()
        ->and($this->user->fresh()->stripe_subscription_status)->toBeNull();
})->with([null, '']);

test('unrelated event types are acknowledged and ignored', function () {
    postWebhook($this, eventPayload('invoice.paid'))->assertOk();

    expect($this->stripe->requests)->toBeEmpty();
});

test('subscriptions of another price are ignored', function () {
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody(price: 'price_other_product'));

    postWebhook($this, eventPayload())->assertOk();

    expect($this->user->fresh()->stripe_subscription_status)->toBeNull();
});

test('a subscription of an unknown customer is ignored', function () {
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody(customer: 'cus_unknown'));

    postWebhook($this, eventPayload())->assertOk();

    expect($this->user->fresh()->stripe_subscription_status)->toBeNull();
});

test('an inactive older subscription does not overwrite the current one', function () {
    $this->user->forceFill(['stripe_subscription_id' => 'sub_new', 'stripe_subscription_status' => 'active', 'stripe_current_period_end' => now()->addMonth()])->save();
    $this->stripe->respond('GET', '/v1/subscriptions/sub_old', subscriptionBody(id: 'sub_old', status: 'canceled'));

    postWebhook($this, eventPayload('customer.subscription.deleted', 'sub_old', 'canceled'))->assertOk();

    $user = $this->user->fresh();
    expect($user->stripe_subscription_id)->toBe('sub_new')
        ->and($user->hasActiveSubscription())->toBeTrue();
});

test('a failing stripe lookup answers 500 so that stripe retries', function () {
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', ['error' => ['type' => 'api_error', 'message' => 'boom']], 500);

    postWebhook($this, eventPayload())->assertStatus(500);

    expect($this->user->fresh()->stripe_subscription_status)->toBeNull();
});

test('an unknown subscription at stripe is acknowledged instead of retried forever', function () {
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', ['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such subscription']], 404);

    postWebhook($this, eventPayload())->assertOk();

    expect($this->user->fresh()->stripe_subscription_status)->toBeNull();
});

test('replaying the same event is harmless', function () {
    $this->stripe->respond('GET', '/v1/subscriptions/sub_1', subscriptionBody());

    postWebhook($this, eventPayload())->assertOk();
    postWebhook($this, eventPayload())->assertOk();

    expect($this->user->fresh()->hasActiveSubscription())->toBeTrue()
        ->and(User::query()->where('stripe_subscription_id', 'sub_1')->count())->toBe(1);
});
