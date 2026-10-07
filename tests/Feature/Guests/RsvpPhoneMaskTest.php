<?php

namespace Tests\Feature\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The page masks the WhatsApp — "(61) 99999-8888" — and the same person is
 * still recognised whatever the format they typed it in before.
 */
class RsvpPhoneMaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_masked_whatsapp_finds_the_guest_who_typed_only_digits(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        $event->guests()->create(['name' => 'Ana', 'whatsapp' => '61999998888', 'identifier' => 'outro-aparelho']);

        // Another browser (no cookie): recognised by the WhatsApp.
        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana Souza', 'whatsapp' => '(61) 99999-8888'],
            'attending' => true,
            'guests_count' => '3',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Guest::count());
        $this->assertSame(3, Guest::first()->rsvp_guests_count);
    }
}
