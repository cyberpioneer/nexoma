<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedJobRequest;
use App\Http\Requests\UpdateSavedJobNoteRequest;
use App\Models\SavedJob;
use App\Models\User;
use App\Services\Jobsuche\JobDataMapper;
use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SavedJobController extends Controller
{
    /**
     * Eigene gespeicherte Vakanzen, neueste zuerst. Nur Whitelist-Felder des angemeldeten Benutzers,
     * hoechstens {@see SavedJob::MAX_PER_USER} Eintraege.
     */
    public function index(Request $request): Response
    {
        $savedJobs = $this->user($request)->savedJobs()
            ->latest()
            ->latest('id')
            ->limit(SavedJob::MAX_PER_USER)
            ->get()
            ->map(fn (SavedJob $savedJob): array => [
                'id' => $savedJob->id,
                'refnr' => $savedJob->refnr,
                'title' => $savedJob->title,
                'employer' => $savedJob->employer,
                'location' => $savedJob->location,
                'note' => $savedJob->note,
                'savedAt' => $savedJob->created_at?->format('Y-m-d'),
            ])
            ->all();

        return Inertia::render('SavedJobs/Index', ['savedJobs' => $savedJobs]);
    }

    /**
     * Speichert eine Vakanz. Titel, Arbeitgeber und Ort holt der Server selbst ueber die BA-API (Whitelist des
     * Mappers). Ist die Stelle schon gespeichert, bleibt es bei einem Eintrag und nur der Vermerk wird aktualisiert;
     * die BA-API wird dann nicht erneut abgefragt.
     */
    public function store(StoreSavedJobRequest $request, JobsucheApiClient $client, JobDataMapper $mapper): RedirectResponse
    {
        $user = $this->user($request);
        $refnr = (string) $request->validated('refnr');

        if ($this->updateExistingNote($request, $user, $refnr)) {
            return back();
        }

        if ($user->savedJobs()->count() >= SavedJob::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'refnr' => __('ui.saved.error.limit', ['max' => SavedJob::MAX_PER_USER]),
            ]);
        }

        $details = $client->details($refnr);

        if ($details === null) {
            throw ValidationException::withMessages(['refnr' => __('ui.saved.error.unavailable')]);
        }

        $job = $mapper->jobDetails($details, $refnr);

        try {
            $user->savedJobs()->create([
                'refnr' => $refnr,
                'title' => $job['title'],
                'employer' => $job['employer'],
                'location' => $job['locations'][0] ?? null,
                'note' => $request->note(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Paralleler Doppelklick: der Eintrag existiert bereits, es bleibt bei einem.
            $this->updateExistingNote($request, $user, $refnr);
        }

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

    /**
     * Aktualisiert bei einem vorhandenen Eintrag den Vermerk (nur wenn mitgesendet); liefert, ob er existiert.
     */
    private function updateExistingNote(StoreSavedJobRequest $request, User $user, string $refnr): bool
    {
        $existing = $user->savedJobs()->where('refnr', $refnr)->first();

        if ($existing === null) {
            return false;
        }

        if ($request->has('note')) {
            $existing->update(['note' => $request->note()]);
        }

        return true;
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
