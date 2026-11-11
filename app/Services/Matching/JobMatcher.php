<?php

namespace App\Services\Matching;

use App\Ai\Agents\JobAssessmentAgent;
use App\Ai\Agents\SearchTermAgent;
use App\Models\CareerEntry;
use App\Models\SavedJob;
use App\Models\SeenJobReference;
use App\Models\User;
use App\Services\Ai\BioOptimizer;
use App\Services\Jobsuche\JobDataMapper;
use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Automatische Vakanzensuche fuer einen Benutzer: Suchbegriff aus Profil und Werdegang ableiten (MiniMax),
 * neue Stellen bei der BA suchen, bewerten (MiniMax) und als `ai_matcher` speichern.
 *
 * Stellendaten sind Fremddaten und gelangen nur gekapselt (`<untrusted_job_data>`) in den Prompt; jede Ausgabe der KI
 * wird vor dem Speichern strikt validiert. Es werden nie mehr als {@see self::MAX_NEW_JOBS} Vakanzen je Lauf verarbeitet.
 */
class JobMatcher
{
    /** Hoechstzahl neuer Vakanzen je Lauf und Profil. */
    public const MAX_NEW_JOBS = 10;

    /** Treffer je Seite der BA-Suche (Matcher). */
    private const PAGE_SIZE = 10;

    /** Hoechstzahl abgefragter Ergebnisseiten je Lauf. */
    private const MAX_PAGES = 3;

    private const MAX_DESCRIPTION_CHARACTERS = 3000;

    /** Zielobergrenze der Zusammenfassung im Prompt (hartes Limit: {@see SavedJob::MAX_AI_SUMMARY_LENGTH}). */
    private const SUMMARY_TARGET_CHARACTERS = 200;

    private const SEARCH_TERM_CACHE_DAYS = 7;

    /** Gueltige Referenznummern (wie im API-Client); schuetzt zusaetzlich das Attribut im Prompt. */
    private const REFNR_PATTERN = '/^[A-Za-z0-9._-]{1,100}\z/D';

    private const SEARCH_TERM_PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N} \-\/+.&()]{1,79}\z/u';

    public function __construct(private JobsucheApiClient $client, private JobDataMapper $mapper) {}

    /**
     * Fuehrt einen Lauf fuer den Benutzer aus.
     *
     * @return array{status: string, found: int, saved: int}
     *
     * @throws RuntimeException wenn die Suche oder der KI-Dienst nicht verwertbar antwortet
     */
    public function run(User $user): array
    {
        if (! $user->hasActiveSubscription() || ! $user->hasCompleteCareerProfile()) {
            return ['status' => 'ineligible', 'found' => 0, 'saved' => 0];
        }

        $languageName = BioOptimizer::LANGUAGE_NAMES[$user->preferred_language] ?? throw new RuntimeException('Unbekannte Sprache.');
        $profile = $this->profileBlock($user);
        $candidates = $this->findNewJobs($user, $this->searchTerm($user, $profile));

        if ($candidates === []) {
            return ['status' => 'no_new_jobs', 'found' => 0, 'saved' => 0];
        }

        $assessments = $this->assess($candidates, $profile, $languageName);
        $saved = $this->store($user, $candidates, $assessments);

        return ['status' => 'done', 'found' => count($candidates), 'saved' => $saved];
    }

    /**
     * Profiltext und Werdegang als gekapselter Datenblock (Benutzereingabe, bereinigt und gekuerzt).
     */
    private function profileBlock(User $user): string
    {
        $lines = [];

        if (trim((string) $user->bio) !== '') {
            $lines[] = 'About me: '.$this->clean((string) $user->bio, User::BIO_MAX_LENGTH);
        }

        $entries = $user->careerEntries()->orderByDesc('from_date')->limit(CareerEntry::MAX_PER_USER)->get();

        foreach ($entries as $entry) {
            $lines[] = sprintf(
                '- %s | %s | %s to %s | %s',
                $this->clean($entry->what, 150),
                $this->clean($entry->where, 150),
                $entry->from_date->format('Y-m'),
                $entry->to_date?->format('Y-m') ?? 'present',
                $this->clean($entry->qualification_or_role, 200),
            );
        }

        return "<candidate_profile>\n".implode("\n", $lines)."\n</candidate_profile>";
    }

    /**
     * Suchbegriff fuer die BA-Suche; je Profilinhalt sieben Tage gecacht, damit nicht jeder Lauf einen KI-Aufruf kostet.
     */
    private function searchTerm(User $user, string $profile): string
    {
        $key = 'matcher:term:'.$user->id.':'.sha1($profile);

        return Cache::remember($key, now()->addDays(self::SEARCH_TERM_CACHE_DAYS), function () use ($profile): string {
            $answer = (string) (new SearchTermAgent)->prompt($profile)->text;
            $answer = preg_replace('#<think>.*?</think>#isu', '', $answer) ?? '';
            $line = trim(strtok(trim($answer), "\r\n") ?: '');
            $term = trim($line, " \t\"'`*“”„«»");

            if (preg_match(self::SEARCH_TERM_PATTERN, $term) !== 1) {
                throw new RuntimeException('Kein gueltiger Suchbegriff des KI-Dienstes.');
            }

            return $term;
        });
    }

    /**
     * Sucht bei der BA und liefert bis zu {@see self::MAX_NEW_JOBS} fuer den Benutzer neue Vakanzen (inkl. Beschreibung).
     *
     * @return array<string, array{refnr: string, title: string|null, employer: string|null, location: string|null, distance: int|null, description: string}>
     */
    private function findNewJobs(User $user, string $term): array
    {
        $found = [];

        for ($page = 1; $page <= self::MAX_PAGES && count($found) < self::MAX_NEW_JOBS; $page++) {
            try {
                $response = $this->client->search(
                    $term,
                    $page,
                    self::PAGE_SIZE,
                    (int) config('matcher.published_within_days'),
                    $user->search_location,
                    $user->search_radius,
                );
            } catch (InvalidArgumentException) {
                throw new RuntimeException('Ungueltige Suchparameter.');
            }

            if ($response === null) {
                if ($page === 1) {
                    throw new RuntimeException('Die BA-Suche ist nicht verfuegbar.');
                }

                break;
            }

            $jobs = $this->mapper->searchResults($response)['jobs'];

            if ($jobs === []) {
                break;
            }

            $new = array_flip(SeenJobReference::unseenFor($user, array_column($jobs, 'refnr')));

            foreach ($jobs as $job) {
                if (count($found) >= self::MAX_NEW_JOBS) {
                    break;
                }

                if (preg_match(self::REFNR_PATTERN, $job['refnr']) === 1 && isset($new[$job['refnr']]) && ! isset($found[$job['refnr']])) {
                    $found[$job['refnr']] = [
                        'refnr' => $job['refnr'],
                        'title' => $job['title'],
                        'employer' => $job['employer'],
                        'location' => $job['location'],
                        'distance' => $job['distance'],
                        'description' => $this->description($job['refnr']),
                    ];
                }
            }
        }

        return $found;
    }

    private function description(string $refnr): string
    {
        $details = $this->client->details($refnr);

        if ($details === null) {
            return '';
        }

        return $this->clean((string) ($this->mapper->jobDetails($details, $refnr)['description'] ?? ''), self::MAX_DESCRIPTION_CHARACTERS);
    }

    /**
     * Bewertet die Kandidaten mit einem KI-Aufruf und validiert die Antwort strikt.
     *
     * @param  array<string, array{refnr: string, title: string|null, employer: string|null, location: string|null, distance: int|null, description: string}>  $candidates
     * @return array<string, array{summary: string, temperature: int}>
     */
    private function assess(array $candidates, string $profile, string $languageName): array
    {
        $blocks = [];

        foreach ($candidates as $job) {
            $blocks[] = sprintf(
                "<untrusted_job_data refnr=\"%s\">\nTitle: %s\nEmployer: %s\nLocation: %s\nDescription: %s\n</untrusted_job_data>",
                $job['refnr'],
                $this->clean((string) $job['title'], 300),
                $this->clean((string) $job['employer'], 300),
                $this->clean((string) $job['location'], 300),
                $job['description'],
            );
        }

        $agent = new JobAssessmentAgent($languageName, self::SUMMARY_TARGET_CHARACTERS);
        $answer = (string) $agent->prompt($profile."\n\n".implode("\n\n", $blocks))->text;

        return $this->parseAssessments($answer, array_keys($candidates));
    }

    /**
     * Liest die JSON-Antwort der KI. Es werden nur bekannte Referenznummern mit ganzzahliger Passung 1 bis 10 und
     * nichtleerer Zusammenfassung uebernommen; zu lange Zusammenfassungen werden auf das Limit gekuerzt.
     *
     * @param  list<string>  $allowedRefnrs
     * @return array<string, array{summary: string, temperature: int}>
     */
    private function parseAssessments(string $answer, array $allowedRefnrs): array
    {
        $answer = preg_replace('#<think>.*?</think>#isu', '', $answer) ?? '';
        $start = strpos($answer, '[');
        $end = strrpos($answer, ']');

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('Keine verwertbare Bewertung des KI-Dienstes.');
        }

        $items = json_decode(substr($answer, $start, $end - $start + 1), true);

        if (! is_array($items)) {
            throw new RuntimeException('Keine verwertbare Bewertung des KI-Dienstes.');
        }

        $valid = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_string($item['refnr'] ?? null) || ! in_array($item['refnr'], $allowedRefnrs, true) || isset($valid[$item['refnr']])) {
                continue;
            }

            $temperature = $this->temperature($item['temperature'] ?? null);
            $summary = is_string($item['summary'] ?? null) ? $this->clean($item['summary'], 2000) : '';

            if ($temperature === null || $summary === '') {
                continue;
            }

            if (mb_strlen($summary) > SavedJob::MAX_AI_SUMMARY_LENGTH) {
                $summary = rtrim(mb_substr($summary, 0, SavedJob::MAX_AI_SUMMARY_LENGTH - 1)).'…';
            }

            $valid[$item['refnr']] = ['summary' => $summary, 'temperature' => $temperature];
        }

        return $valid;
    }

    private function temperature(mixed $value): ?int
    {
        $number = match (true) {
            is_int($value) => $value,
            is_float($value) && floor($value) === $value => (int) $value,
            is_string($value) && preg_match('/^\d{1,2}\z/', $value) === 1 => (int) $value,
            default => null,
        };

        return $number !== null && $number >= SavedJob::MIN_TEMPERATURE && $number <= SavedJob::MAX_TEMPERATURE ? $number : null;
    }

    /**
     * Speichert die bewerteten Vakanzen und merkt sich alle gefundenen Kandidaten als gesehen (auch bei ungueltiger
     * Bewertung), damit keine Vakanz erneut vorgeschlagen wird.
     *
     * @param  array<string, array{refnr: string, title: string|null, employer: string|null, location: string|null, distance: int|null, description: string}>  $candidates
     * @param  array<string, array{summary: string, temperature: int}>  $assessments
     */
    private function store(User $user, array $candidates, array $assessments): int
    {
        return DB::transaction(function () use ($user, $candidates, $assessments): int {
            $saved = 0;

            foreach ($assessments as $refnr => $assessment) {
                if ($saved >= self::MAX_NEW_JOBS || $user->savedJobs()->where('refnr', $refnr)->exists()) {
                    continue;
                }

                $job = $candidates[$refnr];
                $savedJob = new SavedJob([
                    'refnr' => $refnr,
                    'title' => $job['title'],
                    'employer' => $job['employer'],
                    'location' => $job['location'],
                ]);
                $savedJob->forceFill([
                    'source' => SavedJob::SOURCE_AI_MATCHER,
                    'ai_summary' => $assessment['summary'],
                    'temperature' => $assessment['temperature'],
                    'distance_km' => $job['distance'],
                    'briefed_at' => null,
                ]);
                $user->savedJobs()->save($savedJob);
                $saved++;
            }

            SeenJobReference::rememberFor($user, array_keys($candidates));

            return $saved;
        });
    }

    /**
     * Bereinigt Fremdtext fuer den Prompt: Steuerzeichen und Begrenzer-Tags entfernen, Leerraum zusammenfassen, kuerzen.
     */
    private function clean(string $text, int $maxCharacters): string
    {
        $text = preg_replace('#</?\s*(untrusted_job_data|candidate_profile)\b[^>]*>#iu', '', $text) ?? '';
        $text = preg_replace('/[\p{Cc}\p{Cf}]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return mb_substr($text, 0, $maxCharacters);
    }
}
