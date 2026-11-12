<?php

namespace App\Http\Controllers;

use App\Models\AudioBriefing;
use App\Policies\AudioBriefingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Liefert Audio-Briefings aus. Fremde Briefings werden ueber {@see AudioBriefingPolicy} mit 403 abgewiesen;
 * die Datei liegt auf der privaten Disk und wird nur hier ausgeliefert (mit HTTP-Range-Unterstuetzung fuer den Player).
 */
class AudioBriefingController extends Controller
{
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
