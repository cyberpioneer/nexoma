<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OAuth\RedirectUriDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

class ConnectedAppController extends Controller
{
    /**
     * Listet die Apps (OAuth-Clients), die der angemeldete Benutzer mit einem aktiven Token verbunden hat.
     * Nur eigene Tokens; Client-Daten sind unvertrauenswuerdig und gehen nur als Whitelist an den Client.
     */
    public function index(Request $request): Response
    {
        $tokens = $this->user($request)->tokens()
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query
                ->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn (Builder $refresh) => $refresh
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())))
            ->with('client')
            ->orderBy('created_at')
            ->get();

        $apps = $tokens->groupBy('client_id')->map(function ($clientTokens, $clientId): array {
            /** @var Token $first */
            $first = $clientTokens->first();

            return [
                'id' => (string) $clientId,
                'name' => (string) $first->client?->name,
                'redirectHosts' => RedirectUriDisplay::describeAll($first->client?->getAttribute('redirect_uris')),
                'connectedAt' => $first->created_at?->format('Y-m-d'),
                'scopes' => $clientTokens->pluck('scopes')->flatten()->unique()->values()->all(),
            ];
        })->values()->all();

        return Inertia::render('ConnectedApps/Index', ['apps' => $apps]);
    }

    /**
     * Widerruft alle Access- und Refresh-Tokens des angemeldeten Benutzers fuer den Client.
     * Es werden ausschliesslich Tokens des Benutzers betrachtet; fremde oder unbekannte Clients ergeben 404.
     */
    public function destroy(Request $request, string $clientId): RedirectResponse
    {
        $tokens = $this->user($request)->tokens()->where('client_id', $clientId)->with('refreshToken')->get();

        abort_if($tokens->isEmpty(), 404);

        DB::transaction(function () use ($tokens): void {
            foreach ($tokens as $token) {
                $token->revoke();
                $token->refreshToken?->revoke();
            }
        });

        return to_route('connected-apps.index');
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
