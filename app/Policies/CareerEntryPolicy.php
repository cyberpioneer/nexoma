<?php

namespace App\Policies;

use App\Models\CareerEntry;
use App\Models\User;

/**
 * Werdegang-Eintraege gehoeren genau einem Benutzer: nur der Besitzer darf sie sehen, aendern und loeschen.
 * Anlegen ist bis zur Obergrenze je Benutzer erlaubt. Die Paywall liegt davor (Middleware `subscribed`).
 */
class CareerEntryPolicy
{
    public function view(User $user, CareerEntry $careerEntry): bool
    {
        return $careerEntry->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->careerEntries()->count() < CareerEntry::MAX_PER_USER;
    }

    public function update(User $user, CareerEntry $careerEntry): bool
    {
        return $careerEntry->user_id === $user->id;
    }

    public function delete(User $user, CareerEntry $careerEntry): bool
    {
        return $careerEntry->user_id === $user->id;
    }
}
