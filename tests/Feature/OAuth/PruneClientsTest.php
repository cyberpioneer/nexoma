<?php

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

function clientAged(CarbonInterface $createdAt, bool $revoked = false): Client
{
    $client = createPublicOAuthClient();
    $client->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt, 'revoked' => $revoked])->save();

    return $client;
}

function addAccessToken(Client $client, User $user, bool $revoked = false, ?CarbonInterface $expiresAt = null): void
{
    Passport::token()->forceFill([
        'id' => Str::random(40),
        'user_id' => $user->getKey(),
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => $revoked,
        'expires_at' => $expiresAt ?? now()->addHour(),
    ])->save();
}

test('unused clients are pruned only after the configured retention and young ones are kept', function () {
    $days = config('mcp.client_retention.unused_days');
    $old = clientAged(now()->subDays($days + 1));
    $young = clientAged(now()->subDays($days - 1));
    $twoDays = clientAged(now()->subDays(2));

    $this->artisan('oauth:prune-clients')->expectsOutput('Pruned 1 OAuth clients.')->assertSuccessful();

    expect(Passport::client()->newQuery()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$young->getKey(), $twoDays->getKey()])->sort()->values()->all())
        ->and(Passport::client()->newQuery()->whereKey($old->getKey())->exists())->toBeFalse();
    expect($days)->toBe(30);
});

test('a client whose tokens were purged is kept while it is recently used or updated', function () {
    $user = User::factory()->create();
    $client = clientAged(now()->subDays(2));
    addAccessToken($client, $user, revoked: true, expiresAt: now()->subDays(20));
    Passport::token()->newQuery()->delete();

    $oldButRecentlyUpdated = clientAged(now()->subDays(60));
    $oldButRecentlyUpdated->forceFill(['updated_at' => now()->subDays(5)])->save();

    $this->artisan('oauth:prune-clients')->expectsOutput('Pruned 0 OAuth clients.')->assertSuccessful();
    expect(Passport::client()->newQuery()->count())->toBe(2);

    // Erst wenn auch die letzte Aenderung laenger als die Frist zurueckliegt, wird der Client entfernt.
    Passport::client()->newQuery()->update(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(31)]);
    $this->artisan('oauth:prune-clients')->expectsOutput('Pruned 2 OAuth clients.')->assertSuccessful();
});

test('old clients with tokens or authorization codes are kept', function () {
    $user = User::factory()->create();
    $withToken = clientAged(now()->subDays(60));
    addAccessToken($withToken, $user);
    $withExpiredToken = clientAged(now()->subDays(60));
    addAccessToken($withExpiredToken, $user, expiresAt: now()->subDay());
    $withCode = clientAged(now()->subDays(60));
    Passport::authCode()->forceFill([
        'id' => Str::random(40), 'user_id' => $user->getKey(), 'client_id' => $withCode->getKey(),
        'scopes' => '["mcp:use"]', 'revoked' => false, 'expires_at' => now()->addMinutes(5),
    ])->save();

    $this->artisan('oauth:prune-clients')->expectsOutput('Pruned 0 OAuth clients.')->assertSuccessful();

    expect(Passport::client()->newQuery()->count())->toBe(3);
});

test('revoked clients older than 90 days are pruned unless they still have an active token', function () {
    $user = User::factory()->create();
    $revokedOld = clientAged(now()->subDays(100), revoked: true);
    addAccessToken($revokedOld, $user, revoked: true);
    $revokedYoung = clientAged(now()->subDays(80), revoked: true);
    addAccessToken($revokedYoung, $user, revoked: true);
    $revokedWithActiveToken = clientAged(now()->subDays(100), revoked: true);
    addAccessToken($revokedWithActiveToken, $user);

    $this->artisan('oauth:prune-clients')->expectsOutput('Pruned 1 OAuth clients.')->assertSuccessful();

    expect(Passport::client()->newQuery()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$revokedYoung->getKey(), $revokedWithActiveToken->getKey()])->sort()->values()->all());
});

test('the schedule contains the hourly purge and the daily client cleanup', function () {
    $events = collect(app(Schedule::class)->events());

    $purge = $events->first(fn (Event $event) => str_contains($event->command, 'passport:purge'));
    $prune = $events->first(fn (Event $event) => str_contains($event->command, 'oauth:prune-clients'));

    expect($purge)->not->toBeNull()->and($purge->expression)->toBe('0 * * * *')
        ->and($prune)->not->toBeNull()->and($prune->expression)->toBe('0 0 * * *');
});
