<?php

namespace App\Models;

use Database\Factories\SavedJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Von einem Benutzer gespeicherte Vakanz. `user_id` ist bewusst nicht massenzuweisbar und wird nur ueber
 * die Beziehung {@see User::savedJobs()} gesetzt.
 *
 * @property int $id
 * @property int $user_id
 * @property string $refnr
 * @property string|null $title
 * @property string|null $employer
 * @property string|null $location
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['refnr', 'title', 'employer', 'location', 'note'])]
class SavedJob extends Model
{
    /** @use HasFactory<SavedJobFactory> */
    use HasFactory;

    /** Hoechstzahl gespeicherter Vakanzen je Benutzer. */
    public const MAX_PER_USER = 100;

    /** Hoechstlaenge des Vermerks in Zeichen. */
    public const MAX_NOTE_LENGTH = 1000;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
