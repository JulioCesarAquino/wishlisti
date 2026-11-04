<?php

namespace Database\Factories\Events;

use App\Models\Events\Event;
use App\Models\Events\EventAlbum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAlbum>
 */
class EventAlbumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => EventAlbum::DEFAULT_NAME,
            'position' => 0,
            'photos' => null,
        ];
    }
}
