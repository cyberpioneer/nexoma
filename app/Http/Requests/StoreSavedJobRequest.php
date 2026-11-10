<?php

namespace App\Http\Requests;

use App\Models\SavedJob;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Speichern einer Vakanz: Referenznummer (dieselbe Regel wie {@see JobDetailsRequest}) und optionaler Vermerk.
 * Titel, Arbeitgeber und Ort kommen nie aus der Anfrage, sondern vom Server aus der BA-API.
 * Die Autorisierung erfolgt ueber die Routen-Middleware (auth + verified).
 */
class StoreSavedJobRequest extends FormRequest
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
            ...(new JobDetailsRequest)->rules(),
            'note' => ['nullable', 'string', 'max:'.SavedJob::MAX_NOTE_LENGTH],
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
