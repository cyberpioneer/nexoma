<?php

namespace App\Models;

use Database\Factories\SeenJobReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Referenznummer einer Vakanz, die dem Benutzer vom automatischen Matcher bereits vorgelegt wurde. Zusammen mit
 * den gespeicherten Vakanzen verhindert sie, dass dieselbe Stelle erneut gespeichert oder im Briefing erwaehnt wird.
 * `user_id` und `refnr` sind nicht massenzuweisbar; geschrieben wird nur ueber {@see self::rememberFor()}.
 *
 * @property int $id
 * @property int $user_id
 * @property string $refnr
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SeenJobReference extends Model
{
    /** @use HasFactory<SeenJobReferenceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Die Referenznummern, die fuer den Benutzer neu sind: weder bereits gespeichert (manuell oder vom Matcher)
     * noch vom Matcher schon einmal vorgelegt. Reihenfolge und Eindeutigkeit der Eingabe bleiben erhalten.
     *
     * @param  array<int, mixed>  $refnrs  Rohwerte, z. B. aus der BA-API; Nicht-Zeichenketten werden ignoriert
     * @return list<string>
     */
    public static function unseenFor(User $user, array $refnrs): array
    {
        $candidates = array_values(array_unique(array_filter($refnrs, fn (mixed $refnr): bool => is_string($refnr) && $refnr !== '')));

        if ($candidates === []) {
            return [];
        }

        $known = $user->savedJobs()->whereIn('refnr', $candidates)->pluck('refnr')
            ->merge($user->seenJobReferences()->whereIn('refnr', $candidates)->pluck('refnr'))
            ->flip();

        return array_values(array_filter($candidates, fn (string $refnr): bool => ! $known->has($refnr)));
    }

    /**
     * Merkt sich die Referenznummern fuer den Benutzer (idempotent; bereits vorhandene bleiben unveraendert).
     *
     * @param  array<int, mixed>  $refnrs  Rohwerte, z. B. aus der BA-API; Nicht-Zeichenketten werden ignoriert
     */
    public static function rememberFor(User $user, array $refnrs): void
    {
        $now = now();

        $rows = collect($refnrs)
            ->filter(fn (mixed $refnr): bool => is_string($refnr) && $refnr !== '' && mb_strlen($refnr) <= 100)
            ->unique()
            ->map(fn (string $refnr): array => ['user_id' => $user->id, 'refnr' => $refnr, 'created_at' => $now, 'updated_at' => $now])
            ->values()
            ->all();

        if ($rows !== []) {
            self::query()->insertOrIgnore($rows);
        }
    }
}
