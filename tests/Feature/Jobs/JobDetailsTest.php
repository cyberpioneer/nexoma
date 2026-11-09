<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

const JOBS_API_DETAILS_URL = 'https://jobsuche.test/pc/v4/jobdetails/*';
const JOBS_TEST_REFNR = '10001-1234567890-S';

beforeEach(function () {
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');
    config()->set('services.jobsuche.api_key', 'secret-test-key-123');
    config()->set('inertia.ssr.enabled', false);

    Http::preventStrayRequests();
    Cache::flush();
});

/**
 * Eine Detail-Antwort in der Struktur der echten API (inkl. nicht freigegebener Felder).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiDetailsResponse(array $overrides = []): array
{
    return [
        'stellenangebotsart' => 'ARBEIT',
        'stellenangebotsTitel' => 'Pflegefachkraft (m/w/d)',
        'stellenangebotsBeschreibung' => "Zeile eins\n<script>alert(1)</script>\nZeile drei",
        'firma' => 'Muster Pflege GmbH',
        'hauptberuf' => 'Altenpflege',
        'referenznummer' => JOBS_TEST_REFNR,
        'vertragsdauer' => 'UNBEFRISTET',
        'arbeitszeitVollzeit' => true,
        'arbeitszeitTeilzeitFlexibel' => true,
        'arbeitszeitSchichtNachtWochenende' => false,
        'homeofficemoeglich' => true,
        'eintrittszeitraum' => ['von' => '2026-12-01'],
        'veroeffentlichungszeitraum' => ['von' => '2026-11-02'],
        'stellenlokationen' => [['adresse' => ['plz' => '10999', 'ort' => 'Berlin', 'strasse' => 'Geheimweg 1']]],
        'externeURL' => 'https://bewerbung.example/abc',
        'allianzpartnerUrl' => 'javascript:alert(2)',
        'internesFeld' => 'darf-nicht-im-client-landen',
        ...$overrides,
    ];
}

test('guests are sent to the login and unverified users to the verification notice', function () {
    $this->get(route('jobs.show', JOBS_TEST_REFNR))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('jobs.show', JOBS_TEST_REFNR))
        ->assertRedirect(route('verification.notice'));

    Http::assertNothingSent();
});

test('verified users see the whitelisted details', function () {
    Http::fake([JOBS_API_DETAILS_URL => Http::response(apiDetailsResponse())]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.show', JOBS_TEST_REFNR))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Jobs/Show')
            ->where('job', [
                'refnr' => JOBS_TEST_REFNR,
                'title' => 'Pflegefachkraft (m/w/d)',
                'profession' => 'Altenpflege',
                'employer' => 'Muster Pflege GmbH',
                'locations' => ['10999 Berlin'],
                'description' => "Zeile eins\n<script>alert(1)</script>\nZeile drei",
                'publishedAt' => '2026-11-02',
                'startDate' => '2026-12-01',
                'offerType' => 'ARBEIT',
                'contractDuration' => 'UNBEFRISTET',
                'workingTimes' => ['fulltime', 'parttime'],
                'homeOffice' => true,
                'officialUrl' => 'https://www.arbeitsagentur.de/jobsuche/jobdetail/'.JOBS_TEST_REFNR,
                'applicationUrl' => 'https://bewerbung.example/abc',
                'applicationHost' => 'bewerbung.example',
            ]));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://jobsuche.test/pc/v4/jobdetails/'.base64_encode(JOBS_TEST_REFNR));
});

test('the response contains neither unlisted api fields nor the api key', function () {
    Http::fake([JOBS_API_DETAILS_URL => Http::response(apiDetailsResponse())]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.show', JOBS_TEST_REFNR))
        ->assertDontSee('darf-nicht-im-client-landen')
        ->assertDontSee('Geheimweg')
        ->assertDontSee('javascript:alert')
        ->assertDontSee('secret-test-key-123');
});

test('external application urls other than http and https are dropped', function (mixed $url) {
    Http::fake([JOBS_API_DETAILS_URL => Http::response(apiDetailsResponse(['externeURL' => $url]))]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.show', JOBS_TEST_REFNR))
        ->assertInertia(fn (Assert $page) => $page
            ->where('job.applicationUrl', null)
            ->where('job.applicationHost', null)
            ->where('job.officialUrl', 'https://www.arbeitsagentur.de/jobsuche/jobdetail/'.JOBS_TEST_REFNR));
})->with([
    'javascript' => 'javascript:alert(1)',
    'javascript mixed case' => 'JaVaScRiPt:alert(1)',
    'data' => 'data:text/html,<script>alert(1)</script>',
    'ftp' => 'ftp://example.test/x',
    'http' => 'http://example.test/x',
    'userinfo' => 'https://user:pass@example.test/x',
    'unicode host' => 'https://bücher.example/x',
    'no scheme' => '//example.test/x',
    'whitespace' => 'https://example.test/a b',
    'not a string' => [['https://example.test']],
]);

test('an api failure shows the unavailable state instead of a server error', function () {
    Http::fake([JOBS_API_DETAILS_URL => Http::response('Fehler', 500)]);

    $this->actingAs(User::factory()->create())
        ->get(route('jobs.show', JOBS_TEST_REFNR))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Jobs/Show')->where('job', null));
});

test('an invalid reference number is rejected with 404 without an api call', function (string $refnr) {
    $this->actingAs(User::factory()->create())
        ->get('/jobs/'.$refnr)
        ->assertNotFound();

    Http::assertNothingSent();
})->with([
    'umlaut' => 'inv%C3%A4lid',
    'too long' => str_repeat('a', 101),
    'encoded slash' => 'a%2Fb',
    'dollar' => 'a%24b',
    'space' => 'a%20b',
]);

test('details are cached so the second view makes no further api call', function () {
    Http::fake([JOBS_API_DETAILS_URL => Http::response(apiDetailsResponse())]);
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('jobs.show', JOBS_TEST_REFNR))->assertOk();
    $this->actingAs($user)->get(route('jobs.show', JOBS_TEST_REFNR))->assertOk();

    Http::assertSentCount(1);
});

test('the details are rate limited', function () {
    Http::fake([JOBS_API_DETAILS_URL => Http::response(apiDetailsResponse())]);
    $user = User::factory()->create();

    for ($i = 0; $i < AppServiceProvider::JOBDETAILS_ATTEMPTS_PER_MINUTE; $i++) {
        $this->actingAs($user)->get(route('jobs.show', JOBS_TEST_REFNR))->assertOk();
    }

    $this->actingAs($user)->get(route('jobs.show', JOBS_TEST_REFNR))->assertTooManyRequests();
});

test('the details route sits behind auth, verified and its rate limiter', function () {
    $middleware = Route::getRoutes()->getByName('jobs.show')->gatherMiddleware();

    expect($middleware)->toContain('auth', 'verified', 'throttle:jobdetails');
});

test('the details page renders without missing translations in every locale', function (string $locale) {
    Http::fake([JOBS_API_DETAILS_URL => Http::response(apiDetailsResponse())]);

    $this->actingAs(User::factory()->create())
        ->withSession(['locale' => $locale])
        ->get(route('jobs.show', JOBS_TEST_REFNR))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locale', $locale)
            ->where('translations', fn ($translations): bool => isset($translations['jobs.detail.title'])
                && collect($translations)->every(fn ($text, $key): bool => $text !== $key && $text !== '')));
})->with(['de', 'en', 'ru', 'uk', 'es', 'it']);
