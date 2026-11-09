<?php

use App\Services\Jobsuche\JobDataMapper;

test('only clean https urls are accepted', function (mixed $url, ?string $expected) {
    expect((new JobDataMapper)->safeHttpUrl($url))->toBe($expected);
})->with([
    'valid' => ['https://example.test/a?b=1', 'https://example.test/a?b=1'],
    'valid with port' => ['https://example.test:8443/a', 'https://example.test:8443/a'],
    'valid punycode host' => ['https://xn--bcher-kva.example/a', 'https://xn--bcher-kva.example/a'],
    'upper case scheme' => ['HTTPS://example.test', 'HTTPS://example.test'],
    'http is dropped' => ['http://example.test', null],
    'javascript' => ['javascript:alert(1)', null],
    'javascript with host' => ['javascript://example.test/%0Aalert(1)', null],
    'data' => ['data:text/plain,x', null],
    'ftp' => ['ftp://example.test', null],
    'no host' => ['https:///nohost', null],
    'userinfo' => ['https://user:pass@example.test/', null],
    'userinfo disguising the host' => ['https://example.test@evil.test/', null],
    'userinfo without password' => ['https://user@example.test/', null],
    'backslash after host' => ['https://example.test\\@evil.test/', null],
    'backslash in path' => ['https://example.test/a\\b', null],
    'bidi override' => ["https://example.test/\u{202E}txt.exe", null],
    'zero width space in host' => ["https://exam\u{200B}ple.test/", null],
    'tab inside' => ["https://example.test/a\tb", null],
    'null byte' => ["https://example.test/a\x00b", null],
    'space inside' => ['https://example.test/a b', null],
    'newline inside' => ["https://example.test/\nx", null],
    'unicode host' => ['https://bücher.example/', null],
    'cyrillic lookalike host' => ["https://\u{0430}pple.example/", null],
    'invalid utf-8' => ["https://example.test/\xFF", null],
    'ipv6 host' => ['https://[::1]/', null],
    'empty' => ['', null],
    'null' => [null, null],
    'int' => [123, null],
]);

test('the url length limit is 2048 bytes', function () {
    $mapper = new JobDataMapper;
    $atLimit = 'https://example.test/'.str_repeat('a', 2048 - strlen('https://example.test/'));

    expect(strlen($atLimit))->toBe(2048)
        ->and($mapper->safeHttpUrl($atLimit))->toBe($atLimit)
        ->and($mapper->safeHttpUrl($atLimit.'a'))->toBeNull();
});

test('the application host is shown for a valid url and absent otherwise', function () {
    $mapper = new JobDataMapper;

    expect($mapper->jobDetails(['externeURL' => 'https://Bewerbung.Example/x?y=1'], 'A-1'))
        ->toMatchArray(['applicationUrl' => 'https://Bewerbung.Example/x?y=1', 'applicationHost' => 'bewerbung.example'])
        ->and($mapper->jobDetails(['externeURL' => 'http://bewerbung.example/'], 'A-1'))
        ->toMatchArray(['applicationUrl' => null, 'applicationHost' => null]);
});

test('malformed search items are skipped or reduced instead of failing', function () {
    $result = (new JobDataMapper)->searchResults([
        'ergebnisliste' => [
            'kein array',
            ['referenznummer' => 'ungültig!', 'stellenangebotsTitel' => 'x'],
            ['stellenangebotsTitel' => 'ohne refnr'],
            [
                'referenznummer' => 'A-1',
                'stellenangebotsTitel' => str_repeat('ü', 400),
                'firma' => ['kein text'],
                'veroeffentlichungszeitraum' => 'kein array',
                'eintrittszeitraum' => ['von' => '2026-13-45'],
                'stellenangebotsart' => 'UNBEKANNT',
                'entfernung' => '5',
                'stellenlokationen' => 'kein array',
            ],
        ],
        'maxErgebnisse' => '42',
    ]);

    expect($result['total'])->toBe(42)
        ->and($result['jobs'])->toHaveCount(1)
        ->and($result['jobs'][0])->toMatchArray([
            'refnr' => 'A-1',
            'employer' => null,
            'location' => null,
            'distance' => null,
            'publishedAt' => null,
            'startDate' => null,
            'offerType' => null,
        ])
        ->and(mb_strlen($result['jobs'][0]['title']))->toBe(300);
});

test('legacy field names from the openapi are used as a fallback', function () {
    $result = (new JobDataMapper)->searchResults([
        'ergebnisliste' => [[
            'refnr' => 'B-2', 'titel' => 'Titel', 'arbeitgeber' => 'AG', 'beruf' => 'Beruf',
            'arbeitsort' => ['plz' => '80331', 'ort' => 'München'],
            'aktuelleVeroeffentlichungsdatum' => '2026-01-02', 'eintrittsdatum' => '2026-02-03',
        ]],
    ]);

    expect($result['jobs'][0])->toMatchArray([
        'refnr' => 'B-2', 'title' => 'Titel', 'employer' => 'AG', 'profession' => 'Beruf',
        'location' => '80331 München', 'publishedAt' => '2026-01-02', 'startDate' => '2026-02-03',
    ]);
});

test('details fall back to the requested reference number and tolerate missing fields', function () {
    $job = (new JobDataMapper)->jobDetails(['titel' => 'Nur Titel'], 'C-3');

    expect($job)->toMatchArray([
        'refnr' => 'C-3', 'title' => 'Nur Titel', 'locations' => [], 'description' => null,
        'workingTimes' => [], 'homeOffice' => false, 'applicationUrl' => null,
        'officialUrl' => 'https://www.arbeitsagentur.de/jobsuche/jobdetail/C-3',
    ]);
});

test('short texts lose control and format characters and get normalized whitespace', function () {
    $job = (new JobDataMapper)->jobDetails([
        'stellenangebotsTitel' => "  Pflege\u{202E}kraft\x00 \u{200B}(m/w/d)\n\t  Berlin\u{FEFF}  ",
        'firma' => "Muster\u{202D}\u{2066} GmbH",
        'hauptberuf' => "\x00\u{200B}\u{202E}",
    ], 'A-1');

    expect($job['title'])->toBe('Pflegekraft (m/w/d) Berlin')
        ->and($job['employer'])->toBe('Muster GmbH')
        ->and($job['profession'])->toBeNull();
});

test('the description keeps line breaks and tabs but loses other control and format characters', function () {
    $job = (new JobDataMapper)->jobDetails([
        'stellenangebotsBeschreibung' => "Zeile eins\r\nZeile\u{202E} zwei\x00\n\tEingerückt\u{200B}\x07\rEnde",
    ], 'A-1');

    expect($job['description'])->toBe("Zeile eins\nZeile zwei\n\tEingerückt\nEnde");
});

test('texts with invalid utf-8 are dropped', function () {
    $mapper = new JobDataMapper;

    expect($mapper->jobDetails(['stellenangebotsTitel' => "Titel\xFF"], 'A-1')['title'])->toBeNull()
        ->and($mapper->jobDetails(['stellenangebotsBeschreibung' => "Text\xFF"], 'A-1')['description'])->toBeNull();
});

test('short texts are truncated at 300 and the description at 50000 characters', function () {
    $job = (new JobDataMapper)->jobDetails([
        'stellenangebotsTitel' => str_repeat('ä', 301),
        'firma' => str_repeat('b', 300),
        'stellenangebotsBeschreibung' => str_repeat('ü', 50001),
    ], 'A-1');

    expect(mb_strlen($job['title']))->toBe(300)
        ->and(mb_strlen($job['employer']))->toBe(300)
        ->and(mb_strlen($job['description']))->toBe(50000);
});

test('the distance is only accepted as a non-negative integer', function (mixed $distance, ?int $expected) {
    $result = (new JobDataMapper)->searchResults(['ergebnisliste' => [['referenznummer' => 'A-1', 'entfernung' => $distance]]]);

    expect($result['jobs'][0]['distance'])->toBe($expected);
})->with([
    'int' => [7, 7],
    'zero' => [0, 0],
    'float' => [4.5, null],
    'numeric string' => ['5', null],
    'negative' => [-1, null],
    'null' => [null, null],
]);
