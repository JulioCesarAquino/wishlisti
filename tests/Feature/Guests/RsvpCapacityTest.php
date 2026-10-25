<?php

namespace Tests\Feature\Guests;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Events\EventRsvpSetting;
use App\Models\Guests\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * How many people fit in the event, guests and companions together — and
 * the children under the age limit, who may not take a place.
 */
class RsvpCapacityTest extends TestCase
{
    use RefreshDatabase;

    private function event(int $capacity, array $settings = []): Event
    {
        return Event::factory()
            ->withRsvpSettings(['capacity' => $capacity, ...$settings])
            ->create(['is_published' => true]);
    }

    private function rsvp(Event $event, string $name, string $whatsapp, int $guestsCount, array $extra = []): mixed
    {
        return $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => $name, 'whatsapp' => $whatsapp],
            'attending' => true,
            'guests_count' => $guestsCount,
            ...$extra,
        ]);
    }

    public function test_confirmations_stop_when_the_places_run_out(): void
    {
        $event = $this->event(5);

        $this->rsvp($event, 'Maria', '11999999991', 3)->assertSessionHasNoErrors();
        $this->rsvp($event, 'Ana', '11999999992', 3)->assertSessionHasErrors(['guests_count' => 'Restam só 2 vagas no evento.']);
        $this->assertNull($event->guests()->firstWhere('name', 'Ana'));

        $this->rsvp($event, 'Ana', '11999999992', 2)->assertSessionHasNoErrors();
        $this->assertSame(0, $event->fresh()->rsvpPlacesLeft());

        $this->rsvp($event, 'João', '11999999993', 1)->assertSessionHasErrors(['guests_count' => 'As vagas do evento acabaram. Fale com os anfitriões.']);
    }

    public function test_who_confirmed_can_change_their_answer_within_their_own_places(): void
    {
        $event = $this->event(5);

        $this->rsvp($event, 'Maria', '11999999991', 3);
        $this->rsvp($event, 'Ana', '11999999992', 2);

        $this->rsvp($event, 'Maria', '11999999991', 3)->assertSessionHasNoErrors();
        $this->rsvp($event, 'Maria', '11999999991', 4)->assertSessionHasErrors('guests_count');
        $this->rsvp($event, 'Maria', '11999999991', 2)->assertSessionHasNoErrors();
        $this->assertSame(1, $event->fresh()->rsvpPlacesLeft());
    }

    public function test_declining_frees_places_and_is_never_refused(): void
    {
        $event = $this->event(2);
        $this->rsvp($event, 'Maria', '11999999991', 2);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999992'],
            'attending' => false,
        ])->assertSessionHasNoErrors();

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999991'],
            'attending' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $event->fresh()->rsvpPlacesLeft());
    }

    public function test_lowering_the_capacity_doesnt_turn_away_who_already_confirmed(): void
    {
        $event = $this->event(10);
        $this->rsvp($event, 'Maria', '11999999991', 6);

        $event->rsvpSettings->update(['capacity' => 4]);
        $event->unsetRelation('rsvpSettings');

        $this->assertSame(0, $event->rsvpPlacesLeft());
        $this->rsvp($event, 'Maria', '11999999991', 6)->assertSessionHasNoErrors();
        $this->rsvp($event, 'Maria', '11999999991', 5)->assertSessionHasNoErrors();
        $this->rsvp($event, 'Maria', '11999999991', 6)->assertSessionHasErrors('guests_count');
    }

    public function test_children_under_the_age_limit_may_not_take_a_place(): void
    {
        $event = $this->event(3, ['child_age_limit' => 6, 'capacity_excludes_children' => true]);

        $this->rsvp($event, 'Maria', '11999999991', 4, ['children_count' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, $event->fresh()->rsvpTakenPlaces());

        $this->rsvp($event, 'Ana', '11999999992', 2, ['children_count' => 0])
            ->assertSessionHasErrors(['guests_count' => 'Resta só 1 vaga no evento (crianças com menos de 6 anos não contam).']);
        $this->rsvp($event, 'Ana', '11999999992', 3, ['children_count' => 2])->assertSessionHasNoErrors();
    }

    public function test_children_count_when_the_host_doesnt_exclude_them(): void
    {
        $event = $this->event(3, ['child_age_limit' => 6]);

        $this->rsvp($event, 'Maria', '11999999991', 4, ['children_count' => 2])->assertSessionHasErrors('guests_count');
    }

    public function test_without_an_age_limit_children_always_count(): void
    {
        $event = $this->event(3, ['capacity_excludes_children' => true]);

        $this->assertFalse($event->capacityExcludesChildren());
    }

    public function test_children_listed_one_by_one_are_told_by_their_age(): void
    {
        $event = Event::factory()
            ->withFeatures(Feature::GuestList)
            ->withRsvpSettings([
                'capacity' => 2,
                'child_age_limit' => 6,
                'capacity_excludes_children' => true,
                'collect_companions' => true,
                'companion_fields' => [...EventRsvpSetting::DEFAULT_COMPANION_FIELDS, 'age' => EventRsvpSetting::FIELD_OPTIONAL],
            ])
            ->create(['is_published' => true]);

        $this->rsvp($event, 'Maria', '11999999991', 3, ['companions' => [['name' => 'João', 'age' => 30], ['name' => 'Bebê', 'age' => 1]]])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $event->fresh()->rsvpTakenPlaces());
    }

    public function test_the_page_and_the_panel_tell_the_places(): void
    {
        $event = $this->event(10);
        $this->rsvp($event, 'Maria', '11999999991', 3);
        $guest = $event->guests()->firstWhere('name', 'Maria');

        $this->withCookie(Guest::cookieName($event), $guest->identifier)
            ->get("/{$event->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('event.rsvp_places_left', 7)
                ->where('guest.rsvp_places', 3));
    }

    public function test_without_a_capacity_nothing_is_limited(): void
    {
        $event = Event::factory()->create(['is_published' => true]);

        $this->assertNull($event->rsvpPlacesLeft());
        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page->where('event.rsvp_places_left', null));
    }
}
