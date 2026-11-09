<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /** Anfragen pro Minute und Benutzer (sonst IP) fuer die Jobsuche. */
    public const JOBSUCHE_ATTEMPTS_PER_MINUTE = 30;

    /** Anfragen pro Minute und Benutzer (sonst IP) fuer Stellendetails. */
    public const JOBDETAILS_ATTEMPTS_PER_MINUTE = 60;

    /** Versuche pro Minute und E-Mail+IP fuer Login/Registrierung/Reset. */
    public const AUTH_ATTEMPTS_PER_MINUTE = 5;

    /** Versuche pro Minute und IP (unabhaengig von der E-Mail) fuer Login/Registrierung/Reset. */
    public const AUTH_IP_ATTEMPTS_PER_MINUTE = 20;

    /** Sprachwechsel pro Minute und Benutzer (sonst IP); genug fuer Bedienung, zu wenig fuer Missbrauch. */
    public const LOCALE_ATTEMPTS_PER_MINUTE = 20;

    /** Anfragen pro Minute und Benutzer (sonst IP) fuer MCP. */
    public const MCP_ATTEMPTS_PER_MINUTE = 60;

    /** Anfragen pro Minute und IP (vor der Authentifizierung) fuer MCP; bremst Token-Raten und 401-Fluten. */
    public const MCP_IP_ATTEMPTS_PER_MINUTE = 120;

    /** Anfragen pro Minute und IP fuer den OAuth-Token-Endpunkt. */
    public const OAUTH_TOKEN_ATTEMPTS_PER_MINUTE = 30;

    /** Dynamische Client-Registrierungen pro Stunde und IP. */
    public const OAUTH_REGISTER_ATTEMPTS_PER_HOUR = 10;

    /** Dynamische Client-Registrierungen pro Stunde insgesamt (alle IPs). */
    public const OAUTH_REGISTER_GLOBAL_ATTEMPTS_PER_HOUR = 200;

    /** Anfragen pro Minute und IP fuer die OAuth-Metadaten (.well-known). */
    public const OAUTH_METADATA_ATTEMPTS_PER_MINUTE = 60;

    /** Gueltigkeit eines OAuth-Access-Tokens in Stunden. */
    public const OAUTH_ACCESS_TOKEN_LIFETIME_HOURS = 1;

    /** Gueltigkeit eines OAuth-Refresh-Tokens in Tagen. */
    public const OAUTH_REFRESH_TOKEN_LIFETIME_DAYS = 30;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Passport-Routen werden bewusst nicht automatisch registriert, sondern gezielt in
        // routes/web.php (Consent, auth + verified) und routes/ai.php (Token-Endpunkt) angelegt.
        Passport::ignoreRoutes();

        // Kein Device-Code-Grant: weder Route noch Grant werden genutzt.
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureUrlGeneration();
        $this->configureRateLimiting();
        $this->configurePassport();
    }

    /**
     * Konfiguriert OAuth2: genau ein Scope (`mcp:use`) und kurze Token-Lebensdauern.
     */
    protected function configurePassport(): void
    {
        Passport::tokensCan([Registrar::OAUTH_SCOPE => 'Use MCP server']);
        Passport::defaultScopes([Registrar::OAUTH_SCOPE]);

        Passport::tokensExpireIn(CarbonInterval::hours(self::OAUTH_ACCESS_TOKEN_LIFETIME_HOURS));
        Passport::refreshTokensExpireIn(CarbonInterval::days(self::OAUTH_REFRESH_TOKEN_LIFETIME_DAYS));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Erzwingt in Produktion APP_URL und HTTPS fuer generierte URLs (Mail-Links), unabhaengig vom Host-Header.
     */
    protected function configureUrlGeneration(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        URL::forceRootUrl(config('app.url'));
        URL::forceScheme('https');
    }

    /**
     * Definiert die benannten Rate Limiter (Nutzung per Middleware `throttle:<name>`).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('jobsuche', fn (Request $request): Limit => Limit::perMinute(self::JOBSUCHE_ATTEMPTS_PER_MINUTE)
            ->by($this->userOrIpKey($request)));

        RateLimiter::for('jobdetails', fn (Request $request): Limit => Limit::perMinute(self::JOBDETAILS_ATTEMPTS_PER_MINUTE)
            ->by($this->userOrIpKey($request)));

        RateLimiter::for('auth', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower(mb_substr($email, 0, 255)) : '';

            return [
                Limit::perMinute(self::AUTH_ATTEMPTS_PER_MINUTE)
                    ->by('email:'.Str::transliterate($email.'|'.$request->ip())),
                Limit::perMinute(self::AUTH_IP_ATTEMPTS_PER_MINUTE)
                    ->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('locale', fn (Request $request): Limit => Limit::perMinute(self::LOCALE_ATTEMPTS_PER_MINUTE)
            ->by($this->userOrIpKey($request)));

        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(self::MCP_ATTEMPTS_PER_MINUTE)
            ->by($this->userOrIpKey($request)));

        RateLimiter::for('mcp-ip', fn (Request $request): Limit => Limit::perMinute(self::MCP_IP_ATTEMPTS_PER_MINUTE)
            ->by('ip:'.$request->ip()));

        RateLimiter::for('oauth-token', fn (Request $request): Limit => Limit::perMinute(self::OAUTH_TOKEN_ATTEMPTS_PER_MINUTE)
            ->by('ip:'.$request->ip()));

        RateLimiter::for('oauth-register', fn (Request $request): array => [
            Limit::perHour(self::OAUTH_REGISTER_ATTEMPTS_PER_HOUR)->by('ip:'.$request->ip()),
            Limit::perHour(self::OAUTH_REGISTER_GLOBAL_ATTEMPTS_PER_HOUR)->by('global'),
        ]);

        RateLimiter::for('oauth-metadata', fn (Request $request): Limit => Limit::perMinute(self::OAUTH_METADATA_ATTEMPTS_PER_MINUTE)
            ->by('ip:'.$request->ip()));
    }

    /**
     * Schluessel pro angemeldetem Benutzer, sonst pro IP-Adresse.
     */
    private function userOrIpKey(Request $request): string
    {
        $userId = $request->user()?->getAuthIdentifier();

        return $userId !== null ? 'user:'.$userId : 'ip:'.$request->ip();
    }
}
