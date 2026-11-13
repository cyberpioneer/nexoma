<?php

namespace App\Services\Matching;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Wochenlimit der geplanten Matcher-Laeufe je Benutzer (Kostenbremse fuer den KI-Dienst).
 *
 * Erlaubt sind hoechstens `matcher.max_runs_per_week` Laeufe in den letzten 7 Tagen, mit einem Mindestabstand von
 * 7 Tagen / Anzahl, damit sie sich gleichmaessig verteilen. Die Laufzeitpunkte liegen im Cache (Treiber `database`);
 * ein Leeren des Caches setzt das Limit zurueck.
 */
class MatcherQuota
{
    private const WINDOW_MINUTES = 7 * 24 * 60;

    /**
     * Reserviert einen Lauf, wenn das Limit es zulaesst (atomar je Benutzer).
     */
    public function reserve(User $user): bool
    {
        $max = (int) config('matcher.max_runs_per_week');

        if ($max <= 0) {
            return true;
        }

        $key = 'matcher:runs:'.$user->id;

        return (bool) Cache::lock($key.':lock', 10)->block(5, function () use ($key, $max): bool {
            $now = now();
            $windowStart = $now->copy()->subMinutes(self::WINDOW_MINUTES)->getTimestamp();

            /** @var list<int> $runs */
            $runs = array_values(array_filter(
                (array) Cache::get($key, []),
                fn (mixed $timestamp): bool => is_int($timestamp) && $timestamp > $windowStart,
            ));

            $minimumGap = intdiv(self::WINDOW_MINUTES * 60, $max);
            $last = $runs === [] ? null : max($runs);

            if (count($runs) >= $max || ($last !== null && $now->getTimestamp() - $last < $minimumGap)) {
                return false;
            }

            $runs[] = $now->getTimestamp();
            Cache::put($key, $runs, $now->copy()->addMinutes(self::WINDOW_MINUTES));

            return true;
        });
    }
}
