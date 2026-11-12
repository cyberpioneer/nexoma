<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Audio\BriefingGenerator;
use App\Services\Audio\PiperTtsException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Erzeugt das Audio-Briefing eines Benutzers auf der Queue `piper` (Piper 300 s, Job 330 s, Worker 350 s).
 * Kein Wiederholen (`tries = 1`): Das Ergebnis ist ueber `content_hash` idempotent, ein Fehler wird beim naechsten
 * Matcher-Lauf erneut versucht. Je Benutzer laeuft hoechstens ein Job gleichzeitig.
 */
class GenerateAudioBriefingJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Sekunden; unter dem Worker-Timeout (350 s). */
    public int $timeout = 330;

    /** Sekunden, in denen kein zweiter Job fuer denselben Benutzer eingereiht wird. */
    public int $uniqueFor = 600;

    public function __construct(public int $userId)
    {
        $this->onQueue('piper');
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    public function handle(BriefingGenerator $generator): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        try {
            $generator->generate($user);
        } catch (Throwable $exception) {
            // Nur Klasse, Fehlercode und Benutzer-ID protokollieren; Sprechtext und Antworten gehoeren nicht ins Log.
            Log::error('Audio-Briefing fehlgeschlagen.', array_filter([
                'user' => $this->userId,
                'exception' => $exception::class,
                'code' => $exception instanceof PiperTtsException ? $exception->errorCode : null,
            ]));

            $this->fail(new RuntimeException('Audio-Briefing fehlgeschlagen.'));
        }
    }
}
