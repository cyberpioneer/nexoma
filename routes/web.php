<?php

use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware\EncryptHistory;

Route::inertia('/', 'Welcome')->name('home');

// Bewusst oeffentlich (Gaeste und Benutzer); gedrosselt, Eingabe ist auf die unterstuetzten Sprachen beschraenkt.
Route::post('/locale', [LocaleController::class, 'update'])->middleware('throttle:locale')->name('locale.update');

// Alle geschuetzten Routen gehoeren in diese Gruppe (auth + verified), z. B. kuenftige
// Jobsuche-, Detail- und Profilrouten. Der Route-Inventar-Test erzwingt das.
Route::middleware(['auth', 'verified', EncryptHistory::class])->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');
});
