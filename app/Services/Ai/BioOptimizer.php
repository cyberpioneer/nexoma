<?php

namespace App\Services\Ai;

use App\Ai\Agents\BioOptimizerAgent;
use App\Models\User;
use RuntimeException;

/**
 * Optimiert den Profiltext ueber MiniMax (Laravel AI SDK). Der Benutzertext wird als Daten gekapselt,
 * die Sprache ueber eine feste Whitelist aufgeloest und das Zeichenlimit strikt eingehalten.
 */
class BioOptimizer
{
    /** Zielobergrenze, die dem Modell genannt wird (etwas unter dem harten Limit, damit ein Versuch genuegt). */
    private const TARGET_CHARACTERS = 400;

    /** Englische Sprachnamen je unterstuetztem Sprachcode (nie Rohwerte aus Anfragen in den Prompt). */
    private const LANGUAGE_NAMES = [
        'de' => 'German',
        'en' => 'English',
        'ru' => 'Russian',
        'uk' => 'Ukrainian',
        'es' => 'Spanish',
        'it' => 'Italian',
    ];

    /**
     * @throws RuntimeException wenn keine gueltige, ausreichend kurze Antwort entsteht
     */
    public function optimize(string $bio, string $language): string
    {
        $languageName = self::LANGUAGE_NAMES[$language] ?? throw new RuntimeException('Unbekannte Sprache.');
        $agent = new BioOptimizerAgent($languageName, self::TARGET_CHARACTERS);

        $prompt = $this->wrap($bio);
        $result = $this->clean((string) $agent->prompt($prompt)->text);

        if (mb_strlen($result) > User::BIO_MAX_LENGTH) {
            $result = $this->clean((string) $agent->prompt(
                $prompt."\n\nYour previous answer was too long. Answer again with at most ".self::TARGET_CHARACTERS.' characters.',
            )->text);
        }

        if ($result === '' || mb_strlen($result) > User::BIO_MAX_LENGTH) {
            throw new RuntimeException('Keine gueltige Antwort des KI-Dienstes.');
        }

        return $result;
    }

    /**
     * Kapselt den Benutzertext; enthaltene Begrenzer-Tags werden entfernt, damit er den Rahmen nicht verlassen kann.
     */
    private function wrap(string $bio): string
    {
        $safe = preg_replace('#</?\s*user_bio\s*>#iu', '', $bio) ?? '';

        return "<user_bio>\n".trim($safe)."\n</user_bio>";
    }

    /**
     * Entfernt Denkbloecke (`<think>`), umschliessende Anfuehrungszeichen und Leerraum.
     */
    private function clean(string $text): string
    {
        $text = preg_replace('#<think>.*?</think>#isu', '', $text) ?? '';
        $text = preg_replace('#</?think>#iu', '', $text) ?? '';
        $text = trim($text);

        return trim($text, " \t\n\r\0\x0B\"“”„«»");
    }
}
