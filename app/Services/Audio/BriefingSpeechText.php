<?php

namespace App\Services\Audio;

use App\Ai\Agents\BriefingSpeechAgent;
use App\Models\SavedJob;
use App\Services\Ai\BioOptimizer;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Erzeugt den Sprechtext eines Audio-Briefings aus bewerteten Vakanzen (KI-Dienst). Die Vakanzdaten gelangen nur
 * gekapselt (`<untrusted_job_data>`) in den Prompt; die Antwort wird bereinigt und auf das Limit des TTS-Dienstes begrenzt.
 */
class BriefingSpeechText
{
    /** Zielobergrenze im Prompt (unter dem harten Limit {@see PiperTtsService::MAX_TEXT_LENGTH}). */
    private const TARGET_CHARACTERS = 6000;

    private const MIN_CHARACTERS = 20;

    /**
     * @param  Collection<int, SavedJob>  $jobs  bewertete Vakanzen des Benutzers (`ai_matcher`)
     *
     * @throws RuntimeException wenn keine verwertbare Antwort entsteht
     */
    public function build(Collection $jobs, string $language): string
    {
        $languageName = BioOptimizer::LANGUAGE_NAMES[$language] ?? throw new RuntimeException('Unbekannte Sprache.');

        if ($jobs->isEmpty()) {
            throw new RuntimeException('Keine Vakanzen fuer das Briefing.');
        }

        $blocks = $jobs->values()->map(fn (SavedJob $job, int $index): string => sprintf(
            "<untrusted_job_data position=\"%d\">\nTitle: %s\nEmployer: %s\nLocation: %s\nFit (1-10): %s\nDistance (km): %s\nSummary: %s\n</untrusted_job_data>",
            $index + 1,
            $this->field($job->title, 300),
            $this->field($job->employer, 300),
            $this->field($job->location, 300),
            $job->temperature ?? 'unknown',
            $job->distance_km ?? 'unknown',
            $this->field($job->ai_summary, SavedJob::MAX_AI_SUMMARY_LENGTH),
        ))->all();

        $agent = new BriefingSpeechAgent($languageName, self::TARGET_CHARACTERS);
        $answer = (string) $agent->prompt(
            count($blocks)." new job postings follow. Write the briefing in {$languageName}.\n\n".implode("\n\n", $blocks),
        )->text;

        return $this->sanitize($answer);
    }

    /**
     * Entfernt Denkbloecke, Tags, Markdown-Zeichen und Steuerzeichen; kuerzt ueberlange Antworten an einer Satzgrenze.
     */
    private function sanitize(string $answer): string
    {
        $text = preg_replace('#<think>.*?</think>#isu', '', $answer) ?? '';
        $text = preg_replace('#</?[A-Za-z][^>]{0,200}>#u', ' ', $text) ?? '';
        $text = preg_replace('#https?://\S+#iu', ' ', $text) ?? '';
        $text = preg_replace('/[*_`#>~|\[\]{}\\\\]/u', '', $text) ?? '';
        $text = preg_replace('/[^\P{C}\n]/u', ' ', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\n{3,}/u', "\n\n", $text) ?? '');

        if (mb_strlen($text) > PiperTtsService::MAX_TEXT_LENGTH) {
            $cut = mb_substr($text, 0, PiperTtsService::MAX_TEXT_LENGTH);
            $end = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, "\n") ?: 0);
            $text = trim($end > PiperTtsService::MAX_TEXT_LENGTH / 2 ? mb_substr($cut, 0, $end + 1) : $cut);
        }

        if (mb_strlen($text) < self::MIN_CHARACTERS) {
            throw new RuntimeException('Keine verwertbare Antwort des KI-Dienstes.');
        }

        return $text;
    }

    /**
     * Fremdtext fuer den Prompt: ohne Steuerzeichen und Begrenzer-Tags, einzeilig und gekuerzt.
     */
    private function field(?string $value, int $max): string
    {
        $value = preg_replace('#</?\s*untrusted_job_data[^>]*>#iu', '', (string) $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        $value = preg_replace('/[^\P{C}]/u', '', $value) ?? '';

        return mb_substr(trim($value), 0, $max) ?: 'unknown';
    }
}
