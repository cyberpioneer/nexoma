<?php

namespace App\Http\Requests;

use App\Services\Jobsuche\JobsucheApiClient;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validiert die Referenznummer (refnr) fuer {@see JobsucheApiClient::details()}; die Regel ist dieselbe wie im Client.
 *
 * Web: Die Klasse im Controller anfordern; ein Routenparameter `refnr` wird mitvalidiert. Danach `$request->validated('refnr')`.
 *
 * MCP: `Validator::make($arguments, (new JobDetailsRequest)->rules())->validate()['refnr']`.
 *
 * Die Autorisierung erfolgt ueber Routen-Middleware bzw. die MCP-Authentifizierung, nicht hier.
 */
class JobDetailsRequest extends FormRequest
{
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
            'refnr' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{1,100}\z/D'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $routeRefnr = $this->route('refnr');

        if ($routeRefnr !== null) {
            $this->merge(['refnr' => $routeRefnr]);
        }
    }
}
