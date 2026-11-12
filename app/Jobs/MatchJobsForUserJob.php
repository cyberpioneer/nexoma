<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Matching\JobMatcher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Ein Matcher-Lauf fuer einen Benutzer auf der Queue `ai`. Kein Wiederholen (`tries = 1`): Ein fehlgeschlagener Lauf
 * wird erst im naechsten Intervall neu gestartet, damit Fehler keine Kosten vervielfachen. Je Benutzer laeuft hoechstens
 * ein Job gleichzeitig.
 */
class MatchJobsForUserJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Sekunden; unter dem Worker-Timeout (350 s). */
    public int $timeout = 120;

    /** Sekunden, in denen kein zweiter Job fuer denselben Benutzer eingereiht wird. */
    public int $uniqueFor = 300;

    public function __construct(public int $userId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(JobMatcher $matcher): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        try {
            $result = $matcher->run($user);
        } catch (Throwable $exception) {
            // Nur Klasse und Benutzer-ID protokollieren; Texte, Antworten und Zugangsdaten gehoeren nicht ins Log
            // und nicht in die Tabelle der fehlgeschlagenen Jobs.
            Log::error('Matcher-Lauf fehlgeschlagen.', ['user' => $this->userId, 'exception' => $exception::class]);

            $this->fail(new RuntimeException('Matcher-Lauf fehlgeschlagen.'));

            return;
        }

        // Neue Vorschlaege werden im Audio-Briefing vorgelesen (Queue `piper`, je Benutzer eindeutig).
        if ($result['saved'] > 0) {
            GenerateAudioBriefingJob::dispatch($user->id);
        }
    }
}
