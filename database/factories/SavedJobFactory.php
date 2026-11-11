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
     * Vom automatischen Matcher gefundene Vakanz mit KI-Bewertung.
     */
    public function aiMatcher(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => SavedJob::SOURCE_AI_MATCHER,
            'ai_summary' => fake()->sentence(),
            'temperature' => fake()->numberBetween(SavedJob::MIN_TEMPERATURE, SavedJob::MAX_TEMPERATURE),
            'distance_km' => fake()->numberBetween(0, 100),
        ]);
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
