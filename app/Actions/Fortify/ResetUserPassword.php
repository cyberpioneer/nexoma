<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Laravel\Passport\Passport;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        DB::transaction(function () use ($user, $input): void {
            $user->forceFill([
                'password' => $input['password'],
            ])->save();

            $this->revokeOAuthTokens($user);
        });
    }

    /**
     * Widerruft alle OAuth-Access-, Refresh-Tokens und offenen Autorisierungscodes des Benutzers
     * (verbundene KI-Clients muessen nach einem Passwort-Reset neu autorisiert werden).
     */
    protected function revokeOAuthTokens(User $user): void
    {
        $userTokenIds = Passport::token()->newQuery()
            ->where('user_id', $user->getAuthIdentifier())
            ->select('id');

        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $userTokenIds)->update(['revoked' => true]);
        Passport::token()->newQuery()->where('user_id', $user->getAuthIdentifier())->update(['revoked' => true]);
        Passport::authCode()->newQuery()->where('user_id', $user->getAuthIdentifier())->update(['revoked' => true]);
    }
}
