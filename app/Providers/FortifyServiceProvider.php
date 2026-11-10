<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Responses\FailedPasswordResetResponse as FortifyFailedPasswordResetResponse;
use Symfony\Component\HttpFoundation\Response;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Gegen Benutzer-Enumeration: unbekannte Adresse und Throttling liefern dieselbe Antwort wie Erfolg.
        $this->app->bind(
            FailedPasswordResetLinkRequestResponse::class,
            fn ($app) => $app->make(SuccessfulPasswordResetLinkRequestResponse::class, [
                'status' => Password::RESET_LINK_SENT,
            ]),
        );

        // Ungueltiger oder abgelaufener Reset-Link: zurueck zur Reset-Seite statt auf die zuletzt gemerkte Seite ("/").
        $this->app->bind(FailedPasswordResetResponse::class, fn ($app, array $parameters) => new class($parameters['status']) extends FortifyFailedPasswordResetResponse
        {
            public function toResponse($request): Response
            {
                if ($request->wantsJson()) {
                    return parent::toResponse($request);
                }

                $token = $request->input('token');
                $email = $request->input('email');

                return redirect()
                    ->route('password.reset', [
                        'token' => is_string($token) ? $token : '',
                        'email' => is_string($email) ? $email : '',
                    ])
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => trans($this->status)]);
            }
        });

        // Nach dem Logout die verschluesselte Inertia-History verwerfen.
        $this->app->singleton(LogoutResponse::class, fn () => new class implements LogoutResponse
        {
            public function toResponse($request): Response
            {
                if ($request->wantsJson()) {
                    return new JsonResponse('', 204);
                }

                Inertia::clearHistory();

                return redirect('/');
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => Inertia::render('auth/Login'));
        Fortify::registerView(fn () => Inertia::render('auth/Register'));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/ForgotPassword'));
        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => is_string($request->query('email')) ? $request->query('email') : '',
        ]));
        Fortify::verifyEmailView(fn () => Inertia::render('auth/VerifyEmail'));

        // Passwortbestaetigung ist in Release 1 nicht vorgesehen: sauberes 404 statt 500.
        Fortify::confirmPasswordView(fn () => abort(404));
    }
}
