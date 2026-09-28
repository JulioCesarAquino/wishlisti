<?php

namespace Database\Factories\Guests;

use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestMessage>
 */
class GuestMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'author_name' => $this->faker->name(),
            'message' => $this->faker->sentence(12),
        ];
    }

    public function approved(): static
    {
        return $this->state(['approved_at' => now()]);
    }
}
