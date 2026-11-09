<?php

namespace App\Services\Jobsuche;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use InvalidArgumentException;
use Throwable;

/**
 * Client fuer die Jobsuche-API der Bundesagentur fuer Arbeit.
 *
 * Alle Antworten sind Fremddaten und gelten als unvertrauenswuerdig (Ausgabe escapen, nicht blind weiterreichen).
 * search() normalisiert die Trefferliste auf den Schluessel `ergebnisliste`.
 */
class JobsucheApiClient
{
    /**
     * Zulaessige Werte fuer den Parameter angebotsart.
     *
     * @var list<int>
     */
    public const ALLOWED_OFFER_TYPES = [1, 2, 4, 34];

    /**
     * Laut OpenAPI dokumentierte, zusaetzlich erlaubte Suchparameter und ihr Typ.
     *
     * @var array<string, string>
     */
    private const EXTRA_PARAMETER_TYPES = [
        'berufsfeld' => 'string',
        'arbeitgeber' => 'string',
        'zeitarbeit' => 'bool',
        'behinderung' => 'bool',
        'corona' => 'bool',
        'befristung' => 'befristung',
        'arbeitszeit' => 'arbeitszeit',
    ];

    /**
     * Felder, von denen mindestens eines in einer gueltigen Detailantwort vorhanden sein muss.
     *
     * @var list<string>
     */
    private const DETAILS_EXPECTED_FIELDS = ['referenznummer', 'stellenangebotsTitel', 'titel'];

    private const MAX_TEXT_LENGTH = 255;

    /** Eigene Festlegung: Die OpenAPI nennt fuer umkreis kein Maximum. */
    private const MAX_RADIUS_KILOMETERS = 200;

    private const DETAILS_CACHE_SECONDS = 6 * 60 * 60;

    private const RATE_LIMIT_DELAY_MILLISECONDS = 500;

    /** Schluessel des globalen Limiters fuer ausgehende BA-Aufrufe (alle Benutzer und Clients zusammen). */
    public const GLOBAL_LIMITER_KEY = 'jobsuche-global';

    private const DEFAULT_GLOBAL_LIMIT_PER_MINUTE = 600;

    /**
     * Sucht Stellenangebote. Gibt bei Fehlern null zurueck, bei null Treffern ein Array mit leerer `ergebnisliste`.
     *
     * @param  array<string, scalar|null>  $extra  Weitere dokumentierte Parameter: berufsfeld, arbeitgeber (string), zeitarbeit, behinderung, corona (bool), befristung (1|2), arbeitszeit (vz;tz;snw;ho;mj). Schluessel benannter Parameter (z. B. was, page) sind nicht erlaubt und fuehren zu einer InvalidArgumentException.
     * @return array<string, mixed>|null
     *
     * @throws InvalidArgumentException Bei unzulaessigen Parametern.
     */
    public function search(
        string $was,
        int $page = 1,
        int $size = 100,
        int $veroeffentlichtseit = 100,
        ?string $wo = null,
        ?int $umkreis = null,
        ?int $angebotsart = null,
        array $extra = [],
    ): ?array {
        if ($angebotsart !== null && ! in_array($angebotsart, self::ALLOWED_OFFER_TYPES, true)) {
            throw new InvalidArgumentException('Unzulaessige Angebotsart. Erlaubt sind: '.implode(', ', self::ALLOWED_OFFER_TYPES).'.');
        }

        $this->assertValidSearchArguments($was, $page, $size, $veroeffentlichtseit, $wo, $umkreis);
        $this->assertValidExtraParameters($extra);

        $parameters = array_merge($extra, [
            'was' => $was,
            'page' => $page,
            'size' => $size,
            'veroeffentlichtseit' => $veroeffentlichtseit,
            'wo' => $wo,
            'umkreis' => $umkreis,
            'angebotsart' => $angebotsart,
        ]);

        $parameters = collect($parameters)
            ->reject(fn (mixed $value): bool => $value === null)
            ->map(fn (mixed $value): mixed => is_bool($value) ? ($value ? 'true' : 'false') : $value)
            ->all();

        $data = $this->get('/pc/v6/jobs', $parameters, 'search');

        if ($data === null) {
            return null;
        }

        $list = $data['ergebnisliste'] ?? $data['stellenangebote'] ?? null;

        // Bei null Treffern liefert die API ein Objekt ohne Ergebnisliste, aber mit maxErgebnisse.
        if ($list === null && array_key_exists('maxErgebnisse', $data)) {
            $list = [];
        }

        if (! is_array($list)) {
            Log::warning('Jobsuche-API: Antwort der Suche hat keine gueltige Struktur.');

            return null;
        }

        $data['ergebnisliste'] = array_values($list);

        return $data;
    }

    /**
     * Ruft die Details eines Stellenangebots ab und cached erfolgreiche Antworten sechs Stunden.
     *
     * @return array<string, mixed>|null
     */
    public function details(string $refnr): ?array
    {
        if (preg_match('/^[A-Za-z0-9._-]{1,100}\z/D', $refnr) !== 1) {
            Log::warning('Jobsuche-API: Ungueltige Referenznummer fuer Details abgewiesen.');

            return null;
        }

        $cacheKey = 'jobsuche.details.'.$refnr;

        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $data = $this->get('/pc/v4/jobdetails/'.base64_encode($refnr), [], 'details');

        if ($data === null) {
            return null;
        }

        if (array_intersect_key($data, array_flip(self::DETAILS_EXPECTED_FIELDS)) === []) {
            Log::warning('Jobsuche-API: Detailantwort enthaelt kein erwartetes Feld.');

            return null;
        }

        Cache::put($cacheKey, $data, self::DETAILS_CACHE_SECONDS);

        return $data;
    }

    /**
     * Fuehrt einen GET-Request aus und liefert das dekodierte JSON-Objekt oder null.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query, string $operation): ?array
    {
        if (! $this->isBaseUrlAllowed()) {
            Log::error('Jobsuche-API: Basis-URL ist ungueltig oder nicht https; Anfrage abgebrochen.', [
                'operation' => $operation,
            ]);

            return null;
        }

        try {
            if (! $this->reserveGlobalQuota()) {
                Log::warning('Jobsuche-API: Globales Kontingent ausgeschoepft; Anfrage nicht gesendet.', [
                    'operation' => $operation,
                ]);

                return null;
            }

            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $exception) {
            Log::error('Jobsuche-API: Verbindungsfehler.', [
                'operation' => $operation,
                'exception' => $exception::class,
            ]);

            return null;
        } catch (Throwable $exception) {
            Log::error('Jobsuche-API: Unerwarteter Fehler bei der Anfrage.', [
                'operation' => $operation,
                'exception' => $exception::class,
            ]);

            return null;
        }

        return $this->decode($response, $operation);
    }

    /**
     * Zaehlt einen ausgehenden Aufruf gegen das globale Minutenkontingent; false, wenn es ausgeschoepft ist.
     * Ein Limit von 0 schaltet das globale Kontingent ab (Entscheidung des Nutzers); negative Werte fallen auf den Standard zurueck.
     * Fehler des Cache-Speichers werden vom Aufrufer wie ein Anfragefehler behandelt (null).
     */
    private function reserveGlobalQuota(): bool
    {
        $limit = (int) config('services.jobsuche.global_limit_per_minute', self::DEFAULT_GLOBAL_LIMIT_PER_MINUTE);

        if ($limit === 0) {
            return true;
        }

        if ($limit < 0) {
            $limit = self::DEFAULT_GLOBAL_LIMIT_PER_MINUTE;
        }

        return RateLimiter::attempt(self::GLOBAL_LIMITER_KEY, $limit, fn (): bool => true, 60);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Response $response, string $operation): ?array
    {
        if (! $response->successful()) {
            Log::warning('Jobsuche-API: Anfrage nicht erfolgreich.', [
                'operation' => $operation,
                'status' => $response->status(),
            ]);

            return null;
        }

        $data = $response->json();

        if (! is_array($data)) {
            Log::warning('Jobsuche-API: Antwort ist kein gueltiges JSON-Objekt.', [
                'operation' => $operation,
            ]);

            return null;
        }

        return $data;
    }

    private function isBaseUrlAllowed(): bool
    {
        $baseUrl = config('services.jobsuche.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));

        return $scheme === 'https'
            || ($scheme === 'http' && app()->environment(['local', 'testing']));
    }

    private function assertValidSearchArguments(
        string $was,
        int $page,
        int $size,
        int $veroeffentlichtseit,
        ?string $wo,
        ?int $umkreis,
    ): void {
        $valid = $page >= 1
            && $size >= 1 && $size <= 100
            && $veroeffentlichtseit >= 0 && $veroeffentlichtseit <= 100
            && ($umkreis === null || ($umkreis >= 0 && $umkreis <= self::MAX_RADIUS_KILOMETERS))
            && mb_strlen($was) <= self::MAX_TEXT_LENGTH
            && ($wo === null || mb_strlen($wo) <= self::MAX_TEXT_LENGTH);

        if (! $valid) {
            throw new InvalidArgumentException('Unzulaessige Suchparameter (Wertebereich oder Laenge).');
        }
    }

    /**
     * @param  array<array-key, mixed>  $extra
     */
    private function assertValidExtraParameters(array $extra): void
    {
        foreach ($extra as $key => $value) {
            $type = self::EXTRA_PARAMETER_TYPES[$key] ?? null;

            if ($type === null) {
                throw new InvalidArgumentException('Unbekannter Suchparameter: '.$key);
            }

            if ($value === null) {
                continue;
            }

            $valid = match ($type) {
                'string' => is_string($value) && mb_strlen($value) <= self::MAX_TEXT_LENGTH,
                'bool' => is_bool($value),
                'befristung' => in_array($value, [1, 2], true),
                'arbeitszeit' => is_string($value)
                    && preg_match('/^(vz|tz|snw|ho|mj)(;(vz|tz|snw|ho|mj))*\z/D', $value) === 1,
            };

            if (! $valid) {
                throw new InvalidArgumentException('Unzulaessiger Wert fuer Suchparameter: '.$key);
            }
        }
    }

    private function request(): PendingRequest
    {
        /** @var string $baseUrl */
        $baseUrl = config('services.jobsuche.base_url');
        $apiKey = config('services.jobsuche.api_key');

        return Http::baseUrl($baseUrl)
            ->withHeaders(['X-API-Key' => is_string($apiKey) ? $apiKey : ''])
            ->acceptJson()
            ->connectTimeout((int) config('services.jobsuche.connect_timeout', 5))
            ->timeout((int) config('services.jobsuche.timeout', 10))
            ->retry(
                2,
                self::RATE_LIMIT_DELAY_MILLISECONDS,
                fn (Throwable $exception): bool => $exception instanceof RequestException
                    && $exception->response->status() === 429,
                throw: false,
            );
    }
}
