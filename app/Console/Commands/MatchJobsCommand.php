<?php

namespace App\Console\Commands;

use App\Jobs\MatchJobsForUserJob;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('jobs:match {--user= : Nur diesen Benutzer (ID) starten, ohne Intervallsperre}')]
#[Description('Startet die automatische Vakanzensuche (AI Matcher) fuer berechtigte Benutzer auf der Queue "ai"')]
class MatchJobsCommand extends Command
{
    public function handle(): int
    {
        $userOption = $this->option('user');

        if ($userOption !== null) {
            return $this->dispatchForUser((string) $userOption);
        }

        $interval = (int) config('matcher.interval_minutes');

        if ($interval <= 0) {
            $this->info('Der Matcher ist ausgeschaltet (JOB_MATCHER_INTERVAL_MINUTES=0).');

            return self::SUCCESS;
        }

        $dispatched = 0;

        User::query()
            ->whereIn('stripe_subscription_status', User::ACTIVE_SUBSCRIPTION_STATUSES)
            ->whereNotNull('preferred_language')
            ->whereNotNull('search_location')
            ->chunkById(100, function ($users) use ($interval, &$dispatched): void {
                foreach ($users as $user) {
                    if (! $user->hasActiveSubscription() || ! $user->hasCompleteCareerProfile()) {
                        continue;
                    }

                    // Hoechstens ein Lauf je Benutzer und Intervall; die Sperre bleibt auch nach einem Fehler bestehen.
                    if (! Cache::add('matcher:lock:'.$user->id, true, now()->addMinutes(max(1, $interval - 1)))) {
                        continue;
                    }

                    MatchJobsForUserJob::dispatch($user->id);
                    $dispatched++;
                }
            });

        $this->info("{$dispatched} Matcher-Läufe eingereiht.");

        return self::SUCCESS;
    }

    private function dispatchForUser(string $id): int
    {
        $user = ctype_digit($id) ? User::query()->find((int) $id) : null;

        if ($user === null) {
            $this->error('Benutzer nicht gefunden.');

            return self::FAILURE;
        }

        if (! $user->hasActiveSubscription() || ! $user->hasCompleteCareerProfile()) {
            $this->error('Der Benutzer hat kein aktives Abo oder kein vollständiges Profil.');

            return self::FAILURE;
        }

        MatchJobsForUserJob::dispatch($user->id);
        $this->info('Matcher-Lauf eingereiht.');

        return self::SUCCESS;
    }
}
