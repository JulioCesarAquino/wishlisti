<?php

namespace Database\Factories\Contacts;

use App\Models\Contacts\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name(),
            'whatsapp' => $this->faker->numerify('55###########'),
            'email' => $this->faker->optional()->safeEmail(),
        ];
    }
}
