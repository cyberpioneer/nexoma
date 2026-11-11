<?php

namespace App\Models;

use Database\Factories\CareerEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eintrag im Berufs- oder Ausbildungswerdegang eines Benutzers. `user_id` ist bewusst nicht massenzuweisbar
 * und wird nur ueber die Beziehung {@see User::careerEntries()} gesetzt.
 *
 * @property int $id
 * @property int $user_id
 * @property string $what Was (Beruf, Ausbildung, Studium ...)
 * @property string $where Wo (Arbeitgeber, Schule, Hochschule ...)
 * @property Carbon $from_date
 * @property Carbon|null $to_date null = laufend
 * @property string $qualification_or_role Abschluss oder Taetigkeit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['what', 'where', 'from_date', 'to_date', 'qualification_or_role'])]
class CareerEntry extends Model
{
    /** @use HasFactory<CareerEntryFactory> */
    use HasFactory;

    /** Hoechstzahl Werdegang-Eintraege je Benutzer. */
    public const MAX_PER_USER = 30;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }
}
