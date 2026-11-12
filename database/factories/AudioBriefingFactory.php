<?php

namespace Database\Factories;

use App\Models\AudioBriefing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioBriefing>
 */
class AudioBriefingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'user_id' => User::factory(),
            'locale' => 'de',
            'content_hash' => hash('sha256', $text.fake()->unique()->uuid()),
            'speech_text' => $text,
        ];
    }

    /**
     * Fertiges Briefing mit Audiodatei (der Pfad verweist auf keine echte Datei).
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AudioBriefing::STATUS_READY,
            'storage_path' => 'audio-briefings/'.fake()->uuid().'.wav',
            'duration_ms' => fake()->numberBetween(20_000, 240_000),
        ]);
    }

    public function failed(string $errorCode = 'piper_failed'): static
    {
        return $this->state(fn (array $attributes) => ['status' => AudioBriefing::STATUS_FAILED, 'error_code' => $errorCode]);
    }
}
