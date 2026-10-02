<?php

namespace Database\Factories\Events;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->sentence(3), '.');

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(100, 999),
            'type' => $this->faker->randomElement(['casamento', 'cha_bebe', 'cha_panela', 'aniversario']),
            'title' => $title,
            'event_date' => $this->faker->dateTimeBetween('now', '+1 year'),
            'description' => $this->faker->paragraph(),
            'is_published' => true,
        ];
    }

    public function withFeatures(Feature ...$features): static
    {
        return $this->afterCreating(fn (Event $event) => $event->syncFeatures(array_values($features)));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withRsvpSettings(array $attributes): static
    {
        return $this->afterCreating(fn (Event $event) => $event->rsvpSettings()->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withAppearance(array $attributes): static
    {
        return $this->afterCreating(fn (Event $event) => $event->appearance()->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withLocation(array $attributes = []): static
    {
        return $this->afterCreating(fn (Event $event) => $event->locations()->create([
            'name' => 'Local do evento',
            'address' => 'Rua das Flores, 123, São Paulo - SP',
            ...$attributes,
        ]));
    }

    public function withMercadoPago(?string $accessToken = 'TEST-token', ?string $publicKey = 'TEST-public-key'): static
    {
        return $this->afterCreating(fn (Event $event) => $event->paymentSettings()->create([
            'mp_access_token' => $accessToken,
            'mp_public_key' => $publicKey,
        ]));
    }
}
