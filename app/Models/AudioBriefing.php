<?php

namespace App\Models;

use Database\Factories\AudioBriefingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audio-Briefing eines Benutzers. `user_id` sowie Status, Pfad, Dauer und Fehlercode sind nicht massenzuweisbar;
 * sie werden nur ueber die Beziehung {@see User::audioBriefings()} bzw. die Statusmethoden gesetzt.
 *
 * @property int $id
 * @property int $user_id
 * @property string $locale
 * @property string $content_hash
 * @property string $speech_text
 * @property string|null $storage_path Pfad relativ zur Audio-Disk, nur serverseitig
 * @property int|null $duration_ms
 * @property string $status
 * @property string|null $error_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['locale', 'content_hash', 'speech_text'])]
class AudioBriefing extends Model
{
    /** @use HasFactory<AudioBriefingFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_READY, self::STATUS_FAILED];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markProcessing(): void
    {
        $this->forceFill(['status' => self::STATUS_PROCESSING, 'error_code' => null])->save();
    }

    public function markReady(string $storagePath, int $durationMs): void
    {
        $this->forceFill([
            'status' => self::STATUS_READY,
            'storage_path' => $storagePath,
            'duration_ms' => $durationMs,
            'error_code' => null,
        ])->save();
    }

    public function markFailed(string $errorCode): void
    {
        $this->forceFill(['status' => self::STATUS_FAILED, 'error_code' => mb_substr($errorCode, 0, 64)])->save();
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY && $this->storage_path !== null;
    }
}
