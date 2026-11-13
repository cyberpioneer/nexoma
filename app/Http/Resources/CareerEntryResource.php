<?php

namespace App\Http\Resources;

use App\Models\CareerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Whitelist eines Werdegang-Eintrags fuer den Client (ohne user_id und Zeitstempel).
 *
 * @mixin CareerEntry
 */
class CareerEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'what' => $this->what,
            'where' => $this->where,
            'from_date' => $this->from_date->format('Y-m-d'),
            'to_date' => $this->to_date?->format('Y-m-d'),
            'qualification_or_role' => $this->qualification_or_role,
        ];
    }
}
