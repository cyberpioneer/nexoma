<?php

namespace Database\Factories;

use App\Models\CareerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareerEntry>
 */
class CareerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = fake()->dateTimeBetween('-20 years', '-3 years');

        return [
            'user_id' => User::factory(),
            'what' => fake()->jobTitle(),
            'where' => fake()->company(),
            'from_date' => $from->format('Y-m-d'),
            'to_date' => fake()->dateTimeBetween($from, '-1 year')->format('Y-m-d'),
            'qualification_or_role' => fake()->sentence(4),
        ];
    }

    /**
     * Laufender Eintrag ohne Enddatum.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => ['to_date' => null]);
    }
}
