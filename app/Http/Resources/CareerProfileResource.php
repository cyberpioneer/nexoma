<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whitelist der Profilfelder des angemeldeten Benutzers (keine E-Mail, keine Stripe- oder Einwilligungsdaten).
 *
 * @mixin User
 */
class CareerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'preferred_language' => $this->preferred_language,
            'bio' => $this->bio,
            'search_location' => $this->search_location,
            'search_radius' => $this->search_radius,
        ];
    }
}
