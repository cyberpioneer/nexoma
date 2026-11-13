<?php

namespace Database\Factories;

use App\Models\SeenJobReference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeenJobReference>
 */
class SeenJobReferenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'refnr' => fake()->numerify('10001-##########-S'),
        ];
    }
}
