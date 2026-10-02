<?php

namespace Database\Factories\Events;

use App\Models\Events\Event;
use App\Models\Events\EventLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventLocation>
 */
class EventLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Local do evento',
            'address' => $this->faker->streetAddress().', São Paulo - SP',
            'position' => 0,
        ];
    }
}
