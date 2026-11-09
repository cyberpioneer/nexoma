<?php

use App\Http\Requests\JobDetailsRequest;
use App\Http\Requests\SearchJobsRequest;
use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

function searchErrors(array $input): array
{
    return Validator::make($input, (new SearchJobsRequest)->rules())->errors()->keys();
}

test('minimal valid search passes', function () {
    expect(searchErrors(['was' => 'Pflege']))->toBe([]);
});

test('was is required, a string and at most 255 characters', function (mixed $was, bool $valid) {
    expect(searchErrors(['was' => $was]) === [])->toBe($valid);
})->with([
    'missing' => [null, false],
    'empty' => ['', false],
    'blank' => ['   ', false],
    'array' => [['x'], false],
    '1 char' => ['a', true],
    '255 chars' => [str_repeat('a', 255), true],
    '256 chars' => [str_repeat('a', 256), false],
    '255 multibyte chars' => [str_repeat('ü', 255), true],
]);

test('numeric and enum fields respect their bounds', function (string $field, mixed $value, bool $valid) {
    expect(searchErrors(['was' => 'x', $field => $value]) === [])->toBe($valid);
})->with([
    ['umkreis', 0, true], ['umkreis', 200, true], ['umkreis', 201, false], ['umkreis', -1, false], ['umkreis', 'abc', false], ['umkreis', 1.5, false],
    ['angebotsart', 1, true], ['angebotsart', 2, true], ['angebotsart', 4, true], ['angebotsart', 34, true], ['angebotsart', 3, false], ['angebotsart', 0, false],
    ['page', 1, true], ['page', 0, false], ['page', -3, false], ['page', 'x', false],
    ['size', 1, true], ['size', 100, true], ['size', 0, false], ['size', 101, false],
    ['veroeffentlichtseit', 0, true], ['veroeffentlichtseit', 100, true], ['veroeffentlichtseit', 101, false], ['veroeffentlichtseit', -1, false],
    ['wo', str_repeat('a', 255), true], ['wo', str_repeat('a', 256), false], ['wo', ['x'], false], ['wo', null, true],
]);

test('extra parameters are validated', function (string $field, mixed $value, bool $valid) {
    expect(searchErrors(['was' => 'x', $field => $value]) === [])->toBe($valid);
})->with([
    ['berufsfeld', str_repeat('a', 255), true], ['berufsfeld', str_repeat('a', 256), false],
    ['arbeitgeber', 'ACME', true], ['arbeitgeber', str_repeat('a', 256), false],
    ['zeitarbeit', true, true], ['zeitarbeit', 'false', false], ['zeitarbeit', 'maybe', false],
    ['befristung', 1, true], ['befristung', 2, true], ['befristung', 3, false],
    ['arbeitszeit', 'vz', true], ['arbeitszeit', 'vz;tz;snw;ho;mj', true], ['arbeitszeit', 'vz;', false],
    ['arbeitszeit', 'xx', false], ['arbeitszeit', 'vz;tz ', false], ['arbeitszeit', 'vz; tz', false], ['arbeitszeit', "vz;tz\u{200E}", false], ['arbeitszeit', 'ｖｚ', false], ['arbeitszeit', "vz;t\u{FF5A}", false], ['arbeitszeit', 'vz,tz', false], ['arbeitszeit', "vz\n", false],
]);

test('every validated search matches what the client accepts', function () {
    Http::fake(['*' => Http::response(['ergebnisliste' => [], 'maxErgebnisse' => 0])]);
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');

    $validated = Validator::make([
        'was' => 'Pflege', 'wo' => 'Berlin', 'umkreis' => '25', 'angebotsart' => '34', 'page' => '2', 'size' => '50',
        'veroeffentlichtseit' => '0', 'berufsfeld' => 'Pflege', 'arbeitgeber' => 'ACME', 'zeitarbeit' => '1',
        'befristung' => '2', 'arbeitszeit' => 'vz;tz',
    ], (new SearchJobsRequest)->rules())->validate();

    $arguments = SearchJobsRequest::searchArguments($validated);

    expect($arguments)->toBe([
        'was' => 'Pflege', 'page' => 2, 'size' => 50, 'veroeffentlichtseit' => 0, 'umkreis' => 25, 'angebotsart' => 34,
        'wo' => 'Berlin',
        'extra' => ['berufsfeld' => 'Pflege', 'arbeitgeber' => 'ACME', 'zeitarbeit' => true, 'befristung' => 2, 'arbeitszeit' => 'vz;tz'],
    ]);

    expect(app(JobsucheApiClient::class)->search(...$arguments))->toBe(['ergebnisliste' => [], 'maxErgebnisse' => 0]);

    Http::assertSent(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query['zeitarbeit'] === 'true' && $query['page'] === '2' && $query['arbeitszeit'] === 'vz;tz';
    });
});

test('searchArguments omits unset values so client defaults apply', function () {
    expect(SearchJobsRequest::searchArguments(['was' => 'x', 'page' => null, 'wo' => null, 'zeitarbeit' => null]))
        ->toBe(['was' => 'x']);

    expect(SearchJobsRequest::searchArguments(['was' => 'x', 'zeitarbeit' => 'false']))
        ->toBe(['was' => 'x', 'extra' => ['zeitarbeit' => false]]);
});

test('unknown input keys are not passed on', function () {
    $validated = Validator::make(['was' => 'x', 'evil' => '1'], (new SearchJobsRequest)->rules())->validate();

    expect(SearchJobsRequest::searchArguments($validated))->toBe(['was' => 'x']);
});

test('the form request validates web input and returns 422 for json', function () {
    Route::get('/_test/search', fn (SearchJobsRequest $request) => response()->json(SearchJobsRequest::searchArguments($request->validated())));

    $this->getJson('/_test/search?was=Pflege&size=10')->assertOk()->assertExactJson(['was' => 'Pflege', 'size' => 10]);
    $this->getJson('/_test/search?was=Pflege&size=101')->assertUnprocessable()->assertJsonValidationErrors('size');
    $this->getJson('/_test/search')->assertUnprocessable()->assertJsonValidationErrors('was');
});

test('refnr follows the same rule as the client', function (mixed $refnr, bool $valid) {
    $errors = Validator::make(['refnr' => $refnr], (new JobDetailsRequest)->rules())->errors()->keys();

    expect($errors === [])->toBe($valid);
})->with([
    'typical' => ['10001-1234567-S', true],
    'dots and underscores' => ['a.b_c-D', true],
    '100 chars' => [str_repeat('a', 100), true],
    '101 chars' => [str_repeat('a', 101), false],
    'empty' => ['', false],
    'null' => [null, false],
    'slash' => ['a/b', false],
    'space' => ['a b', false],
    'trailing newline' => ["abc\n", false],
    'array' => [['abc'], false],
]);

test('the details request validates the refnr route parameter', function () {
    Route::get('/_test/details/{refnr}', fn (JobDetailsRequest $request) => response()->json(['refnr' => $request->validated('refnr')]));

    $this->getJson('/_test/details/10001-1-S')->assertOk()->assertExactJson(['refnr' => '10001-1-S']);
    $this->getJson('/_test/details/'.str_repeat('a', 101))->assertUnprocessable()->assertJsonValidationErrors('refnr');
    $this->getJson('/_test/details/a%20b')->assertUnprocessable();
});

test('empty and blank strings are treated like null in every field', function (string $blank) {
    $fields = ['wo', 'umkreis', 'angebotsart', 'page', 'size', 'veroeffentlichtseit', 'berufsfeld', 'arbeitgeber', 'zeitarbeit', 'befristung', 'arbeitszeit'];

    foreach ($fields as $field) {
        expect(SearchJobsRequest::searchArguments(['was' => 'x', $field => $blank]))->toBe(['was' => 'x'], $field);
    }
})->with(['empty' => [''], 'spaces' => ['  '], 'tab and newline' => ["\t\n"]]);

test('empty strings pass validation for optional fields but not for was', function () {
    $input = ['was' => 'x', 'wo' => '', 'umkreis' => '', 'angebotsart' => '', 'page' => '', 'size' => '', 'veroeffentlichtseit' => '',
        'berufsfeld' => '', 'arbeitgeber' => '', 'zeitarbeit' => '', 'befristung' => '', 'arbeitszeit' => ''];

    expect(searchErrors($input))->toBe([])
        ->and(searchErrors(['was' => '']))->toBe(['was'])
        ->and(searchErrors(['was' => '  ']))->toBe(['was']);
});

test('mcp input with empty strings reaches search without exception and without those fields', function () {
    Http::fake(['*' => Http::response(['ergebnisliste' => [], 'maxErgebnisse' => 0])]);
    config()->set('services.jobsuche.base_url', 'https://jobsuche.test');

    $validated = Validator::make([
        'was' => 'Pflege', 'wo' => '', 'umkreis' => '', 'angebotsart' => '', 'page' => '', 'size' => '', 'veroeffentlichtseit' => '',
        'berufsfeld' => '', 'arbeitgeber' => '  ', 'zeitarbeit' => '', 'befristung' => '', 'arbeitszeit' => '',
    ], (new SearchJobsRequest)->rules())->validate();

    expect(app(JobsucheApiClient::class)->search(...SearchJobsRequest::searchArguments($validated)))->not->toBeNull();

    Http::assertSent(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        ksort($query);

        return $query === ['page' => '1', 'size' => '100', 'veroeffentlichtseit' => '100', 'was' => 'Pflege'];
    });
});
