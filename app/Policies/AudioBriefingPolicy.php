<?php

namespace App\Policies;

use App\Models\AudioBriefing;
use App\Models\User;

/**
 * Audio-Briefings gehoeren genau einem Benutzer: nur der Besitzer darf sie abspielen. Die Paywall liegt davor
 * (Middleware `subscribed`).
 */
class AudioBriefingPolicy
{
    public function view(User $user, AudioBriefing $audioBriefing): bool
    {
        return $audioBriefing->user_id === $user->id;
    }
}
