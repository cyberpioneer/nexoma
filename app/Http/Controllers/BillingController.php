<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Billing\StripeBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Premium-Abo: Checkout starten, Rueckkehrseiten, Kundenportal. Der Abo-Status wird nie hier, sondern nur
 * ueber den signierten Stripe-Webhook gesetzt; die Rueckkehrseiten geben lediglich eine Meldung aus.
 */
class BillingController extends Controller
{
    /** Abo-Status, bei denen ein neuer Checkout ein zweites Abo erzeugen wuerde (Zahlung klaeren statt neu kaufen). */
    private const STATUSES_FOR_PORTAL = ['past_due', 'unpaid', 'paused'];

    /**
     * Startet den Stripe-Checkout. Ohne Anfrageparameter: Plan, Preis und Rueckkehr-URLs bestimmt der Server.
     */
    public function checkout(Request $request, StripeBilling $billing): Response|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->hasActiveSubscription()) {
            return redirect()->route('dashboard')->with('status', __('ui.billing.already_subscribed'));
        }

        $hasOpenSubscription = in_array($user->stripe_subscription_status, self::STATUSES_FOR_PORTAL, true);

        return $this->redirectToStripe(
            fn (): string => $hasOpenSubscription ? $billing->createPortalUrl($user) : $billing->createCheckoutUrl($user),
            $billing,
        );
    }

    /**
     * Oeffnet das Stripe-Kundenportal (nur fuer Benutzer mit bestehendem Stripe-Kunden).
     */
    public function portal(Request $request, StripeBilling $billing): Response|RedirectResponse
    {
        $user = $this->user($request);

        if ($user->stripe_customer_id === null) {
            return redirect()->route('dashboard')->withErrors(['billing' => __('ui.billing.no_subscription')]);
        }

        return $this->redirectToStripe(fn (): string => $billing->createPortalUrl($user), $billing);
    }

    /**
     * Rueckkehr nach erfolgreicher Zahlung. Freigeschaltet wird erst durch den Webhook.
     */
    public function success(Request $request): RedirectResponse
    {
        $message = $this->user($request)->hasActiveSubscription()
            ? __('ui.billing.success_active')
            : __('ui.billing.success_pending');

        return redirect()->route('dashboard')->with('status', $message);
    }

    /**
     * Rueckkehr nach abgebrochenem Checkout.
     */
    public function cancel(): RedirectResponse
    {
        return redirect()->route('dashboard')->with('status', __('ui.billing.canceled'));
    }

    /**
     * @param  callable(): string  $resolveUrl
     */
    private function redirectToStripe(callable $resolveUrl, StripeBilling $billing): Response|RedirectResponse
    {
        if (! $billing->isConfigured()) {
            Log::error('Stripe ist nicht vollstaendig konfiguriert (Schluessel oder Preis fehlt).');

            return $this->unavailable();
        }

        try {
            return Inertia::location($resolveUrl());
        } catch (ApiErrorException|RuntimeException $exception) {
            // Nur Klasse und Stripe-Code protokollieren, nie Anfrage-/Antwortinhalt.
            Log::error('Stripe-Aufruf fehlgeschlagen.', ['exception' => $exception::class]);

            return $this->unavailable();
        }
    }

    private function unavailable(): RedirectResponse
    {
        return redirect()->route('dashboard')->withErrors(['billing' => __('ui.billing.unavailable')]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
