<?php

/**
 * Alle Vue- und TypeScript-Dateien unter resources/js (ohne generierte Ordner).
 *
 * @return list<string>
 */
function frontendSourceFiles(): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        if (preg_match('/\.(vue|ts)$/', $path) && ! preg_match('#/js/(actions|routes|wayfinder)/#', $path)) {
            $files[] = $path;
        }
    }

    return $files;
}

test('the root template and the stylesheet reference no external hosts', function () {
    foreach ([resource_path('views/app.blade.php'), resource_path('css/app.css')] as $file) {
        expect((string) file_get_contents($file))->not->toMatch('#https?://#i', $file);
    }
});

test('no frontend source loads fonts from a third party', function () {
    foreach (frontendSourceFiles() as $file) {
        expect((string) file_get_contents($file))
            ->not->toMatch('#rsms\.me|bunny\.net|googleapis\.com|gstatic\.com|typekit|fontshare|fontawesome#i', $file);
    }
});

test('the built font stylesheets only use local font files', function () {
    $stylesheets = glob(public_path('build/assets/{fonts,app}-*.css'), GLOB_BRACE) ?: [];

    if ($stylesheets === []) {
        $this->markTestSkipped('No frontend build available.');
    }

    foreach ($stylesheets as $stylesheet) {
        preg_match_all('/url\(\s*["\']?([^"\')]+)/', (string) file_get_contents($stylesheet), $matches);

        foreach ($matches[1] as $url) {
            expect($url)->not->toMatch('#^(https?:)?//#', $stylesheet);
        }
    }
});

test('the frontend never renders raw html and never uses localStorage', function () {
    foreach (frontendSourceFiles() as $file) {
        expect((string) file_get_contents($file))->not->toMatch('/v-html|innerHTML|localStorage|sessionStorage/', $file);
    }
});
