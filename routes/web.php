<?php

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
