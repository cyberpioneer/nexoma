<?php

namespace App\Services\Audio;

use App\Models\AudioBriefing;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\Matching\JobMatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Erzeugt das Audio-Briefing eines Benutzers aus den noch nicht erwaehnten Matcher-Vakanzen (`ai_matcher`,
 * `briefed_at IS NULL`), die neuesten zuerst (hoechstens {@see JobMatcher::MAX_NEW_JOBS} je Briefing). Idempotent ueber `content_hash` (Sprache + Vakanz-IDs): Ein fertiges Briefing wird nie
 * doppelt erzeugt, ein fehlgeschlagenes beim naechsten Lauf mit demselben Text erneut vertont.
 * `briefed_at` wird erst gesetzt, wenn die Audiodatei fertig ist.
 */
class BriefingGenerator
{
    public function __construct(private BriefingSpeechText $speech, private PiperTtsService $tts) {}

    /**
     * @return AudioBriefing|null null, wenn der Benutzer nicht berechtigt ist oder es nichts Neues gibt
     *
     * @throws PiperTtsException wenn die Synthese scheitert (das Briefing steht dann auf `failed`)
     * @throws RuntimeException wenn kein Sprechtext entsteht
     */
    public function generate(User $user): ?AudioBriefing
    {
        $locale = (string) $user->preferred_language;

        if (! $user->hasActiveSubscription() || ! array_key_exists($locale, (array) config('services.piper.voices', []))) {
            return null;
        }

        $jobs = $user->savedJobs()
            ->where('source', SavedJob::SOURCE_AI_MATCHER)
            ->whereNull('briefed_at')
            ->orderByDesc('id')
            ->limit(JobMatcher::MAX_NEW_JOBS)
            ->get();

        if ($jobs->isEmpty()) {
            return null;
        }

        $ids = array_values(array_map(fn (SavedJob $job): int => $job->id, $jobs->all()));
        $hash = hash('sha256', $locale.'|'.implode(',', $ids));
        $briefing = $user->audioBriefings()->where('content_hash', $hash)->first();

        if ($briefing?->isReady()) {
            $this->markBriefed($user, $ids);

            return $briefing;
        }

        $briefing ??= $this->createBriefing($user, $locale, $hash, $this->speech->build($jobs, $locale));
        $briefing->markProcessing();

        try {
            $audio = $this->tts->synthesize($briefing->speech_text, $locale);
        } catch (PiperTtsException $exception) {
            $briefing->markFailed($exception->errorCode);

            throw $exception;
        }

        try {
            DB::transaction(function () use ($briefing, $audio, $user, $ids): void {
                $briefing->markReady($audio['path'], $audio['duration_ms']);
                $this->markBriefed($user, $ids);
            });
        } catch (Throwable $exception) {
            Storage::disk((string) config('services.piper.disk', 'local'))->delete($audio['path']);
            $briefing->markFailed('save_failed');

            throw $exception;
        }

        return $briefing;
    }

    private function createBriefing(User $user, string $locale, string $hash, string $text): AudioBriefing
    {
        try {
            return $user->audioBriefings()->create(['locale' => $locale, 'content_hash' => $hash, 'speech_text' => $text]);
        } catch (UniqueConstraintViolationException) {
            return $user->audioBriefings()->where('content_hash', $hash)->firstOrFail();
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function markBriefed(User $user, array $ids): void
    {
        $user->savedJobs()
            ->whereIn('id', $ids)
            ->where('source', SavedJob::SOURCE_AI_MATCHER)
            ->whereNull('briefed_at')
            ->update(['briefed_at' => now()]);
    }
}
