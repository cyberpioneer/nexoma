<?php

namespace App\Console\Commands;

use App\Services\Legal\LegalDocuments;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('legal:check {--path= : Verzeichnis der Rechtstexte (Standard: resources/legal)}')]
#[Description('Listet offene Platzhalter in eckigen Klammern in den Rechtstexten auf (Exit-Code 1, wenn welche vorhanden sind)')]
class LegalCheckCommand extends Command
{
    public function handle(): int
    {
        $path = $this->option('path');
        $documents = new LegalDocuments(is_string($path) && $path !== '' ? $path : null);
        $open = 0;

        foreach ($documents->files() as $file) {
            foreach ($documents->placeholders((string) file_get_contents($file)) as $finding) {
                $this->line(basename($file).':'.$finding['line'].' '.$finding['placeholder']);
                $open++;
            }
        }

        if ($open > 0) {
            $this->error("{$open} offene Platzhalter in den Rechtstexten.");

            return self::FAILURE;
        }

        $this->info('Keine offenen Platzhalter.');

        return self::SUCCESS;
    }
}
