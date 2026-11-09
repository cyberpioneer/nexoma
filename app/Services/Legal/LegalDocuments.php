<?php

namespace App\Services\Legal;

use RuntimeException;

/**
 * Liest die Rechtstexte aus `resources/legal/*.md` und wandelt sie in eine Block-Struktur um.
 *
 * Der Parser kennt nur Ueberschriften, Absaetze und Listen und liefert ausschliesslich Text. HTML-Kommentare werden
 * entfernt, alles andere (auch etwaige Tags in der Quelle) bleibt Text und wird vom Client nur als Text ausgegeben.
 *
 * @phpstan-type Block array{type: 'h1'|'h2', text: string}|array{type: 'p', lines: list<string>}|array{type: 'ul', items: list<string>}
 */
class LegalDocuments
{
    public function __construct(private readonly ?string $directory = null) {}

    /**
     * Verzeichnis der Markdown-Dateien.
     */
    public function directory(): string
    {
        return $this->directory ?? resource_path('legal');
    }

    /**
     * Dateipfade aller Rechtstexte, sortiert.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob(rtrim($this->directory(), '/').'/*.md') ?: [];
        sort($files);

        return $files;
    }

    /**
     * Block-Struktur eines Textes. Der Name ist der Dateiname ohne Endung und kommt nur aus festen Routen.
     *
     * @return list<Block>
     */
    public function blocks(string $name): array
    {
        $path = rtrim($this->directory(), '/').'/'.$name.'.md';

        if (preg_match('/\A[a-z0-9_-]+\z/', $name) !== 1 || ! is_file($path)) {
            throw new RuntimeException("Rechtstext [{$name}] nicht gefunden.");
        }

        return $this->parse((string) file_get_contents($path));
    }

    /**
     * Wandelt Markdown (Ueberschriften `#`, Absaetze, Listen `-`) in Bloecke um.
     *
     * @return list<Block>
     */
    public function parse(string $markdown): array
    {
        $blocks = [];
        $lines = [];
        $items = [];

        foreach (preg_split('/\R/u', $this->stripComments($markdown)) ?: [] as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                $this->flush($blocks, $lines, $items);

                continue;
            }

            if (preg_match('/\A(#{1,6})\s+(.+)\z/u', $line, $heading) === 1) {
                $this->flush($blocks, $lines, $items);
                $blocks[] = ['type' => strlen($heading[1]) === 1 ? 'h1' : 'h2', 'text' => trim($heading[2])];

                continue;
            }

            if (preg_match('/\A-\s+(.+)\z/u', $line, $item) === 1) {
                $this->flushLines($blocks, $lines);
                $items[] = trim($item[1]);

                continue;
            }

            $this->flushItems($blocks, $items);
            $lines[] = $line;
        }

        $this->flush($blocks, $lines, $items);

        return $blocks;
    }

    /**
     * @param  list<Block>  $blocks
     * @param  list<string>  $lines
     * @param  list<string>  $items
     */
    private function flush(array &$blocks, array &$lines, array &$items): void
    {
        $this->flushLines($blocks, $lines);
        $this->flushItems($blocks, $items);
    }

    /**
     * @param  list<Block>  $blocks
     * @param  list<string>  $lines
     */
    private function flushLines(array &$blocks, array &$lines): void
    {
        if ($lines !== []) {
            $blocks[] = ['type' => 'p', 'lines' => $lines];
            $lines = [];
        }
    }

    /**
     * @param  list<Block>  $blocks
     * @param  list<string>  $items
     */
    private function flushItems(array &$blocks, array &$items): void
    {
        if ($items !== []) {
            $blocks[] = ['type' => 'ul', 'items' => $items];
            $items = [];
        }
    }

    /**
     * Entfernt HTML-Kommentare, auch unvollstaendige und verschachtelte Konstrukte.
     */
    public function stripComments(string $markdown): string
    {
        do {
            $before = $markdown;
            $markdown = (string) preg_replace('/<!--.*?(?:-->|\z)/su', '', $markdown);
        } while ($markdown !== $before);

        return $markdown;
    }

    /**
     * Platzhalter in eckigen Klammern ausserhalb von Kommentaren und Markdown-Links, je Fundstelle mit Zeilennummer.
     *
     * @return list<array{line: int, placeholder: string}>
     */
    public function placeholders(string $markdown): array
    {
        $found = [];
        $visible = $this->stripComments($markdown);

        foreach (preg_split('/\R/u', $visible) ?: [] as $index => $line) {
            $line = (string) preg_replace('/\[[^\]]*\]\([^)]*\)/u', '', $line);

            if (preg_match_all('/\[[^\[\]]+\]/u', $line, $matches) > 0) {
                foreach ($matches[0] as $placeholder) {
                    $found[] = ['line' => $index + 1, 'placeholder' => $placeholder];
                }
            }
        }

        return $found;
    }
}
