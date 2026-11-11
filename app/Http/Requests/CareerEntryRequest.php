<?php

namespace App\Http\Requests;

use App\Models\CareerEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anlegen und Aendern eines Werdegang-Eintrags. Die Eigentumspruefung laeuft ueber die Policy
 * (fremde Eintraege ergeben 403); `user_id` wird nie aus der Anfrage uebernommen.
 */
class CareerEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $careerEntry = $this->route('careerEntry');
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return $careerEntry instanceof CareerEntry
            ? $user->can('update', $careerEntry)
            : $user->can('create', CareerEntry::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'what' => ['required', 'string', 'max:150'],
            'where' => ['required', 'string', 'max:150'],
            'from_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1950-01-01', 'before_or_equal:today'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from_date', 'before_or_equal:today'],
            'qualification_or_role' => ['required', 'string', 'max:200'],
        ];
    }

    /**
     * Nur die validierten Felder, Texte zugeschnitten.
     *
     * @return array<string, mixed>
     */
    public function entryAttributes(): array
    {
        $validated = $this->validated();

        foreach (['what', 'where', 'qualification_or_role'] as $field) {
            $validated[$field] = trim((string) $validated[$field]);
        }

        $validated['to_date'] ??= null;

        return $validated;
    }
}
