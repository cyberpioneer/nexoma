<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'consent' => ['accepted'],
        ])->validate();

        $user = new User([
            // Vorname und Nachname werden im einen Feld "name" gespeichert.
            'name' => trim(preg_replace('/\s+/u', ' ', $input['first_name'].' '.$input['last_name']) ?? ''),
            'email' => $input['email'],
            'password' => $input['password'],
            // Nur die von der Middleware aufgeloeste Sprache, nie die Eingabe.
            'locale' => app()->getLocale(),
        ]);

        // Nachweis der Kenntnisnahme: Zeitpunkt und Version stammen nie aus der Eingabe.
        $user->forceFill([
            'consented_at' => now(),
            'consent_version' => config('legal.privacy_version'),
        ])->save();

        return $user;
    }
}
