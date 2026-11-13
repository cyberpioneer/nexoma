<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedJobRequest;
use App\Http\Requests\UpdateSavedJobNoteRequest;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\SavedJobs\SavedJobSaver;
use App\Services\SavedJobs\SavedJobSaveStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SavedJobController extends Controller
{
    /**
     * Eigene gespeicherte Vakanzen, neueste zuerst. Nur Whitelist-Felder des angemeldeten Benutzers,
     * hoechstens {@see SavedJob::MAX_PER_USER} Eintraege. Vorschlaege des Matchers enthalten zusaetzlich `ai`
     * (Zusammenfassung, Passung 1 bis 10, Entfernung in km).
     */
    public function index(Request $request): Response
    {
        $savedJobs = $this->user($request)->savedJobs()
            ->latest()
            ->latest('id')
            ->limit(SavedJob::MAX_PER_USER)
            ->get()
            ->map(function (SavedJob $savedJob): array {
                $row = [
                    'id' => $savedJob->id,
                    'refnr' => $savedJob->refnr,
                    'title' => $savedJob->title,
                    'employer' => $savedJob->employer,
                    'location' => $savedJob->location,
                    'note' => $savedJob->note,
                    'savedAt' => $savedJob->created_at?->format('Y-m-d'),
                ];

                // Nur Vorschlaege des Matchers tragen KI-Angaben; manuell gespeicherte Eintraege bleiben unveraendert.
                if ($savedJob->source === SavedJob::SOURCE_AI_MATCHER) {
                    $row['ai'] = [
                        'summary' => $savedJob->ai_summary,
                        'temperature' => $savedJob->temperature,
                        'distanceKm' => $savedJob->distance_km,
                    ];
                }

                return $row;
            })
            ->all();

        return Inertia::render('SavedJobs/Index', ['savedJobs' => $savedJobs]);
    }

    /**
     * Speichert eine Vakanz ueber {@see SavedJobSaver}: Titel, Arbeitgeber und Ort holt der Server selbst ueber die
     * BA-API. Ist die Stelle schon gespeichert, bleibt es bei einem Eintrag und nur der Vermerk wird aktualisiert.
     */
    public function store(StoreSavedJobRequest $request, SavedJobSaver $saver): RedirectResponse
    {
        $result = $saver->save(
            $this->user($request),
            (string) $request->validated('refnr'),
            $request->has('note'),
            $request->note(),
        );

        match ($result->status) {
            SavedJobSaveStatus::LimitReached => throw ValidationException::withMessages([
                'refnr' => __('ui.saved.error.limit', ['max' => SavedJob::MAX_PER_USER]),
            ]),
            SavedJobSaveStatus::Unavailable => throw ValidationException::withMessages(['refnr' => __('ui.saved.error.unavailable')]),
            default => null,
        };

        return back();
    }

    /**
     * Aendert den Vermerk eines eigenen Eintrags; leer entfernt ihn. Fremde Eintraege ergeben 404.
     */
    public function update(UpdateSavedJobNoteRequest $request, int $savedJob): RedirectResponse
    {
        $this->ownSavedJob($request, $savedJob)->update(['note' => $request->note()]);

        return back();
    }

    /**
     * Loescht einen eigenen Eintrag. Fremde Eintraege ergeben 404.
     */
    public function destroy(Request $request, int $savedJob): RedirectResponse
    {
        $this->ownSavedJob($request, $savedJob)->delete();

        return back();
    }

    private function ownSavedJob(Request $request, int $id): SavedJob
    {
        return $this->user($request)->savedJobs()->findOrFail($id);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
