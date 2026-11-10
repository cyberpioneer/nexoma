<?php

namespace App\Http\Requests;

use App\Models\SavedJob;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Aendern des Vermerks einer gespeicherten Vakanz; leer entfernt den Vermerk.
 * Die Eigentumspruefung erfolgt im Controller ueber die Beziehung des Benutzers (fremde Eintraege ergeben 404).
 */
class UpdateSavedJobNoteRequest extends FormRequest
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
            'note' => ['present', 'nullable', 'string', 'max:'.SavedJob::MAX_NOTE_LENGTH],
        ];
    }

    /**
     * Zugeschnittener Vermerk; leer wird zu null.
     */
    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && trim($note) !== '' ? trim($note) : null;
    }
}
