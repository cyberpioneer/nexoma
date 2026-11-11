<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anfrage zur KI-Optimierung des Profiltexts. Beides wird vor dem Aufruf des KI-Dienstes strikt geprueft:
 * nichtleerer Text bis zum Limit, Sprache nur aus den unterstuetzten Codes.
 */
class OptimizeBioRequest extends FormRequest
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
            'bio' => ['required', 'string', 'min:10', 'max:'.User::BIO_MAX_LENGTH],
            'preferred_language' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales')))],
        ];
    }
}
