<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schedule;
use Laravel\Passport\Passport;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// OAuth-Aufraeumen: abgelaufene/widerrufene Tokens stuendlich; ungenutzte und widerrufene Clients taeglich.
Artisan::command('oauth:prune-clients', function () {
    $now = Date::now();
    $retention = config('mcp.client_retention');

    $unusedBefore = $now->subDays($retention['unused_days']);
    $unused = Passport::client()->newQuery()
        ->where('created_at', '<', $unusedBefore)
        ->where('updated_at', '<', $unusedBefore)
        ->whereDoesntHave('tokens')
        ->whereDoesntHave('authCodes')
        ->delete();

    $revoked = Passport::client()->newQuery()
        ->where('revoked', true)
        ->where('updated_at', '<', $now->subDays($retention['revoked_days']))
        ->whereDoesntHave('tokens', fn (Builder $query) => $query
            ->where('revoked', false)
            ->where('expires_at', '>', $now))
        ->delete();

    $this->info('Pruned '.($unused + $revoked).' OAuth clients.');
})->purpose('Delete unused OAuth clients and revoked clients after the configured retention (mcp.client_retention)');

Schedule::command('passport:purge')->hourly();
Schedule::command('oauth:prune-clients')->daily();
