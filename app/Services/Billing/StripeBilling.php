<?php

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Event;
use Stripe\StripeClient;

/**
 * Kapselt die Stripe-Anbindung des Premium-Abos (Checkout, Kundenportal, Abgleich des Abo-Status).
 *
 * Preis/Plan stammen ausschliesslich aus `services.stripe.price_id`; kein Wert aus einer Anfrage fliesst in
 * die Stripe-Aufrufe ein. Der Abo-Status wird nur aus dem bei Stripe frisch abgerufenen Abonnement uebernommen,
 * nie aus Anfrageparametern oder dem Webhook-Payload.
 */
class StripeBilling
{
    /** Subscription-Events, die einen Abgleich des Status ausloesen. */
    public const SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'customer.subscription.paused',
        'customer.subscription.resumed',
    ];

    /**
     * Der Client wird erst bei Bedarf aufgeloest, damit eine fehlende Konfiguration ({@see isConfigured()})
     * kontrolliert abgefangen werden kann, statt schon bei der Konstruktion zu scheitern.
     */
    private function stripe(): StripeClient
    {
        return app(StripeClient::class);
    }

    /**
     * Ob Schluessel und Preis konfiguriert sind (ohne sie ist keine Zahlungsabwicklung moeglich).
     */
    public function isConfigured(): bool
    {
        return $this->configured('secret') && $this->configured('price_id');
    }

    /**
     * Legt eine Checkout-Session (Modus `subscription`) fuer den konfigurierten Preis an und liefert ihre URL.
     */
    public function createCheckoutUrl(User $user): string
    {
        $session = $this->stripe()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->ensureCustomer($user),
            'client_reference_id' => (string) $user->id,
            'line_items' => [['price' => (string) config('services.stripe.price_id'), 'quantity' => 1]],
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.cancel'),
        ]);

        return $this->urlOf($session->url);
    }

    /**
     * Legt eine Sitzung im Stripe-Kundenportal (Abo verwalten, kuendigen, Zahlungsart) an und liefert ihre URL.
     */
    public function createPortalUrl(User $user): string
    {
        if ($user->stripe_customer_id === null) {
            throw new RuntimeException('Der Benutzer hat keinen Stripe-Kunden.');
        }

        $session = $this->stripe()->billingPortal->sessions->create([
            'customer' => $user->stripe_customer_id,
            'return_url' => route('dashboard'),
        ]);

        return $this->urlOf($session->url);
    }

    /**
     * Verarbeitet ein signaturgeprueftes Stripe-Event; unbekannte Typen werden ignoriert.
     */
    public function handleEvent(Event $event): void
    {
        if (! in_array($event->type, self::SUBSCRIPTION_EVENTS, true)) {
            return;
        }

        $subscriptionId = $event->data->object->id ?? null;

        if (is_string($subscriptionId) && $subscriptionId !== '') {
            $this->syncSubscription($subscriptionId);
        }
    }

    /**
     * Ruft das Abonnement bei Stripe ab und uebernimmt Status und Periodenende beim zugehoerigen Benutzer.
     * So ist das Ergebnis unabhaengig von Reihenfolge und Wiederholung der Webhook-Zustellung.
     */
    public function syncSubscription(string $subscriptionId): void
    {
        /** @var array<string, mixed> $subscription */
        $subscription = $this->stripe()->subscriptions->retrieve($subscriptionId)->toArray();

        if (! $this->containsConfiguredPrice($subscription)) {
            return;
        }

        $customerId = $subscription['customer'] ?? null;
        $customerId = is_array($customerId) ? ($customerId['id'] ?? null) : $customerId;

        $user = is_string($customerId) && $customerId !== ''
            ? User::query()->where('stripe_customer_id', $customerId)->first()
            : null;

        if ($user === null) {
            Log::warning('Stripe-Abonnement ohne zugeordneten Benutzer ignoriert.', ['subscription' => $subscriptionId]);

            return;
        }

        DB::transaction(function () use ($user, $subscription, $subscriptionId): void {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $status = is_string($subscription['status'] ?? null) ? $subscription['status'] : 'incomplete';
            $isActive = in_array($status, User::ACTIVE_SUBSCRIPTION_STATUSES, true);

            // Ein inaktives Fremd-Abo (z. B. altes, gekuendigtes) darf das aktuelle Abo nicht ueberschreiben.
            if ($locked->stripe_subscription_id !== null && $locked->stripe_subscription_id !== $subscriptionId && ! $isActive) {
                return;
            }

            $locked->forceFill([
                'stripe_subscription_id' => $subscriptionId,
                'stripe_subscription_status' => mb_substr($status, 0, 32),
                'stripe_current_period_end' => $this->periodEnd($subscription),
            ])->save();
        });
    }

    /**
     * Liefert die Stripe-Kunden-ID des Benutzers und legt den Kunden bei Bedarf an (gesperrt und mit
     * Idempotenzschluessel, damit parallele Anfragen keinen zweiten Kunden erzeugen).
     */
    private function ensureCustomer(User $user): string
    {
        return DB::transaction(function () use ($user): string {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($locked->stripe_customer_id !== null) {
                return $locked->stripe_customer_id;
            }

            $customer = $this->stripe()->customers->create(
                ['email' => $locked->email, 'metadata' => ['user_id' => (string) $locked->id]],
                ['idempotency_key' => 'nexoma-customer-'.$locked->id.'-'.substr(sha1($locked->email), 0, 12)],
            );

            $locked->forceFill(['stripe_customer_id' => $customer->id])->save();
            $user->stripe_customer_id = $customer->id;

            return $customer->id;
        });
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function containsConfiguredPrice(array $subscription): bool
    {
        $priceId = config('services.stripe.price_id');

        if (! is_string($priceId) || $priceId === '') {
            return false;
        }

        foreach ($subscription['items']['data'] ?? [] as $item) {
            if (($item['price']['id'] ?? null) === $priceId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Spaetestes Periodenende der Positionen (neuere API-Versionen fuehren es je Position, aeltere am Abo).
     *
     * @param  array<string, mixed>  $subscription
     */
    private function periodEnd(array $subscription): ?\DateTimeInterface
    {
        $timestamps = [$subscription['current_period_end'] ?? null];

        foreach ($subscription['items']['data'] ?? [] as $item) {
            $timestamps[] = $item['current_period_end'] ?? null;
        }

        $latest = max(array_map(fn (mixed $value): int => is_int($value) ? $value : 0, $timestamps));

        return $latest > 0 ? now()->setTimestamp($latest) : null;
    }

    private function urlOf(mixed $url): string
    {
        if (! is_string($url) || ! str_starts_with($url, 'https://')) {
            throw new RuntimeException('Stripe lieferte keine gueltige https-URL.');
        }

        return $url;
    }

    private function configured(string $key): bool
    {
        $value = config('services.stripe.'.$key);

        return is_string($value) && $value !== '';
    }
}
