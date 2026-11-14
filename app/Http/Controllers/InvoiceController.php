<?php

namespace App\Http\Controllers;

use App\Services\Billing\StripeBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Stripe\Exception\ApiErrorException;

/**
 * Rechnungsliste: Die Rechnungen entstehen und liegen bei Stripe; Nexoma zeigt nur eine Whitelist der Felder des
 * eigenen Stripe-Kunden und verlinkt auf die von Stripe gehostete Rechnung und die PDF. Es wird nichts gespeichert.
 */
class InvoiceController extends Controller
{
    public function index(Request $request, StripeBilling $billing): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $invoices = [];
        $unavailable = false;

        if ($user->stripe_customer_id !== null) {
            try {
                $invoices = $billing->isConfigured() ? $billing->invoicesFor($user) : throw new RuntimeException('Stripe ist nicht konfiguriert.');
            } catch (ApiErrorException|RuntimeException $exception) {
                // Nur die Klasse protokollieren, nie Anfrage- oder Antwortinhalt.
                Log::error('Stripe-Rechnungsabruf fehlgeschlagen.', ['exception' => $exception::class]);
                $unavailable = true;
            }
        }

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'unavailable' => $unavailable,
        ]);
    }
}
