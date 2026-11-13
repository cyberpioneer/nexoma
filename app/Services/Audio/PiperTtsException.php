<?php

namespace App\Services\Audio;

use RuntimeException;

/**
 * Fehler der Sprachsynthese. `errorCode` ist ein kurzer, stabiler Code ohne Nutzdaten (fuer `audio_briefings.error_code`);
 * die Meldung enthaelt weder Sprechtext noch Prozessausgaben.
 */
class PiperTtsException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
