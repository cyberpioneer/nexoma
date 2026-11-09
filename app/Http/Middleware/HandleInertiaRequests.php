<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->authenticatedUser($request),
            ],
            'status' => $request->session()->get('status'),
            'locale' => app()->getLocale(),
            'availableLocales' => $this->availableLocales(),
            'translations' => fn () => trans('ui'),
        ];
    }

    /**
     * Auswahl fuer den Sprachumschalter: Code und Eigenbezeichnung der Sprache.
     *
     * @return list<array{code: string, name: string}>
     */
    private function availableLocales(): array
    {
        $locales = [];

        foreach (config('app.supported_locales') as $code => $name) {
            $locales[] = ['code' => $code, 'name' => $name];
        }

        return $locales;
    }

    /**
     * Whitelist der Benutzerfelder, die an den Client gehen.
     *
     * @return array{id: int, name: string, email: string, email_verified_at: string|null}|null
     */
    private function authenticatedUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
        ];
    }
}
