<?php

namespace Tests\Feature\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RsvpStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_confirm_attendance(): void
    {
        $event = Event::factory()->create(['is_published' => true]);

        $response = $this->post("/{$event->slug}/rsvp", [
            'guest' => [
                'name' => 'Maria Convidada',
                'whatsapp' => '5511999999999',
                'email' => 'maria@example.com',
            ],
            'attending' => true,
            'guests_count' => 2,
        ]);

        $response->assertRedirect();
        $response->assertInertiaFlash('toast');

        $guest = Guest::where('email', 'maria@example.com')->first();

        $this->assertNotNull($guest);
        $this->assertSame(Guest::RSVP_CONFIRMED, $guest->rsvp_status);
        $this->assertSame(2, $guest->rsvp_guests_count);
        $this->assertNotNull($guest->rsvp_responded_at);
    }

    public function test_a_guest_can_decline_attendance(): void
    {
        $event = Event::factory()->create(['is_published' => true]);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => [
                'name' => 'João Ausente',
                'whatsapp' => '5511988888888',
            ],
            'attending' => false,
        ])->assertRedirect();

        $guest = Guest::where('name', 'João Ausente')->first();

        $this->assertSame(Guest::RSVP_DECLINED, $guest->rsvp_status);
        $this->assertNull($guest->rsvp_guests_count);
    }

    public function test_guests_count_is_required_when_attending(): void
    {
        $event = Event::factory()->create(['is_published' => true]);

        $response = $this->post("/{$event->slug}/rsvp", [
            'guest' => [
                'name' => 'Maria Convidada',
                'whatsapp' => '5511999999999',
            ],
            'attending' => true,
        ]);

        $response->assertSessionHasErrors('guests_count');
    }

    public function test_it_reuses_the_existing_guest_via_cookie(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        $guest = $event->guests()->create([
            'name' => 'Guest Antigo',
            'whatsapp' => '5511888888888',
            'identifier' => (string) Str::uuid(),
        ]);

        $this->withCookie(Guest::cookieName($event), $guest->identifier)
            ->post("/{$event->slug}/rsvp", [
                'guest' => [
                    'name' => 'Guest Antigo',
                    'whatsapp' => '5511888888888',
                ],
                'attending' => true,
                'guests_count' => 1,
            ])->assertRedirect();

        $this->assertSame(1, $event->guests()->count());
        $this->assertSame(Guest::RSVP_CONFIRMED, $guest->fresh()->rsvp_status);
    }

    public function test_a_guest_with_the_cookie_from_before_the_rename_is_still_recognised(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        $guest = $event->guests()->create([
            'name' => 'Guest Antigo',
            'whatsapp' => '5511888888888',
            'identifier' => (string) Str::uuid(),
        ]);

        $this->withCookie("wishlist_guest_{$event->id}", $guest->identifier)
            ->post("/{$event->slug}/rsvp", [
                'guest' => ['name' => 'Novo Nome', 'whatsapp' => '5511777777777'],
                'attending' => false,
            ])
            ->assertRedirect()
            ->assertCookie(Guest::cookieName($event), $guest->identifier);

        $this->assertSame(1, $event->guests()->count());
        $this->assertSame('Novo Nome', $guest->fresh()->name);
    }

    public function test_unpublished_events_return_404(): void
    {
        $event = Event::factory()->create(['is_published' => false]);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '5511999999999'],
            'attending' => true,
            'guests_count' => 1,
        ])->assertNotFound();
    }
}
