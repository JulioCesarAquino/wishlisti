<?php

namespace Tests\Feature\Guests;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How many people each invitation is for: the event's default, and a limit
 * of the guest's own when they bring the whole family.
 */
class RsvpCompanionLimitTest extends TestCase
{
    use RefreshDatabase;

    private function event(?int $maxCompanions, bool $collectCompanions = false): Event
    {
        $factory = Event::factory()->withRsvpSettings(['max_companions' => $maxCompanions, 'collect_companions' => $collectCompanions]);

        return ($collectCompanions ? $factory->withFeatures(Feature::GuestList) : $factory)->create(['is_published' => true]);
    }

    private function rsvp(Event $event, int $guestsCount, string $name = 'Maria', string $whatsapp = '11999999999', array $extra = []): mixed
    {
        return $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => $name, 'whatsapp' => $whatsapp],
            'attending' => true,
            'guests_count' => $guestsCount,
            ...$extra,
        ]);
    }

    public function test_without_a_limit_up_to_the_party_size(): void
    {
        $event = $this->event(null);

        $this->rsvp($event, Guest::MAX_PARTY_SIZE)->assertSessionHasNoErrors();
        $this->rsvp($event, Guest::MAX_PARTY_SIZE + 1, 'Ana', '11988888888')->assertSessionHasErrors('guests_count');
    }

    public function test_the_events_limit_applies_to_everyone_without_their_own(): void
    {
        $event = $this->event(2);

        $this->rsvp($event, 4)->assertSessionHasErrors(['guests_count' => 'Seu convite é para até 3 pessoas, contando com você.']);
        $this->assertSame(0, $event->guests()->count());

        $this->rsvp($event, 3)->assertSessionHasNoErrors();
        $this->assertSame(3, $event->guests()->first()->rsvp_guests_count);
    }

    public function test_a_limit_of_zero_makes_the_invitation_individual(): void
    {
        $event = $this->event(0);

        $this->rsvp($event, 2)->assertSessionHasErrors(['guests_count' => 'Seu convite é individual: confirme só a sua presença.']);
        $this->rsvp($event, 1)->assertSessionHasNoErrors();
    }

    public function test_a_guest_with_a_limit_of_their_own_brings_the_family(): void
    {
        $event = $this->event(1);
        $event->guests()->create(['name' => 'Maria Souza', 'whatsapp' => '11999999999', 'max_companions' => 6]);

        $this->rsvp($event, 7, 'Maria Souza')->assertSessionHasNoErrors();
        $this->assertSame(7, $event->guests()->firstWhere('name', 'Maria Souza')->rsvp_guests_count);

        // Someone else is still held to the event's.
        $this->rsvp($event, 3, 'Ana', '11988888888')->assertSessionHasErrors('guests_count');
    }

    public function test_a_guest_limited_below_the_events_limit(): void
    {
        $event = $this->event(4);
        $event->guests()->create(['name' => 'Maria', 'whatsapp' => '11999999999', 'max_companions' => 0]);

        $this->rsvp($event, 2)->assertSessionHasErrors('guests_count');
    }

    public function test_declining_is_never_limited(): void
    {
        $event = $this->event(0);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'attending' => false,
        ])->assertSessionHasNoErrors();
    }

    public function test_who_confirmed_before_the_limit_came_down_keeps_their_party(): void
    {
        $event = $this->event(1);
        $event->guests()->create([
            'name' => 'Maria',
            'whatsapp' => '11999999999',
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_guests_count' => 5,
        ]);

        $this->rsvp($event, 5)->assertSessionHasNoErrors();
        $this->rsvp($event, 6)->assertSessionHasErrors('guests_count');
    }

    public function test_the_limit_counts_the_companions_listed_one_by_one(): void
    {
        $event = $this->event(1, collectCompanions: true);

        $this->rsvp($event, 3, extra: ['companions' => [['name' => 'João'], ['name' => 'Pedro']]])
            ->assertSessionHasErrors('guests_count');
        $this->assertSame(0, $event->guests()->count());

        $this->rsvp($event, 2, extra: ['companions' => [['name' => 'João']]])->assertSessionHasNoErrors();
    }

    public function test_the_page_tells_each_guest_their_limit(): void
    {
        $event = $this->event(2);
        $guest = $event->guests()->create(['name' => 'Maria', 'max_companions' => 5]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.rsvp_max_companions', 2)
            ->where('guest', null));

        $this->withCookie(Guest::cookieName($event), $guest->identifier)
            ->get("/{$event->slug}")
            ->assertInertia(fn ($page) => $page->where('guest.max_companions', 5));
    }
}
