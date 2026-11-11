<?php

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Diese Datei wird ohne Middleware-Gruppe `web` geladen (siehe bootstrap/app.php): keine Session, kein CSRF,
| kein SetLocale. Die Authentizitaet des Aufrufers wird ausschliesslich ueber die Stripe-Signatur geprueft.
*/

Route::post('/stripe/webhook', StripeWebhookController::class)
    ->middleware('throttle:stripe-webhook')
    ->name('stripe.webhook');
