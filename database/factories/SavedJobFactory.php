<?php

namespace Database\Factories;

use App\Models\SavedJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedJob>
 */
class SavedJobFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'refnr' => fake()->numerify('10001-##########-S'),
            'title' => fake()->jobTitle(),
            'employer' => fake()->company(),
            'location' => fake()->city(),
            'note' => null,
        ];
    }

    /**
     * Mit persoenlichem Vermerk.
     */
    public function withNote(?string $note = null): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => $note ?? fake()->sentence(),
        ]);
    }
}
