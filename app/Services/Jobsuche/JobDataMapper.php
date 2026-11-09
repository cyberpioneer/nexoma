<?php

namespace App\Services\Jobsuche;

/**
 * Ueberfuehrt die (unvertrauenswuerdigen) Antworten der Jobsuche-API in eine feste Whitelist von Feldern fuer die Oberflaeche.
 *
 * Es gelangen nur Felder mit erwartetem Typ in das Ergebnis, Texte werden gekuerzt und URLs auf http/https beschraenkt.
 * Die Feldnamen entsprechen der tatsaechlichen Antwort der API (v6 Suche, v4 Details); aeltere, in der OpenAPI
 * dokumentierte Namen (titel, arbeitgeber, beruf, arbeitsort) dienen als Rueckfall.
 */
class JobDataMapper
{
    /** Referenznummern wie in {@see JobsucheApiClient::details()}. */
    private const REFNR_PATTERN = '/^[A-Za-z0-9._-]{1,100}\z/D';

    private const MAX_SHORT_TEXT_LENGTH = 300;

    private const MAX_DESCRIPTION_LENGTH = 50000;

    private const MAX_URL_LENGTH = 2048;

    private const MAX_LOCATIONS = 5;

    /** Deep Link zur Stelle auf arbeitsagentur.de. */
    private const OFFICIAL_URL = 'https://www.arbeitsagentur.de/jobsuche/jobdetail/';

    /**
     * Angebotsarten laut API (Schluessel von `stellenangebotsart`); Uebersetzungen im Frontend.
     *
     * @var list<string>
     */
    private const OFFER_TYPES = ['ARBEIT', 'SELBSTAENDIGKEIT', 'AUSBILDUNG', 'PRAKTIKUM_TRAINEE'];

    /** @var list<string> */
    private const CONTRACT_DURATIONS = ['BEFRISTET', 'UNBEFRISTET'];

    /**
     * Mappt die Antwort von {@see JobsucheApiClient::search()} auf die Anzeigefelder.
     *
     * @param  array<string, mixed>  $response
     * @return array{total: int, jobs: list<array{refnr: string, title: string|null, profession: string|null, employer: string|null, location: string|null, distance: int|null, publishedAt: string|null, startDate: string|null, offerType: string|null}>}
     */
    public function searchResults(array $response): array
    {
        $jobs = [];
        $list = is_array($response['ergebnisliste'] ?? null) ? $response['ergebnisliste'] : [];

        foreach ($list as $item) {
            if (! is_array($item)) {
                continue;
            }

            $refnr = $this->refnr($item['referenznummer'] ?? $item['refnr'] ?? null);

            if ($refnr === null) {
                continue;
            }

            $distance = $item['entfernung'] ?? null;

            $jobs[] = [
                'refnr' => $refnr,
                'title' => $this->text($item['stellenangebotsTitel'] ?? $item['titel'] ?? $item['hauptberuf'] ?? $item['beruf'] ?? null),
                'profession' => $this->text($item['hauptberuf'] ?? $item['beruf'] ?? null),
                'employer' => $this->text($item['firma'] ?? $item['arbeitgeber'] ?? null),
                'location' => $this->locations($item)[0] ?? null,
                'distance' => is_int($distance) && $distance >= 0 ? $distance : null,
                'publishedAt' => $this->date(data_get($item, 'veroeffentlichungszeitraum.von') ?? $item['datumErsteVeroeffentlichung'] ?? $item['aktuelleVeroeffentlichungsdatum'] ?? null),
                'startDate' => $this->date(data_get($item, 'eintrittszeitraum.von') ?? $item['eintrittsdatum'] ?? null),
                'offerType' => $this->offerType($item['stellenangebotsart'] ?? null),
            ];
        }

        $total = $response['maxErgebnisse'] ?? count($jobs);

        return [
            'total' => is_numeric($total) ? max(0, (int) $total) : count($jobs),
            'jobs' => $jobs,
        ];
    }

    /**
     * Mappt die Antwort von {@see JobsucheApiClient::details()} auf die Anzeigefelder.
     *
     * @param  array<string, mixed>  $details
     * @return array{refnr: string, title: string|null, profession: string|null, employer: string|null, locations: list<string>, description: string|null, publishedAt: string|null, startDate: string|null, offerType: string|null, contractDuration: string|null, workingTimes: list<string>, homeOffice: bool, officialUrl: string, applicationUrl: string|null, applicationHost: string|null}
     */
    public function jobDetails(array $details, string $requestedRefnr): array
    {
        $refnr = $this->refnr($details['referenznummer'] ?? $details['refnr'] ?? null) ?? $requestedRefnr;

        $contractDuration = $details['vertragsdauer'] ?? null;
        $applicationUrl = $this->safeHttpUrl($details['externeURL'] ?? null);

        return [
            'refnr' => $refnr,
            'title' => $this->text($details['stellenangebotsTitel'] ?? $details['titel'] ?? $details['hauptberuf'] ?? $details['beruf'] ?? null),
            'profession' => $this->text($details['hauptberuf'] ?? $details['beruf'] ?? null),
            'employer' => $this->text($details['firma'] ?? $details['arbeitgeber'] ?? null),
            'locations' => $this->locations($details),
            'description' => $this->text($details['stellenangebotsBeschreibung'] ?? $details['stellenbeschreibung'] ?? null, self::MAX_DESCRIPTION_LENGTH, true),
            'publishedAt' => $this->date(data_get($details, 'veroeffentlichungszeitraum.von') ?? $details['datumErsteVeroeffentlichung'] ?? $details['aktuelleVeroeffentlichungsdatum'] ?? null),
            'startDate' => $this->date(data_get($details, 'eintrittszeitraum.von') ?? $details['eintrittsdatum'] ?? null),
            'offerType' => $this->offerType($details['stellenangebotsart'] ?? $details['angebotsart'] ?? null),
            'contractDuration' => is_string($contractDuration) && in_array($contractDuration, self::CONTRACT_DURATIONS, true) ? $contractDuration : null,
            'workingTimes' => $this->workingTimes($details),
            'homeOffice' => ($details['homeofficemoeglich'] ?? false) === true,
            'officialUrl' => self::OFFICIAL_URL.rawurlencode($refnr),
            'applicationUrl' => $applicationUrl,
            'applicationHost' => $this->hostOf($applicationUrl),
        ];
    }

    /**
     * Liefert die URL nur, wenn sie ein sauberes https-Ziel ist; alles andere entfaellt (javascript:, data:, http:, ...).
     * Abgelehnt werden zudem Userinfo (user:pass@), Backslashes, Whitespace, Steuer- und Formatzeichen (u. a. Bidi, Zero-Width),
     * ungueltiges UTF-8 und Hosts ausserhalb von ASCII (IDN nur in Punycode-Schreibweise `xn--`; Unicode-Hosts werden
     * im Zweifel abgelehnt, weil sie sich als anderer Host ausgeben koennen).
     */
    public function safeHttpUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\\\\\p{Cc}\p{Cf}\p{Z}\s]/u', $url) !== 0) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (strtolower($parts['scheme'] ?? '') !== 'https' || preg_match('/^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?\z/', $parts['host'] ?? '') !== 1) {
            return null;
        }

        return $url;
    }

    /**
     * Zielhost einer bereits geprueften URL (nur zur Anzeige als Text).
     */
    private function hostOf(?string $safeUrl): ?string
    {
        return $safeUrl === null ? null : strtolower((string) parse_url($safeUrl, PHP_URL_HOST));
    }

    private function refnr(mixed $value): ?string
    {
        return is_string($value) && preg_match(self::REFNR_PATTERN, $value) === 1 ? $value : null;
    }

    /**
     * Gibt einen bereinigten, nicht leeren String gekuerzt zurueck, sonst null. Entfernt werden Steuer- und
     * Formatzeichen (\p{Cc}, \p{Cf}: u. a. Null-Byte, Bidi-Override, Zero-Width). Kurzfelder werden einzeilig mit
     * normalisiertem Whitespace ausgegeben; bei $multiline bleiben Zeilenumbrueche und Tabs erhalten (Beschreibung).
     * Die Ausgabe im Frontend erfolgt ausschliesslich als Text.
     */
    private function text(mixed $value, int $maxLength = self::MAX_SHORT_TEXT_LENGTH, bool $multiline = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        if ($multiline) {
            $text = preg_replace(['/\r\n?/', '/[^\P{Cc}\n\t]|\p{Cf}/u'], ["\n", ''], $value);
        } else {
            $text = preg_replace(['/\s+/u', '/[\p{Cc}\p{Cf}]/u'], [' ', ''], $value);
        }

        if ($text === null) {
            return null;
        }

        $text = trim($text);

        return $text === '' ? null : mb_substr($text, 0, $maxLength);
    }

    /**
     * Nimmt nur das Datum (JJJJ-MM-TT) eines ISO-Wertes an.
     */
    private function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ].*)?\z/s', $value, $matches) !== 1) {
            return null;
        }

        return checkdate((int) substr($matches[1], 5, 2), (int) substr($matches[1], 8, 2), (int) substr($matches[1], 0, 4))
            ? $matches[1]
            : null;
    }

    private function offerType(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::OFFER_TYPES, true) ? $value : null;
    }

    /**
     * Arbeitsorte als "PLZ Ort" (ohne Strasse), begrenzt und ohne Duplikate.
     *
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function locations(array $item): array
    {
        $addresses = [];

        foreach (is_array($item['stellenlokationen'] ?? null) ? $item['stellenlokationen'] : [] as $location) {
            $addresses[] = is_array($location) ? ($location['adresse'] ?? null) : null;
        }

        foreach (is_array($item['arbeitsorte'] ?? null) ? $item['arbeitsorte'] : [] as $location) {
            $addresses[] = $location;
        }

        $addresses[] = $item['arbeitsort'] ?? null;

        $locations = [];

        foreach ($addresses as $address) {
            if (! is_array($address)) {
                continue;
            }

            $plz = $this->text($address['plz'] ?? null, 10);
            $place = $this->text($address['ort'] ?? $address['region'] ?? null, 100);
            $label = trim(($plz ?? '').' '.($place ?? ''));

            if ($label !== '' && ! in_array($label, $locations, true)) {
                $locations[] = $label;
            }
        }

        return array_slice($locations, 0, self::MAX_LOCATIONS);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return list<string>
     */
    private function workingTimes(array $details): array
    {
        $partTimeFlags = ['arbeitszeitTeilzeitVormittag', 'arbeitszeitTeilzeitNachmittag', 'arbeitszeitTeilzeitAbend', 'arbeitszeitTeilzeitFlexibel'];
        $times = [];

        if (($details['arbeitszeitVollzeit'] ?? false) === true) {
            $times[] = 'fulltime';
        }

        foreach ($partTimeFlags as $flag) {
            if (($details[$flag] ?? false) === true) {
                $times[] = 'parttime';
                break;
            }
        }

        if (($details['arbeitszeitSchichtNachtWochenende'] ?? false) === true) {
            $times[] = 'shift';
        }

        return $times;
    }
}
