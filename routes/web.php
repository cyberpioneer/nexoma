<?php

use Illuminate\Support\Facades\Route;
use Inertia\Middleware\EncryptHistory;

Route::inertia('/', 'Welcome')->name('home');

// Alle geschuetzten Routen gehoeren in diese Gruppe (auth + verified), z. B. kuenftige
// Jobsuche-, Detail- und Profilrouten. Der Route-Inventar-Test erzwingt das.
Route::middleware(['auth', 'verified', EncryptHistory::class])->group(function () {
    Route::inertia('/dashboard', 'Dashboard')->name('dashboard');
});
