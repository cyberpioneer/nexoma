<?php

use App\Http\Controllers\AudioBriefingController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CareerEntryController;
use App\Http\Controllers\CareerProfileController;
use App\Http\Controllers\ConnectedAppController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SavedJobController;
use App\Http\Middleware\EnforceOAuthAuthorizationRequest;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware\EncryptHistory;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController;
use Laravel\Passport\Http\Controllers\AuthorizationController;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController;

Route::inertia('/', 'Welcome')->name('home');

// Rechtstexte: bewusst oeffentlich (Gaeste und Benutzer), reine Leseseiten ohne Eingabe.
Route::get('/impressum', [LegalPageController::class, 'imprint'])->name('legal.imprint');
Route::get('/datenschutz', [LegalPageController::class, 'privacy'])->name('legal.privacy');
Route::get('/haftungsausschluss', [LegalPageController::class, 'disclaimer'])->name('legal.disclaimer');

// Bewusst oeffentlich (Gaeste und Benutzer); gedrosselt, Eingabe ist auf die unterstuetzten Sprachen beschraenkt.
Route::post('/locale', [LocaleController::class, 'update'])->middleware('throttle:locale')->name('locale.update');

// Alle geschuetzten Routen gehoeren in diese Gruppe (auth + verified), z. B. kuenftige
// Jobsuche-, Detail- und Profilrouten. Der Route-Inventar-Test erzwingt das.
Route::middleware(['auth', 'verified', EncryptHistory::class])->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');

    Route::get('/jobs', [JobController::class, 'index'])->middleware('throttle:jobsuche')->name('jobs.index');
    Route::get('/jobs/{refnr}', [JobController::class, 'show'])
        ->where('refnr', '[A-Za-z0-9._-]{1,100}')
        ->middleware('throttle:jobdetails')
        ->name('jobs.show');

    Route::get('/saved-jobs', [SavedJobController::class, 'index'])->middleware('throttle:saved-jobs')->name('saved-jobs.index');
    Route::post('/saved-jobs', [SavedJobController::class, 'store'])->middleware('throttle:saved-jobs')->name('saved-jobs.store');
    Route::patch('/saved-jobs/{savedJob}', [SavedJobController::class, 'update'])
        ->whereNumber('savedJob')
        ->middleware('throttle:saved-jobs')
        ->name('saved-jobs.update');
    Route::delete('/saved-jobs/{savedJob}', [SavedJobController::class, 'destroy'])
        ->whereNumber('savedJob')
        ->middleware('throttle:saved-jobs')
        ->name('saved-jobs.destroy');

    // Premium-Abo (Stripe). Der Status wird ausschliesslich ueber den signierten Webhook (routes/webhooks.php) gesetzt.
    Route::get('/upgrade', [BillingController::class, 'show'])->middleware('throttle:billing')->name('billing.upgrade');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->middleware('throttle:billing')->name('billing.checkout');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->middleware('throttle:billing')->name('billing.portal');
    Route::get('/billing/success', [BillingController::class, 'success'])->middleware('throttle:billing')->name('billing.success');
    Route::get('/billing/cancel', [BillingController::class, 'cancel'])->middleware('throttle:billing')->name('billing.cancel');

    // Premium (Release 1.2.0): Profil und Werdegang. Reihenfolge der Middleware: auth, verified (Gruppe), dann subscribed.
    Route::middleware(['subscribed', 'throttle:career-profile'])->group(function () {
        Route::get('/profile', [CareerProfileController::class, 'show'])->name('profile.show');
        Route::put('/profile', [CareerProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/optimize-bio', [CareerProfileController::class, 'optimizeBio'])
            ->middleware('throttle:optimize-bio')
            ->name('profile.optimize-bio');
        Route::post('/career-entries', [CareerEntryController::class, 'store'])->name('career-entries.store');
        Route::put('/career-entries/{careerEntry}', [CareerEntryController::class, 'update'])
            ->whereNumber('careerEntry')
            ->name('career-entries.update');
        Route::delete('/career-entries/{careerEntry}', [CareerEntryController::class, 'destroy'])
            ->whereNumber('careerEntry')
            ->name('career-entries.destroy');
    });

    // Premium: Audio-Briefings. Auslieferung nur an den Besitzer (Policy), mit Range-Unterstuetzung fuer den Player.
    Route::get('/audio-briefings/{audioBriefing}/stream', [AudioBriefingController::class, 'stream'])
        ->whereNumber('audioBriefing')
        ->middleware(['subscribed', 'throttle:audio-stream'])
        ->name('audio-briefings.stream');

    Route::get('/connected-apps', [ConnectedAppController::class, 'index'])->middleware('throttle:connected-apps')->name('connected-apps.index');
    Route::delete('/connected-apps/{clientId}', [ConnectedAppController::class, 'destroy'])
        ->whereUuid('clientId')
        ->middleware('throttle:connected-apps')
        ->name('connected-apps.destroy');

    // OAuth2-Consent (Passport, bewusst kopiert statt Standardrouten: verified ist Pflicht, Routennamen
    // bleiben erhalten, weil laravel/mcp sie fuer Metadaten und Framing-Header referenziert).
    // Der Token-Endpunkt liegt in routes/ai.php (ohne Session/CSRF).
    Route::get('/oauth/authorize', [AuthorizationController::class, 'authorize'])->middleware(EnforceOAuthAuthorizationRequest::class)->name('passport.authorizations.authorize');
    Route::post('/oauth/authorize', [ApproveAuthorizationController::class, 'approve'])->middleware('throttle:oauth-consent')->name('passport.authorizations.approve');
    Route::delete('/oauth/authorize', [DenyAuthorizationController::class, 'deny'])->middleware('throttle:oauth-consent')->name('passport.authorizations.deny');
});
