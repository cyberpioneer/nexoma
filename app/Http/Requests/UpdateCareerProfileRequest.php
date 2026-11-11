<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Profil des angemeldeten Benutzers. Nur die hier validierten Felder werden uebernommen
 * ({@see self::profileAttributes()}); alles andere in der Anfrage wird ignoriert.
 */
class UpdateCareerProfileRequest extends FormRequest
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
            'preferred_language' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales')))],
            'bio' => ['present', 'nullable', 'string', 'max:'.User::BIO_MAX_LENGTH],
            'search_location' => ['present', 'nullable', 'string', 'max:'.User::SEARCH_LOCATION_MAX_LENGTH],
            'search_radius' => ['present', 'nullable', 'integer', Rule::in(User::SEARCH_RADII)],
        ];
    }

    /**
     * Zugeschnittene Profilwerte; leere Texte werden zu null.
     *
     * @return array{preferred_language: string, bio: string|null, search_location: string|null, search_radius: int|null}
     */
    public function profileAttributes(): array
    {
        $validated = $this->validated();

        return [
            'preferred_language' => (string) $validated['preferred_language'],
            'bio' => $this->trimmedOrNull($validated['bio']),
            'search_location' => $this->trimmedOrNull($validated['search_location']),
            'search_radius' => $validated['search_radius'] === null ? null : (int) $validated['search_radius'],
        ];
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
