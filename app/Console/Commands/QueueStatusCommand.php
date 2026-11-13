<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Entwicklungs-Monitoring (Prozess "status" von `php artisan dev`): zeigt fortlaufend, wie viele Jobs je Queue warten
 * oder laufen und wie viele fehlgeschlagen sind. Ausgegeben wird bei jeder Aenderung und mindestens einmal pro Minute.
 */
#[Signature('queue:status {--once : Nur einmal ausgeben und beenden} {--interval=5 : Sekunden zwischen den Abfragen}')]
#[Description('Zeigt fortlaufend wartende, laufende und fehlgeschlagene Jobs je Queue (Entwicklung, Treiber "database")')]
class QueueStatusCommand extends Command
{
    /** Queues des Entwicklungs-Workers (Reihenfolge = Prioritaet), siehe AppServiceProvider::configureDevProcesses(). */
    public const QUEUES = ['sync', 'default', 'ai', 'piper'];

    private const HEARTBEAT_SECONDS = 60;

    public function handle(): int
    {
        $connection = (string) config('queue.default');

        if ($connection !== 'database') {
            $this->warn("Der Queue-Treiber \"{$connection}\" wird nicht unterstützt; die Statusanzeige braucht den Treiber \"database\".");

            return self::SUCCESS;
        }

        $once = (bool) $this->option('once');
        $interval = max(1, (int) $this->option('interval'));
        $last = null;
        $lastPrinted = 0;

        while (true) {
            $line = $this->statusLine();

            if ($last === null || $line !== $last || time() - $lastPrinted >= self::HEARTBEAT_SECONDS) {
                $this->line($line);
                $last = $line;
                $lastPrinted = time();
            }

            if ($once) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function statusLine(): string
    {
        $table = (string) config('queue.connections.database.table', 'jobs');

        $rows = DB::table($table)
            ->selectRaw('queue, count(*) as total, sum(case when reserved_at is null then 0 else 1 end) as running')
            ->whereIn('queue', self::QUEUES)
            ->groupBy('queue')
            ->get()
            ->keyBy('queue');

        $parts = [];

        foreach (self::QUEUES as $queue) {
            $total = (int) ($rows[$queue]->total ?? 0);
            $running = (int) ($rows[$queue]->running ?? 0);
            $parts[] = $total === 0 ? "{$queue} 0" : sprintf('%s %d (%d läuft)', $queue, $total, $running);
        }

        $failed = DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();

        return 'Queue: '.implode(' | ', $parts).' | fehlgeschlagen '.$failed;
    }
}
