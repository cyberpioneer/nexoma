<?php

namespace App\Services\SavedJobs;

use App\Models\SavedJob;
use App\Models\User;
use App\Services\Jobsuche\JobDataMapper;
use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Gemeinsame Speicherlogik fuer Weboberflaeche und MCP-Tool. Titel, Arbeitgeber und Ort holt der Server selbst ueber
 * die BA-API (Whitelist des Mappers), nie aus Eingaben. Ist die Stelle schon gespeichert, bleibt es bei einem Eintrag
 * und nur der Vermerk wird aktualisiert (sofern angegeben); die BA-API wird dann nicht erneut abgefragt.
 */
class SavedJobSaver
{
    public function __construct(private JobsucheApiClient $client, private JobDataMapper $mapper) {}

    /**
     * @param  bool  $noteProvided  false: vorhandenen Vermerk unveraendert lassen bzw. ohne Vermerk speichern
     * @param  string|null  $note  bereits zugeschnitten; null entfernt den Vermerk (wenn $noteProvided)
     */
    public function save(User $user, string $refnr, bool $noteProvided, ?string $note): SavedJobSaveResult
    {
        $existing = $this->updateExistingNote($user, $refnr, $noteProvided, $note);

        if ($existing !== null) {
            return new SavedJobSaveResult(SavedJobSaveStatus::AlreadySaved, $existing);
        }

        // Das Limit gilt nur fuer manuell gespeicherte Vakanzen; Treffer des Matchers verbrauchen es nicht.
        if ($user->savedJobs()->manual()->count() >= SavedJob::MAX_PER_USER) {
            return new SavedJobSaveResult(SavedJobSaveStatus::LimitReached);
        }

        $details = $this->client->details($refnr);

        if ($details === null) {
            return new SavedJobSaveResult(SavedJobSaveStatus::Unavailable);
        }

        $job = $this->mapper->jobDetails($details, $refnr);

        try {
            $savedJob = $user->savedJobs()->create([
                'refnr' => $refnr,
                'title' => $job['title'],
                'employer' => $job['employer'],
                'location' => $job['locations'][0] ?? null,
                'note' => $noteProvided ? $note : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Paralleler Aufruf: der Eintrag existiert bereits, es bleibt bei einem.
            $existing = $this->updateExistingNote($user, $refnr, $noteProvided, $note);

            return $existing !== null
                ? new SavedJobSaveResult(SavedJobSaveStatus::AlreadySaved, $existing)
                : new SavedJobSaveResult(SavedJobSaveStatus::Unavailable);
        }

        return new SavedJobSaveResult(SavedJobSaveStatus::Created, $savedJob);
    }

    /**
     * Aktualisiert bei einem vorhandenen Eintrag den Vermerk (nur wenn angegeben); liefert den Eintrag oder null.
     */
    private function updateExistingNote(User $user, string $refnr, bool $noteProvided, ?string $note): ?SavedJob
    {
        $existing = $user->savedJobs()->where('refnr', $refnr)->first();

        if ($existing !== null && $noteProvided) {
            $existing->update(['note' => $note]);
        }

        return $existing;
    }
}
