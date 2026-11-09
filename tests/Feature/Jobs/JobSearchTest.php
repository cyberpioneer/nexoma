<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

const JOBS_API_SEARCH_URL = 'https://jobsuche.test/pc/v6/jobs*';

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('services.jobsuche.api_key', 'secret-test-key-123');
    config()->set('inertia.ssr.enabled', false);

    Http::preventStrayRequests();
    Cache::flush();
});

/**
 * Eine Treffer-Antwort in der Struktur der echten API (inkl. Feldern, die nicht an den Client gehen duerfen).
 *
 * @return array<string, mixed>
 */
function apiSearchResponse(int $total = 1): array
{
    return [
        'ergebnisliste' => [[
            'stellenangebotsart' => 'AUSBILDUNG',
            'stellenangebotsTitel' => 'Pflegefachkraft (m/w/d)',
            'hauptberuf' => 'Altenpflege',
            'firma' => 'Muster Pflege GmbH',
            'referenznummer' => '10001-1234567890-S',
            'entfernung' => 4,
            'veroeffentlichungszeitraum' => ['von' => '2026-11-02'],
            'datumErsteVeroeffentlichung' => '2026-11-01',
            'eintrittszeitraum' => ['von' => '2026-12-01'],
            'externeURL' => 'https://bewerbung.example/abc',
            'stellenlokationen' => [[
                'adresse' => ['plz' => '10999', 'ort' => 'Berlin', 'region' => 'BERLIN', 'strasse' => 'Geheimweg 1'],
                'breite' => 52.5,
                'laenge' => 13.4,
            ]],
            'internesFeld' => 'darf-nicht-im-client-landen',
            'alleBerufe' => ['Altenpflege'],
        ]],
        'maxErgebnisse' => $total,
        'page' => 1,
        'size' => 25,
        'facetten' => [['berufsfeld' => ['counts' => []]]],
    ];
}

/**
 * Query-Parameter einer URL.
 *
 * @return array<string, string>
 */
function queryOf(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return $query;
}

test('guests are sent to the login and unverified users to the verification notice', function () {
    $this->get(route('jobs.index', ['was' => 'Pflege']))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('jobs.index', ['was' => 'Pflege']))
        ->assertRedirect(route('verification.notice'));

    Http::assertNothingSent();
});

test('the first visit shows only the form and does not call the api', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Index')
            ->where('results', null)
            ->where('filters.was', null));

    Http::assertNothingSent();
});

test('results are deferred and only whitelisted fields reach the client', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response(apiSearchResponse(1))]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', ['was' => 'Pflege']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Index')
            ->missing('results')
            ->where('filters.was', 'Pflege')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('results.status', 'ok')
                ->where('results.total', 1)
                ->where('results.page', 1)
                ->where('results.pages', 1)
                ->where('results.previousUrl', null)
                ->where('results.nextUrl', null)
                ->has('results.jobs', 1)
                ->where('results.jobs.0', [
                    'refnr' => '10001-1234567890-S',
                    'title' => 'Pflegefachkraft (m/w/d)',
                    'profession' => 'Altenpflege',
                    'employer' => 'Muster Pflege GmbH',
                    'location' => '10999 Berlin',
                    'distance' => 4,
                    'publishedAt' => '2026-11-02',
                    'startDate' => '2026-12-01',
                    'offerType' => 'AUSBILDUNG',
                ])));
});

test('the loaded results contain neither unlisted api fields nor the api key', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response(apiSearchResponse(1))]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', ['was' => 'Pflege']))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(function (Assert $reload): void {
            $json = json_encode($reload->toArray());

            expect($json)->toContain('Pflegefachkraft')
                ->not->toContain('darf-nicht-im-client-landen')
                ->not->toContain('Geheimweg')
                ->not->toContain('facetten')
                ->not->toContain('secret-test-key-123');
        }));
});

test('all filters are passed to the api and the page size defaults to 25', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response(apiSearchResponse(1))]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', [
            'was' => 'Pflege', 'wo' => 'Berlin', 'umkreis' => 50, 'angebotsart' => 4,
            'berufsfeld' => 'Gesundheit', 'veroeffentlichtseit' => 14, 'page' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->where('results.status', 'ok')));

    Http::assertSent(function (Request $request): bool {
        $query = queryOf($request->url());

        return $query['was'] === 'Pflege'
            && $query['wo'] === 'Berlin'
            && $query['umkreis'] === '50'
            && $query['angebotsart'] === '4'
            && $query['berufsfeld'] === 'Gesundheit'
            && $query['veroeffentlichtseit'] === '14'
            && $query['page'] === '2'
            && $query['size'] === '25';
    });
});

test('empty optional form fields are not sent to the api', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response(apiSearchResponse(1))]);

    $this->actingAs(User::factory()->create())
        ->get('/jobs?was=Pflege&wo=&berufsfeld=&angebotsart=&umkreis=25&veroeffentlichtseit=100')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload->where('results.status', 'ok')));

    Http::assertSent(function (Request $request): bool {
        $query = queryOf($request->url());

        return ! array_key_exists('wo', $query) && ! array_key_exists('berufsfeld', $query) && ! array_key_exists('angebotsart', $query);
    });
});

test('invalid input shows field errors and does not call the api', function (array $query, string $errorField) {
    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', $query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Index')
            ->missing('results.status')
            ->where('results', null)
            ->has("fieldErrors.{$errorField}"));

    Http::assertNothingSent();
})->with([
    'empty search term' => [['was' => ''], 'was'],
    'too long term' => [['was' => str_repeat('a', 256)], 'was'],
    'radius out of range' => [['was' => 'x', 'umkreis' => 201], 'umkreis'],
    'unknown offer type' => [['was' => 'x', 'angebotsart' => 3], 'angebotsart'],
    'page zero' => [['was' => 'x', 'page' => 0], 'page'],
    'size too large' => [['was' => 'x', 'size' => 101], 'size'],
    'array instead of text' => [['was' => ['a']], 'was'],
]);

test('an api failure shows the unavailable state instead of a server error', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response('Fehler', 500)]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', ['was' => 'Pflege']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('results', ['status' => 'unavailable'])));
});

test('a search without hits shows the empty state', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response(['maxErgebnisse' => 0, 'page' => 1, 'size' => 25])]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', ['was' => 'Zzzzzz']))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('results.status', 'empty')
            ->where('results.total', 0)
            ->where('results.jobs', [])));
});

test('pagination links keep all filters', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response(apiSearchResponse(60))]);

    $filters = ['angebotsart' => '4', 'size' => '25', 'umkreis' => '50', 'veroeffentlichtseit' => '14', 'was' => 'Pflege', 'wo' => 'Berlin'];

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', ['was' => 'Pflege', 'wo' => 'Berlin', 'umkreis' => 50, 'angebotsart' => 4, 'veroeffentlichtseit' => 14, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('results.page', 2)
            ->where('results.pages', 3)
            ->where('results.previousUrl', fn (string $url): bool => str_starts_with($url, '/jobs?') && queryOf($url) == [...$filters, 'page' => '1'])
            ->where('results.nextUrl', fn (string $url): bool => str_starts_with($url, '/jobs?') && queryOf($url) == [...$filters, 'page' => '3'])));
});

test('the first and last page have no previous respectively next link', function () {
    Http::fake([JOBS_API_SEARCH_URL => Http::response(apiSearchResponse(60))]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('jobs.index', ['was' => 'x']))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('results.previousUrl', null)->whereNot('results.nextUrl', null)));

    $this->actingAs($user)->get(route('jobs.index', ['was' => 'x', 'page' => 3]))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->whereNot('results.previousUrl', null)->where('results.nextUrl', null)));
});

test('a page beyond the last page shows the empty state and a link back', function () {
    // Dokumentiert das Verhalten: Liefert die API fuer page > pages keine Treffer, erscheint der Leerzustand;
    // der Zurueck-Link bleibt erhalten, damit der Benutzer nicht in einer Sackgasse landet.
    Http::fake([JOBS_API_SEARCH_URL => Http::response(['maxErgebnisse' => 30, 'page' => 9, 'size' => 25])]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.index', ['was' => 'Pflege', 'page' => 9]))
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('results.status', 'empty')
            ->where('results.page', 9)
            ->where('results.pages', 2)
            ->where('results.nextUrl', null)
            ->where('results.previousUrl', fn (string $url): bool => queryOf($url)['page'] === '8')));
});

test('the search is rate limited', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < AppServiceProvider::JOBSUCHE_ATTEMPTS_PER_MINUTE; $i++) {
        $this->actingAs($user)->get(route('jobs.index'))->assertOk();
    }

    $this->actingAs($user)->get(route('jobs.index'))->assertTooManyRequests();
});

test('the search routes sit behind auth, verified and their rate limiter', function () {
    $middleware = Route::getRoutes()->getByName('jobs.index')->gatherMiddleware();

    expect($middleware)->toContain('auth', 'verified', 'throttle:jobsuche');
});

test('the search page renders without missing translations in every locale', function (string $locale) {
    $this->actingAs(User::factory()->create())
        ->withSession(['locale' => $locale])
        ->get(route('jobs.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', $locale)
            ->where('translations', function ($translations): bool {
                foreach ($translations as $key => $text) {
                    if ($text === $key || $text === '') {
                        return false;
                    }
                }

                return isset($translations['jobs.title'], $translations['jobs.page_of']);
            }));
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);

test('every literal translation key used in the frontend exists', function () {
    $keys = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        $path = $file->getPathname();

        if (! str_ends_with($path, '.vue') || preg_match('#/js/(actions|routes|wayfinder)/#', $path) === 1) {
            continue;
        }

        preg_match_all("/\\bt\\(\\s*'([A-Za-z0-9_.]+)'/", (string) file_get_contents($path), $matches);
        $keys = [...$keys, ...$matches[1]];
    }

    $dynamicKeys = [
        'jobs.offer_type.ARBEIT', 'jobs.offer_type.AUSBILDUNG', 'jobs.offer_type.PRAKTIKUM_TRAINEE', 'jobs.offer_type.SELBSTAENDIGKEIT',
        'jobs.contract.BEFRISTET', 'jobs.contract.UNBEFRISTET',
        'jobs.working_time.fulltime', 'jobs.working_time.parttime', 'jobs.working_time.shift',
    ];

    expect($keys)->not->toBeEmpty();

    foreach (['de', 'en', 'ru', 'uk', 'es', 'it'] as $locale) {
        $messages = require lang_path("{$locale}/ui.php");

        foreach ([...$keys, ...$dynamicKeys] as $key) {
            expect(array_key_exists($key, $messages))->toBeTrue("{$locale}: {$key}");
        }
    }
});
