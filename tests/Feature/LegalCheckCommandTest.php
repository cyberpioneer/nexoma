<?php

use App\Services\Legal\LegalDocuments;

/**
 * Legt ein Verzeichnis mit Fixture-Dateien an.
 *
 * @param  array<string, string>  $files
 */
function legalFixtureDirectory(array $files): string
{
    $directory = sys_get_temp_dir().'/legal-check-'.bin2hex(random_bytes(4));
    mkdir($directory);

    foreach ($files as $name => $content) {
        file_put_contents($directory.'/'.$name, $content);
    }

    return $directory;
}

test('legal:check lists open placeholders and fails', function () {
    $directory = legalFixtureDirectory([
        'a.md' => "# A\n\nE-Mail: [E-Mail-Adresse]\n\n<!-- [im Kommentar] -->\n[Doku](https://example.test) bleibt\n",
        'b.md' => "Hoster: [Name des Hosters]\n",
    ]);

    $this->artisan('legal:check', ['--path' => $directory])
        ->expectsOutputToContain('a.md:3 [E-Mail-Adresse]')
        ->expectsOutputToContain('b.md:1 [Name des Hosters]')
        ->expectsOutputToContain('2 offene Platzhalter')
        ->assertExitCode(1);

    array_map('unlink', glob($directory.'/*.md'));
    rmdir($directory);
});

test('legal:check succeeds without open placeholders', function () {
    $directory = legalFixtureDirectory(['a.md' => "# A\n\nAlles ausgefüllt.\n\n<!-- [Hinweis] -->\n[Link](https://example.test)\n"]);

    $this->artisan('legal:check', ['--path' => $directory])
        ->expectsOutputToContain('Keine offenen Platzhalter')
        ->assertExitCode(0);

    unlink($directory.'/a.md');
    rmdir($directory);
});

test('legal:check currently reports no open placeholders in the shipped texts', function () {
    $documents = new LegalDocuments;
    $found = collect($documents->files())
        ->flatMap(fn (string $file): array => $documents->placeholders((string) file_get_contents($file)))
        ->pluck('placeholder')
        ->all();

    expect($found)->toBe([]);

    $this->artisan('legal:check')->assertExitCode(0);
});
