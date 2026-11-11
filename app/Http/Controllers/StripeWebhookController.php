<?php

namespace App\Http\Controllers;

use App\Services\Billing\StripeBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * Empfaengt Stripe-Webhooks. Ohne gueltige Signatur wird nichts verarbeitet (400); ohne konfiguriertes
 * Signing-Secret wird fail-closed mit 500 geantwortet. Fehler beim Verarbeiten ergeben 500, damit Stripe wiederholt.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeBilling $billing): Response
    {
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('Stripe-Webhook abgelehnt: Signing-Secret ist nicht konfiguriert.');

            return response('', 500);
        }

        $signature = $request->header('Stripe-Signature');

        if (! is_string($signature) || $signature === '') {
            return response('', 400);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $secret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('', 400);
        }

        try {
            $billing->handleEvent($event);
        } catch (InvalidRequestException $exception) {
            if ($exception->getStripeCode() !== 'resource_missing') {
                throw $exception;
            }

            // Unbekanntes Abonnement (z. B. anderes Konto): Wiederholen waere sinnlos.
            Log::warning('Stripe-Webhook: Abonnement bei Stripe nicht gefunden, Ereignis ignoriert.');
        }

        return response('', 200);
    }
}
