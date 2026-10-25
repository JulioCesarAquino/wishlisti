<?php

namespace Tests\Feature\Guests;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each guest's personal invitation link, and the host's choice of only
 * accepting confirmations through it.
 */
class RsvpInviteLinkTest extends TestCase
{
    use RefreshDatabase;

    private function event(bool $inviteOnly = true, bool $premium = true): Event
    {
        $factory = Event::factory()->withRsvpSettings(['invite_only' => $inviteOnly]);

        return ($premium ? $factory->withFeatures(Feature::GuestList) : $factory)->create(['slug' => 'maju', 'is_published' => true]);
    }

    private function rsvp(Event $event, string $name, ?Guest $invited = null): mixed
    {
        $request = $invited ? $this->withCookie(Guest::inviteCookieName($event), $invited->invite_code) : $this;

        return $request->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => $name, 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 1,
        ]);
    }

    public function test_every_guest_gets_an_invite_code(): void
    {
        $event = $this->event();
        $guest = $event->guests()->create(['name' => 'Bia']);

        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $guest->invite_code);
        $this->assertSame(url("/maju/c/{$guest->invite_code}"), $guest->inviteUrl());
    }

    public function test_the_personal_link_opens_the_page_knowing_the_guest(): void
    {
        $event = $this->event();
        $guest = $event->guests()->create(['name' => 'Bia']);

        $this->get("/maju/c/{$guest->invite_code}")
            ->assertRedirect('/maju')
            ->assertCookie(Guest::cookieName($event), $guest->identifier)
            ->assertCookie(Guest::inviteCookieName($event), $guest->invite_code);
    }

    public function test_an_unknown_code_just_opens_the_page(): void
    {
        $event = $this->event();

        $this->get('/maju/c/naoexiste')
            ->assertRedirect('/maju')
            ->assertCookieMissing(Guest::inviteCookieName($event));
    }

    public function test_a_code_only_works_on_its_own_event(): void
    {
        $event = $this->event();
        $other = Event::factory()->create(['slug' => 'outro', 'is_published' => true]);
        $guest = $other->guests()->create(['name' => 'Bia']);

        $this->get("/maju/c/{$guest->invite_code}")->assertCookieMissing(Guest::inviteCookieName($event));
    }

    public function test_invite_only_turns_away_who_came_by_the_events_link(): void
    {
        $event = $this->event();

        $this->rsvp($event, 'Bia')->assertSessionHasErrors('guest.name');
        $this->assertSame(0, $event->guests()->count());
    }

    public function test_invite_only_accepts_who_opened_their_link(): void
    {
        $event = $this->event();
        $guest = $event->guests()->create(['name' => 'Bia Lima', 'whatsapp' => '11999999999']);

        $this->rsvp($event, 'Bia Lima', $guest)->assertSessionHasNoErrors();
        $this->assertSame(Guest::RSVP_CONFIRMED, $guest->fresh()->rsvp_status);
    }

    public function test_invite_only_doesnt_let_someone_else_answer_on_a_guests_link(): void
    {
        $event = $this->event();
        $guest = $event->guests()->create(['name' => 'Bia Lima']);

        $this->rsvp($event, 'Carlos', $guest)
            ->assertSessionHasErrors(['guest.name' => 'Este convite é de Bia Lima. Para confirmar outra pessoa, use o link do convite dela.']);
        $this->assertSame(1, $event->guests()->count());
        $this->assertNull($guest->fresh()->rsvp_status);
    }

    public function test_without_the_premium_invite_only_is_ignored(): void
    {
        $event = $this->event(premium: false);

        $this->assertFalse($event->rsvpIsInviteOnly());
        $this->rsvp($event, 'Bia')->assertSessionHasNoErrors();
    }

    public function test_the_page_tells_whether_the_guest_came_by_their_link(): void
    {
        $event = $this->event();
        $guest = $event->guests()->create(['name' => 'Bia']);

        $this->withCookie(Guest::cookieName($event), $guest->identifier)
            ->get('/maju')
            ->assertInertia(fn ($page) => $page
                ->where('event.rsvp_invite_only', true)
                ->where('guest.is_invited', false));

        $this->withCookie(Guest::cookieName($event), $guest->identifier)
            ->withCookie(Guest::inviteCookieName($event), $guest->invite_code)
            ->get('/maju')
            ->assertInertia(fn ($page) => $page->where('guest.is_invited', true));
    }
}
