<?php

use App\Console\Commands\QueueStatusCommand;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\DB;

test('the dev queue worker serves all application queues', function () {
    $queue = collect(DevCommands::commands())->first(fn (array $command): bool => $command['name'] === 'queue');

    expect($queue)->not->toBeNull()
        ->and($queue['command'])->toContain('queue:listen')
        ->toContain('--queue=sync,default,ai,piper')
        ->and(QueueStatusCommand::QUEUES)->toBe(['sync', 'default', 'ai', 'piper']);

    expect(collect(DevCommands::commands())->where('name', 'queue'))->toHaveCount(1);
});

test('the dev status process is registered and timestamps are enabled', function () {
    $status = collect(DevCommands::commands())->first(fn (array $command): bool => $command['name'] === 'status');

    expect($status['command'])->toBe('php artisan queue:status')
        ->and(DevCommands::shouldIncludeTimestamps())->toBeTrue();
});

test('queue:status shows waiting, running and failed jobs per queue', function () {
    config()->set('queue.default', 'database');
    $payload = json_encode(['displayName' => 'Test']);
    $row = fn (string $queue, ?int $reservedAt = null): array => ['queue' => $queue, 'payload' => $payload, 'attempts' => 0, 'reserved_at' => $reservedAt, 'available_at' => time(), 'created_at' => time()];

    DB::table('jobs')->insert([$row('ai'), $row('ai', time()), $row('piper'), $row('other')]);
    DB::table('failed_jobs')->insert(['uuid' => 'abc', 'connection' => 'database', 'queue' => 'ai', 'payload' => $payload, 'exception' => 'x', 'failed_at' => now()]);

    $this->artisan('queue:status', ['--once' => true])
        ->expectsOutput('Queue: sync 0 | default 0 | ai 2 (1 läuft) | piper 1 (0 läuft) | fehlgeschlagen 1')
        ->assertSuccessful();
});

test('queue:status explains that other queue drivers are not supported', function () {
    config()->set('queue.default', 'sync');

    $this->artisan('queue:status', ['--once' => true])
        ->expectsOutputToContain('wird nicht unterstützt')
        ->assertSuccessful();
});
