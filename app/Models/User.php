<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $locale
 * @property Carbon|null $consented_at
 * @property string|null $consent_version
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_subscription_id
 * @property string|null $stripe_subscription_status
 * @property Carbon|null $stripe_current_period_end
 * @property string|null $preferred_language
 * @property string|null $bio
 * @property string|null $search_location
 * @property int|null $search_radius
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token', 'stripe_customer_id', 'stripe_subscription_id', 'stripe_subscription_status', 'stripe_current_period_end'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Hoechstlaenge des Profil-Freitexts in Zeichen. */
    public const BIO_MAX_LENGTH = 420;

    /** Hoechstlaenge des Wohn-/Suchorts in Zeichen. */
    public const SEARCH_LOCATION_MAX_LENGTH = 120;

    /** Erlaubte Suchradien in Kilometern. */
    public const SEARCH_RADII = [5, 10, 25, 50, 100, 200];

    /** Stripe-Subscription-Status, der Premium freischaltet (`past_due`, `unpaid`, `canceled` u. a. nicht). */
    public const ACTIVE_SUBSCRIPTION_STATUSES = ['active', 'trialing'];

    /**
     * Sprache fuer Mails und Benachrichtigungen an diesen Benutzer; null, wenn keine gueltige Sprache gespeichert ist.
     */
    public function preferredLocale(): ?string
    {
        return is_string($this->locale) && array_key_exists($this->locale, config('app.supported_locales'))
            ? $this->locale
            : null;
    }

    /**
     * Ob das Stripe-Abonnement aktuell Premium-Funktionen freischaltet: Status `active`/`trialing` und das
     * bezahlte Periodenende (plus Karenz fuer verspaetete Webhooks) ist nicht ueberschritten. Fail-closed.
     */
    public function hasActiveSubscription(): bool
    {
        if (! in_array($this->stripe_subscription_status, self::ACTIVE_SUBSCRIPTION_STATUSES, true)) {
            return false;
        }

        $periodEnd = $this->stripe_current_period_end;

        return $periodEnd !== null
            && $periodEnd->addDays((int) config('services.stripe.grace_days'))->isFuture();
    }

    /**
     * Ob das Profil fuer die automatische Vakanzensuche ausreicht: Sprache und Suchort sind gesetzt, und es gibt einen
     * Profiltext oder mindestens einen Werdegang-Eintrag.
     */
    public function hasCompleteCareerProfile(): bool
    {
        if ($this->preferred_language === null || trim((string) $this->search_location) === '') {
            return false;
        }

        return trim((string) $this->bio) !== '' || $this->careerEntries()->exists();
    }

    /**
     * @return HasMany<CareerEntry, $this>
     */
    public function careerEntries(): HasMany
    {
        return $this->hasMany(CareerEntry::class);
    }

    /**
     * @return HasMany<SeenJobReference, $this>
     */
    public function seenJobReferences(): HasMany
    {
        return $this->hasMany(SeenJobReference::class);
    }

    /**
     * @return HasMany<SavedJob, $this>
     */
    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    /**
     * @return HasMany<AudioBriefing, $this>
     */
    public function audioBriefings(): HasMany
    {
        return $this->hasMany(AudioBriefing::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'consented_at' => 'datetime',
            'stripe_current_period_end' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
