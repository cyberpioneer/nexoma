<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobDetailsRequest;
use App\Http\Requests\SearchJobsRequest;
use App\Services\Jobsuche\JobDataMapper;
use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    /** Standard-Seitengroesse der Weboberflaeche (die API erlaubt bis zu 100). */
    private const PAGE_SIZE = 25;

    /**
     * Formularfelder, die in der URL stehen und im Formular vorbelegt werden.
     *
     * @var list<string>
     */
    private const FILTER_FIELDS = ['was', 'wo', 'umkreis', 'angebotsart', 'berufsfeld', 'veroeffentlichtseit'];

    /**
     * Suchformular und Ergebnisse. Ohne Parameter `was` (erster Besuch) wird nur das Formular gezeigt und die API nicht
     * aufgerufen. Die Ergebnisse sind eine zurueckgestellte Prop (Skeleton im Client); API-Fehler fuehren zu
     * `status: unavailable` statt zu einem Serverfehler.
     */
    public function index(Request $request, JobsucheApiClient $client, JobDataMapper $mapper): Response
    {
        $filters = $this->filters($request);

        if (! $request->query->has('was')) {
            return Inertia::render('Jobs/Index', [
                'filters' => $filters,
                'fieldErrors' => (object) [],
                'results' => null,
            ]);
        }

        $validator = Validator::make($request->query(), (new SearchJobsRequest)->rules());

        if ($validator->fails()) {
            return Inertia::render('Jobs/Index', [
                'filters' => $filters,
                'fieldErrors' => collect($validator->errors()->toArray())->map(fn (array $messages): string => (string) $messages[0])->all() ?: (object) [],
                'results' => null,
            ]);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();
        $validated['size'] ??= self::PAGE_SIZE;

        return Inertia::render('Jobs/Index', [
            'filters' => $filters,
            'fieldErrors' => (object) [],
            'results' => Inertia::defer(fn (): array => $this->searchResults($client, $mapper, $validated)),
        ]);
    }

    /**
     * Details einer Stelle. Der Client liefert bei Fehlern null (nicht gefunden und Dienststoerung sind nicht
     * unterscheidbar); die Seite zeigt dann eine gemeinsame Meldung mit Link zur Suche.
     */
    public function show(JobDetailsRequest $request, JobsucheApiClient $client, JobDataMapper $mapper): Response
    {
        $refnr = (string) $request->validated('refnr');
        $details = $client->details($refnr);

        return Inertia::render('Jobs/Show', [
            'job' => $details === null ? null : $mapper->jobDetails($details, $refnr),
        ]);
    }

    /**
     * Fuehrt die Suche aus und baut die Ergebnis-Prop: Whitelist-Felder, Seitenangaben und Links mit allen Filtern.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function searchResults(JobsucheApiClient $client, JobDataMapper $mapper, array $validated): array
    {
        $response = $client->search(...SearchJobsRequest::searchArguments($validated));

        if ($response === null) {
            return ['status' => 'unavailable'];
        }

        $mapped = $mapper->searchResults($response);
        $size = (int) $validated['size'];
        $page = (int) ($validated['page'] ?? 1);
        $pages = max(1, (int) ceil($mapped['total'] / $size));

        return [
            'status' => $mapped['jobs'] === [] ? 'empty' : 'ok',
            'total' => $mapped['total'],
            'jobs' => $mapped['jobs'],
            'page' => $page,
            'pages' => $pages,
            'previousUrl' => $page > 1 ? $this->pageUrl($validated, $page - 1) : null,
            'nextUrl' => $page < $pages ? $this->pageUrl($validated, $page + 1) : null,
        ];
    }

    /**
     * Relative URL einer Ergebnisseite mit allen validierten Filtern.
     *
     * @param  array<string, mixed>  $validated
     */
    private function pageUrl(array $validated, int $page): string
    {
        $query = array_filter(
            Arr::except($validated, ['page']),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        return route('jobs.index', [...$query, 'page' => $page], false);
    }

    /**
     * Formularwerte aus der URL als Strings (nur bekannte Felder), damit das Formular auch nach Fehlern vorbelegt ist.
     *
     * @return array<string, string|null>
     */
    private function filters(Request $request): array
    {
        $filters = [];

        foreach (self::FILTER_FIELDS as $field) {
            $value = $request->query($field);
            $filters[$field] = is_string($value) && $value !== '' ? mb_substr($value, 0, 255) : null;
        }

        return $filters;
    }
}
