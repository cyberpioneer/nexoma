<?php

namespace App\Http\Requests;

use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validiert die Suchparameter der Jobsuche (Web und MCP). Die Regeln decken sich mit den Pruefungen in
 * {@see JobsucheApiClient::search()}.
 *
 * Web: Die Klasse im Controller per Type-Hint anfordern und mit
 * `$client->search(...SearchJobsRequest::searchArguments($request->validated()))` aufrufen.
 *
 * MCP: Ohne Request-Objekt, mit denselben Regeln:
 * `$validated = Validator::make($arguments, (new SearchJobsRequest)->rules())->validate();`
 * und danach `$client->search(...SearchJobsRequest::searchArguments($validated))`.
 *
 * Die Autorisierung erfolgt ueber Routen-Middleware bzw. die MCP-Authentifizierung, nicht hier.
 */
class SearchJobsRequest extends FormRequest
{
    private const MAX_TEXT_LENGTH = 255;

    /**
     * Zusatzparameter, die als `extra` an den Client gehen.
     *
     * @var list<string>
     */
    private const EXTRA_PARAMETERS = ['berufsfeld', 'arbeitgeber', 'zeitarbeit', 'befristung', 'arbeitszeit'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'was' => ['required', 'string', 'max:'.self::MAX_TEXT_LENGTH],
            'wo' => ['nullable', 'string', 'max:'.self::MAX_TEXT_LENGTH],
            'umkreis' => ['nullable', 'integer', 'between:0,200'],
            'angebotsart' => ['nullable', 'integer', Rule::in(JobsucheApiClient::ALLOWED_OFFER_TYPES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'between:1,100'],
            'veroeffentlichtseit' => ['nullable', 'integer', 'between:0,100'],
            'berufsfeld' => ['nullable', 'string', 'max:'.self::MAX_TEXT_LENGTH],
            'arbeitgeber' => ['nullable', 'string', 'max:'.self::MAX_TEXT_LENGTH],
            'zeitarbeit' => ['nullable', 'boolean'],
            'befristung' => ['nullable', 'integer', Rule::in([1, 2])],
            'arbeitszeit' => ['nullable', 'string', 'regex:/^(vz|tz|snw|ho|mj)(;(vz|tz|snw|ho|mj))*\z/D'],
        ];
    }

    /**
     * Ueberfuehrt validierte Eingaben in die benannten Argumente von {@see JobsucheApiClient::search()}.
     * Nicht gesetzte sowie leere Werte (null, '', Whitespace) entfallen, damit die Defaults des Clients (page 1, size 100, veroeffentlichtseit 100) gelten.
     *
     * @param  array<string, mixed>  $validated  Ergebnis von validated() bzw. Validator::validate().
     * @return array{was: string, page?: int, size?: int, veroeffentlichtseit?: int, wo?: string, umkreis?: int, angebotsart?: int, extra?: array<string, scalar>}
     */
    public static function searchArguments(array $validated): array
    {
        $arguments = ['was' => (string) $validated['was']];

        foreach (['page', 'size', 'veroeffentlichtseit', 'umkreis', 'angebotsart'] as $key) {
            if (! self::isBlank($validated[$key] ?? null)) {
                $arguments[$key] = (int) $validated[$key];
            }
        }

        if (! self::isBlank($validated['wo'] ?? null)) {
            $arguments['wo'] = (string) $validated['wo'];
        }

        $extra = [];

        foreach (self::EXTRA_PARAMETERS as $key) {
            $value = $validated[$key] ?? null;

            if (self::isBlank($value)) {
                continue;
            }

            $extra[$key] = match ($key) {
                'zeitarbeit' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'befristung' => (int) $value,
                default => (string) $value,
            };
        }

        if ($extra !== []) {
            $arguments['extra'] = $extra;
        }

        return $arguments;
    }

    /**
     * Behandelt null, leere und nur aus Whitespace bestehende Strings gleich, da im MCP-Weg weder TrimStrings noch
     * ConvertEmptyStringsToNull laufen und `nullable` leere Strings durchlaesst.
     */
    private static function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
