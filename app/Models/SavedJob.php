<?php

namespace App\Models;

use Database\Factories\SavedJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Von einem Benutzer gespeicherte Vakanz. `user_id` ist bewusst nicht massenzuweisbar und wird nur ueber
 * die Beziehung {@see User::savedJobs()} gesetzt. Ebenso sind `source` und die KI-Felder nicht massenzuweisbar:
 * Web und MCP speichern immer als `manual` (Standardwert), nur der Matcher setzt sie gezielt per forceFill.
 *
 * @property int $id
 * @property int $user_id
 * @property string $refnr
 * @property string|null $title
 * @property string|null $employer
 * @property string|null $location
 * @property string|null $note
 * @property string $source `manual` oder `ai_matcher`
 * @property string|null $ai_summary KI-Zusammenfassung (nur ai_matcher, hoechstens 255 Zeichen)
 * @property int|null $temperature Passung 1 bis 10 (nur ai_matcher)
 * @property int|null $distance_km Entfernung in km (nur ai_matcher)
 * @property Carbon|null $briefed_at Zeitpunkt der Erwaehnung in einem Audio-Briefing
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['refnr', 'title', 'employer', 'location', 'note'])]
class SavedJob extends Model
{
    /** @use HasFactory<SavedJobFactory> */
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AI_MATCHER = 'ai_matcher';

    public const SOURCES = [self::SOURCE_MANUAL, self::SOURCE_AI_MATCHER];

    public const MAX_AI_SUMMARY_LENGTH = 255;

    public const MIN_TEMPERATURE = 1;

    public const MAX_TEMPERATURE = 10;

    /**
     * Standardwerte auch auf dem frisch erzeugten Model (der Datenbank-Default greift erst nach dem Neuladen).
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['source' => self::SOURCE_MANUAL];

    /** Hoechstzahl gespeicherter Vakanzen je Benutzer. */
    public const MAX_PER_USER = 100;

    /** Hoechstlaenge des Vermerks in Zeichen. */
    public const MAX_NOTE_LENGTH = 1000;

    /**
     * Erzwingt die Datenintegritaet auch ausserhalb von PostgreSQL (dort zusaetzlich CHECK-Constraints):
     * gueltige Quelle, Passung 1 bis 10, Zusammenfassung hoechstens 255 Zeichen und KI-Felder nur bei `ai_matcher`.
     */
    protected static function booted(): void
    {
        static::saving(function (SavedJob $savedJob): void {
            if (! in_array($savedJob->source, self::SOURCES, true)) {
                throw new InvalidArgumentException('Ungueltige Quelle der gespeicherten Vakanz.');
            }

            $aiValues = [$savedJob->ai_summary, $savedJob->temperature, $savedJob->distance_km, $savedJob->briefed_at];

            if ($savedJob->source === self::SOURCE_MANUAL && array_filter($aiValues, fn (mixed $value): bool => $value !== null) !== []) {
                throw new InvalidArgumentException('KI-Felder sind nur bei Vakanzen des Matchers erlaubt.');
            }

            if ($savedJob->temperature !== null && ($savedJob->temperature < self::MIN_TEMPERATURE || $savedJob->temperature > self::MAX_TEMPERATURE)) {
                throw new InvalidArgumentException('Die Passung muss zwischen 1 und 10 liegen.');
            }

            if ($savedJob->distance_km !== null && $savedJob->distance_km < 0) {
                throw new InvalidArgumentException('Die Entfernung darf nicht negativ sein.');
            }

            if ($savedJob->ai_summary !== null && mb_strlen($savedJob->ai_summary) > self::MAX_AI_SUMMARY_LENGTH) {
                throw new InvalidArgumentException('Die KI-Zusammenfassung ist zu lang.');
            }
        });
    }

    /**
     * Nur manuell gespeicherte Vakanzen.
     *
     * @param  Builder<SavedJob>  $query
     */
    #[Scope]
    protected function manual(Builder $query): void
    {
        $query->where('source', self::SOURCE_MANUAL);
    }

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
            'temperature' => 'integer',
            'distance_km' => 'integer',
            'briefed_at' => 'datetime',
        ];
    }
}
