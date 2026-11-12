<?php

namespace App\Services\Audio;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Synthetisiert Sprechtext lokal mit Piper (externes Python-Paket, ONNX-Stimmen) zu einer WAV-Datei.
 *
 * Sicherheit: Der Prozess startet ohne Shell mit Argument-Array; der Sprechtext geht ausschliesslich ueber stdin.
 * Die Stimme wird nur ueber die feste Sprach-Whitelist `services.piper.voices` aufgeloest (zusaetzlich per Muster
 * auf einen reinen Dateinamen beschraenkt), der Ausgabepfad entsteht serverseitig. Die temporaere Datei wird
 * in jedem Fall geloescht, das Ergebnis liegt nur auf der privaten Disk.
 */
class PiperTtsService
{
    public const MAX_TEXT_LENGTH = 8000;

    /** Obergrenze fuer die erzeugte Datei (Schutz vor unbegrenztem Speicherverbrauch). */
    public const MAX_AUDIO_BYTES = 100 * 1024 * 1024;

    /**
     * Erzeugt die WAV-Datei und legt sie als `<uuid>.wav` auf der konfigurierten privaten Disk ab.
     *
     * @return array{path: string, duration_ms: int} Pfad relativ zur Disk und Dauer
     *
     * @throws PiperTtsException
     */
    public function synthesize(string $text, string $locale): array
    {
        $text = trim($text);

        if ($text === '' || mb_strlen($text) > self::MAX_TEXT_LENGTH || str_contains($text, "\0") || ! mb_check_encoding($text, 'UTF-8')) {
            throw new PiperTtsException('invalid_text');
        }

        $model = $this->modelPath($locale);
        $temporary = tempnam(sys_get_temp_dir(), 'nexoma-tts-');

        if ($temporary === false) {
            throw new PiperTtsException('temp_file_failed');
        }

        try {
            $this->runPiper($text, $model, $temporary);

            $duration = $this->validatedDurationMs($temporary);

            return ['path' => $this->store($temporary), 'duration_ms' => $duration];
        } finally {
            @unlink($temporary);
        }
    }

    /**
     * Loest die Stimme ausschliesslich ueber die Whitelist auf und prueft, dass die Modelldatei existiert.
     */
    private function modelPath(string $locale): string
    {
        $voices = (array) config('services.piper.voices', []);
        $voice = array_key_exists($locale, $voices) ? $voices[$locale] : null;

        if (! is_string($voice) || preg_match('/^[A-Za-z0-9_-]+$/', $voice) !== 1) {
            throw new PiperTtsException('invalid_locale');
        }

        $path = rtrim((string) config('services.piper.data_dir'), '/').'/'.$voice.'.onnx';

        if (! is_file($path)) {
            throw new PiperTtsException('voice_missing');
        }

        return $path;
    }

    private function runPiper(string $text, string $model, string $outputFile): void
    {
        try {
            $result = Process::timeout((int) config('services.piper.timeout', 300))
                ->input($text)
                ->run([
                    (string) config('services.piper.python_binary'),
                    '-m', 'piper',
                    '-m', $model,
                    '--data-dir', (string) config('services.piper.data_dir'),
                    '-f', $outputFile,
                ]);
        } catch (ProcessTimedOutException) {
            throw new PiperTtsException('piper_timeout');
        }

        if (! $result->successful()) {
            // Weder stderr noch Text ins Log: Piper kann den Sprechtext (Personenbezug) wiedergeben.
            Log::warning('Piper-Synthese fehlgeschlagen.', ['exit_code' => $result->exitCode()]);

            throw new PiperTtsException('piper_failed');
        }
    }

    /**
     * Prueft Groesse sowie RIFF/WAVE-Header und liefert die Dauer in Millisekunden.
     */
    private function validatedDurationMs(string $file): int
    {
        $size = filesize($file);
        $handle = fopen($file, 'rb');

        if ($size === false || $handle === false || $size < 44 || $size > self::MAX_AUDIO_BYTES) {
            throw new PiperTtsException('invalid_audio');
        }

        $header = (string) fread($handle, 4096);
        fclose($handle);

        if (substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WAVE') {
            throw new PiperTtsException('invalid_audio');
        }

        $byteRate = null;
        $dataSize = null;
        $offset = 12;

        while ($offset + 8 <= strlen($header)) {
            $id = substr($header, $offset, 4);
            /** @var array{1: int} $length */
            $length = unpack('V', substr($header, $offset + 4, 4));

            if ($id === 'fmt ' && $offset + 20 <= strlen($header)) {
                /** @var array{1: int} $rate */
                $rate = unpack('V', substr($header, $offset + 16, 4));
                $byteRate = $rate[1];
            }

            if ($id === 'data') {
                $dataSize = min($length[1], $size - ($offset + 8));

                break;
            }

            $offset += 8 + $length[1] + ($length[1] % 2);
        }

        if ($byteRate === null || $byteRate <= 0 || $dataSize === null || $dataSize <= 0) {
            throw new PiperTtsException('invalid_audio');
        }

        return (int) round($dataSize / $byteRate * 1000);
    }

    private function store(string $file): string
    {
        $path = trim((string) config('services.piper.directory', 'audio-briefings'), '/').'/'.Str::uuid()->toString().'.wav';
        $stream = fopen($file, 'rb');

        if ($stream === false) {
            throw new PiperTtsException('storage_failed');
        }

        try {
            $stored = Storage::disk((string) config('services.piper.disk', 'local'))->put($path, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new PiperTtsException('storage_failed');
        }

        return $path;
    }
}
