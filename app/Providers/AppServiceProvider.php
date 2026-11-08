<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /** Anfragen pro Minute und Benutzer (sonst IP) fuer die Jobsuche. */
    public const JOBSUCHE_ATTEMPTS_PER_MINUTE = 30;

    /** Anfragen pro Minute und Benutzer (sonst IP) fuer Stellendetails. */
    public const JOBDETAILS_ATTEMPTS_PER_MINUTE = 60;

    /** Versuche pro Minute und E-Mail+IP fuer Login/Registrierung. */
    public const AUTH_ATTEMPTS_PER_MINUTE = 5;

    /** Anfragen pro Minute und Benutzer (sonst IP) fuer MCP. */
    public const MCP_ATTEMPTS_PER_MINUTE = 60;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
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
     * Definiert die benannten Rate Limiter (Nutzung per Middleware `throttle:<name>`).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('jobsuche', fn (Request $request): Limit => Limit::perMinute(self::JOBSUCHE_ATTEMPTS_PER_MINUTE)
            ->by($this->userOrIpKey($request)));

        RateLimiter::for('jobdetails', fn (Request $request): Limit => Limit::perMinute(self::JOBDETAILS_ATTEMPTS_PER_MINUTE)
            ->by($this->userOrIpKey($request)));

        RateLimiter::for('auth', function (Request $request): Limit {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower($email) : '';

            return Limit::perMinute(self::AUTH_ATTEMPTS_PER_MINUTE)
                ->by(Str::transliterate($email.'|'.$request->ip()));
        });

        RateLimiter::for('mcp', fn (Request $request): Limit => Limit::perMinute(self::MCP_ATTEMPTS_PER_MINUTE)
            ->by($this->userOrIpKey($request)));
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
