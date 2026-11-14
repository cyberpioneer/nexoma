<?php

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\Exception\ApiErrorException;
use Tests\Support\FakeStripeHttpClient;

beforeEach(function () {
    config([
        'services.stripe.secret' => 'sk_test_fake_secret',
        'services.stripe.price_id' => 'price_nexoma_premium',
    ]);
    $this->stripe = FakeStripeHttpClient::install();
});

afterEach(function () {
    FakeStripeHttpClient::uninstall();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function stripeInvoice(array $overrides = []): array
{
    return array_replace([
        'id' => 'in_1',
        'object' => 'invoice',
        'number' => 'L52IHWLK-0001',
        'created' => 1794590000,
        'total' => 495,
        'currency' => 'eur',
        'status' => 'paid',
        'customer' => 'cus_inv',
        'customer_email' => 'secret.customer@example.test',
        'hosted_invoice_url' => 'https://invoice.stripe.com/i/acct_1/live_abc',
        'invoice_pdf' => 'https://pay.stripe.com/invoice/acct_1/live_abc/pdf',
    ], $overrides);
}

/**
 * @param  list<array<string, mixed>>  $invoices
 * @return array<string, mixed>
 */
function stripeInvoiceList(array $invoices): array
{
    return ['object' => 'list', 'data' => $invoices, 'has_more' => false, 'url' => '/v1/invoices'];
}

function userWithCustomer(): User
{
    return User::factory()->create(['stripe_customer_id' => 'cus_inv']);
}

test('the invoice list needs a verified login', function () {
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('invoices.index'))->assertRedirect(route('verification.notice'));
});

test('a user without a stripe customer sees an empty list and no call goes to stripe', function () {
    $this->actingAs(User::factory()->create())->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Index')
            ->where('invoices', [])
            ->where('unavailable', false));

    expect($this->stripe->requestsTo('/v1/invoices'))->toBe([]);
});

test('invoices are listed for the own customer only and reduced to whitelisted fields', function () {
    $this->stripe->respond('GET', '/v1/invoices', stripeInvoiceList([
        stripeInvoice(),
        stripeInvoice(['id' => 'in_2', 'number' => 'L52IHWLK-0002', 'status' => 'open', 'total' => 1250, 'currency' => 'usd']),
    ]));

    $response = $this->actingAs(userWithCustomer())->get(route('invoices.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Invoices/Index')
        ->where('unavailable', false)
        ->has('invoices', 2)
        ->where('invoices.0', [
            'number' => 'L52IHWLK-0001',
            'date' => '2026-11-13',
            'total' => 495,
            'currency' => 'EUR',
            'status' => 'paid',
            'viewUrl' => 'https://invoice.stripe.com/i/acct_1/live_abc',
            'pdfUrl' => 'https://pay.stripe.com/invoice/acct_1/live_abc/pdf',
        ])
        ->where('invoices.1.status', 'open')
        ->where('invoices.1.currency', 'USD'));

    expect($this->stripe->requestsTo('/v1/invoices')[0]['params']['customer'])->toBe('cus_inv')
        ->and($response->getContent())->not->toContain('cus_inv')->not->toContain('secret.customer@example.test')->not->toContain('in_1');
});

test('the customer comes from the database and never from the request', function () {
    $this->stripe->respond('GET', '/v1/invoices', stripeInvoiceList([]));

    $this->actingAs(userWithCustomer())->get(route('invoices.index', ['customer' => 'cus_other', 'limit' => 100]))->assertOk();

    $params = $this->stripe->requestsTo('/v1/invoices')[0]['params'];

    expect($params['customer'])->toBe('cus_inv')->and($params['limit'])->toBe(24);
});

test('drafts, unknown statuses and unsafe links are not shown', function () {
    $this->stripe->respond('GET', '/v1/invoices', stripeInvoiceList([
        stripeInvoice(['id' => 'in_d', 'status' => 'draft', 'number' => null]),
        stripeInvoice(['id' => 'in_x', 'status' => 'weird']),
        stripeInvoice(['id' => 'in_bad_currency', 'currency' => 'euro; drop']),
        stripeInvoice(['id' => 'in_link', 'number' => 'L52IHWLK-0003', 'hosted_invoice_url' => 'javascript:alert(1)', 'invoice_pdf' => 'https://evil.test/stripe.com/x.pdf']),
    ]));

    $this->actingAs(userWithCustomer())->get(route('invoices.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices', 1)
            ->where('invoices.0.number', 'L52IHWLK-0003')
            ->where('invoices.0.viewUrl', null)
            ->where('invoices.0.pdfUrl', null));
});

test('a failing stripe call shows an unavailable notice and logs only the exception class', function () {
    $this->stripe->respond('GET', '/v1/invoices', ['error' => ['type' => 'api_error', 'message' => 'boom secret detail']], 500);

    Log::shouldReceive('error')->once()->withArgs(fn (string $message, array $context): bool => $context === ['exception' => ApiErrorException::class] || (isset($context['exception']) && str_starts_with($context['exception'], 'Stripe\\Exception\\') && count($context) === 1));

    $this->actingAs(userWithCustomer())->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('unavailable', true)->where('invoices', []));
});
