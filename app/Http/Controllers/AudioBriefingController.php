<?php

namespace App\Http\Controllers;

use App\Models\AudioBriefing;
use App\Policies\AudioBriefingPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert Audio-Briefings aus. Fremde Briefings werden ueber {@see AudioBriefingPolicy} mit 403 abgewiesen;
 * die Datei liegt auf der privaten Disk und wird nur hier ausgeliefert (mit HTTP-Range-Unterstuetzung fuer den Player).
 */
class AudioBriefingController extends Controller
{
    /** Anzahl der Briefings je Seite der Liste (Datenbegrenzung). */
    public const PER_PAGE = 10;

    /**
     * Liste der eigenen, fertigen Briefings (neueste zuerst). Es werden nur Whitelist-Felder ausgegeben; Sprechtext,
     * Dateipfad und Fehlercode verlassen den Server nicht.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $page = $user->audioBriefings()->ready()->latest()->latest('id')->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('SavedBriefings/Index', [
            'briefings' => $page->getCollection()->map(fn (AudioBriefing $briefing): array => [
                'id' => $briefing->id,
                'durationMs' => $briefing->duration_ms ?? 0,
                'createdAt' => $briefing->created_at?->format('Y-m-d'),
            ])->values()->all(),
            'pagination' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'total' => $page->total(),
                'previousUrl' => $page->previousPageUrl(),
                'nextUrl' => $page->nextPageUrl(),
            ],
        ]);
    }

    public function stream(AudioBriefing $audioBriefing): BinaryFileResponse
    {
        Gate::authorize('view', $audioBriefing);

        $relative = (string) $audioBriefing->storage_path;
        $directory = trim((string) config('services.piper.directory', 'audio-briefings'), '/');

        // Nur Dateien, die der Dienst selbst angelegt hat (`<directory>/<uuid>.wav`); nie ein Pfad aus Anfragedaten.
        abort_unless(
            $audioBriefing->isReady() && preg_match('#^'.preg_quote($directory, '#').'/[0-9a-f-]{36}\.wav\z#D', $relative) === 1,
            404,
        );

        $disk = Storage::disk((string) config('services.piper.disk', 'local'));

        abort_unless($disk->exists($relative), 404);

        $response = response()->file($disk->path($relative), [
            'Content-Type' => 'audio/wav',
            'Content-Disposition' => 'inline; filename="briefing-'.$audioBriefing->id.'.wav"',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // Symfony markiert Dateiantworten standardmaessig als `public`; persoenliche Audiodaten duerfen nie in
        // gemeinsamen Caches (Proxys, CDN) landen.
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');

        return $response;
    }
}
