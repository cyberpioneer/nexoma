<?php

use App\Models\User;
use App\Services\Legal\LegalDocuments;
use Inertia\Testing\AssertableInertia as Assert;

dataset('legal pages', [
    'imprint' => ['legal.imprint', '/impressum', 'footer.imprint'],
    'privacy' => ['legal.privacy', '/datenschutz', 'footer.privacy'],
    'disclaimer' => ['legal.disclaimer', '/haftungsausschluss', 'footer.disclaimer'],
]);

test('guests and users can open the legal pages with blocks and a translated title', function (string $name, string $uri, string $titleKey) {
    expect(route($name, absolute: false))->toBe($uri);

    $this->get(route($name))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Legal/Show')
            ->has('blocks')
            ->where('translations', fn ($translations): bool => $translations[$titleKey] !== $titleKey));

    auth()->forgetGuards();
    $this->actingAs(User::factory()->create())->get($uri)->assertOk();
})->with('legal pages');

test('the served blocks contain no comments, drafting notes or raw markdown', function (string $name) {
    $json = json_encode($this->get(route($name))->viewData('page')['props']['blocks'], JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('<!--')
        ->and($json)->not->toContain('Hinweise zum Entwurf')
        ->and($json)->not->toContain('lawdb');

    $this->get(route($name))->assertInertia(fn (Assert $page) => $page
        ->where('blocks', function ($blocks): bool {
            foreach ($blocks as $block) {
                $texts = $block['lines'] ?? $block['items'] ?? [$block['text']];

                foreach ($texts as $text) {
                    if (preg_match('/\A(#|-\s)/', $text) === 1) {
                        return false;
                    }
                }
            }

            return true;
        }));
})->with(['legal.imprint', 'legal.privacy', 'legal.disclaimer']);

test('the texts name the provider and the imprint has no register or legal form data', function () {
    $imprint = json_encode(app(LegalDocuments::class)->blocks('impressum'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($imprint)->toContain('NOMNIX // Slawa Mironow', 'Dr.-Schier-Straße 21', '97980 Bad Mergentheim', 'service@nomnix.net')
        ->and($imprint)->not->toContain('Registergericht', 'Registernummer', 'HRB', 'Vertretungsberechtigt', 'Rechtsform', 'Umsatzsteuer');

    foreach (['datenschutzerklaerung', 'haftungsausschluss'] as $file) {
        expect(json_encode(app(LegalDocuments::class)->blocks($file), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->toContain('NOMNIX // Slawa Mironow');
    }
});

test('the parser outputs only text blocks and never markup', function () {
    $markdown = <<<'MD'
# Titel <script>alert(1)</script>

<!-- Kommentar
mit <b>Tags</b> -->
Zeile eins
Zeile <img src=x onerror=alert(1)> zwei

## Abschnitt
- Punkt <b>fett</b>
- Zweiter [Link](https://example.test)

<!-- offen und nie geschlossen
Geheim
MD;

    $blocks = (new LegalDocuments)->parse($markdown);

    expect($blocks)->toBe([
        ['type' => 'h1', 'text' => 'Titel <script>alert(1)</script>'],
        ['type' => 'p', 'lines' => ['Zeile eins', 'Zeile <img src=x onerror=alert(1)> zwei']],
        ['type' => 'h2', 'text' => 'Abschnitt'],
        ['type' => 'ul', 'items' => ['Punkt <b>fett</b>', 'Zweiter [Link](https://example.test)']],
    ]);

    expect(json_encode($blocks))->not->toContain('Geheim')->not->toContain('Kommentar');
});

test('nested comment openers cannot be used to smuggle a comment through', function () {
    expect((new LegalDocuments)->parse('Text <!<!-- x -->-- versteckt --> Ende'))
        ->toBe([['type' => 'p', 'lines' => ['Text  Ende']]]);
});

test('a prepared source file is served as text blocks', function () {
    $directory = sys_get_temp_dir().'/legal-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory.'/impressum.md', "# Prepared\n\n<script>alert(1)</script>\n");
    $this->app->instance(LegalDocuments::class, new LegalDocuments($directory));

    $this->get(route('legal.imprint'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('blocks', [
                ['type' => 'h1', 'text' => 'Prepared'],
                ['type' => 'p', 'lines' => ['<script>alert(1)</script>']],
            ]));

    unlink($directory.'/impressum.md');
    rmdir($directory);
});

test('a missing source file ends in a server error instead of rendering a page', function () {
    $this->app->instance(LegalDocuments::class, new LegalDocuments('/nonexistent-legal-dir'));

    $this->get(route('legal.imprint'))->assertServerError();
});

test('the footer labels exist and are translated in every locale', function (string $locale) {
    $this->withSession(['locale' => $locale])->get(route('legal.imprint'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('translations', fn ($translations): bool => collect($translations)
                ->only(['footer.legal', 'footer.imprint', 'footer.privacy', 'footer.disclaimer', 'legal.german_only'])
                ->every(fn (string $text, string $key): bool => $text !== '' && $text !== $key)));
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);

test('unknown or traversing document names are rejected', function (string $name) {
    expect(fn () => app(LegalDocuments::class)->blocks($name))->toThrow(RuntimeException::class);
})->with(['unknown', '../x', '../../.env']);
