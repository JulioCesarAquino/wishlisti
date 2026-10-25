<?php

namespace Tests\Feature\Events;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\EditEventAppearance;
use App\Models\Events\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The invitation that comes already written when the event is shared on
 * WhatsApp.
 */
class EventShareMessageTest extends TestCase
{
    use RefreshDatabase;

    private function premiumEvent(array $attributes = []): Event
    {
        return Event::factory()->withFeatures(Feature::GuestList)->create(['slug' => 'maju', ...$attributes]);
    }

    public function test_without_a_message_it_is_the_title_and_the_link(): void
    {
        $event = Event::factory()->create(['title' => 'Maria Julia', 'slug' => 'maju']);

        $this->assertSame('Maria Julia — '.url('/maju'), $event->shareText());
    }

    public function test_the_link_goes_where_the_host_put_it(): void
    {
        $event = $this->premiumEvent([
            'share_message' => "Oi, tudo bem?\n\nConfirme na aba *Confirmar presença*:\n👉 {link}\n\nBeijos!",
        ]);

        $this->assertSame(
            "Oi, tudo bem?\n\nConfirme na aba *Confirmar presença*:\n👉 ".url('/maju')."\n\nBeijos!",
            $event->shareText(),
        );
    }

    public function test_without_the_placeholder_the_link_goes_at_the_end(): void
    {
        $event = $this->premiumEvent(['share_message' => 'Vem comemorar com a gente! 🎈']);

        $this->assertSame("Vem comemorar com a gente! 🎈\n\n".url('/maju'), $event->shareText());
    }

    public function test_the_public_page_shares_the_message_on_whatsapp(): void
    {
        $event = $this->premiumEvent(['is_published' => true, 'share_message' => 'Oi! {link}']);

        $this->get('/maju')->assertInertia(fn ($page) => $page
            ->where('event.whatsapp_share_url', 'https://wa.me/?text='.rawurlencode('Oi! '.url('/maju'))));
    }

    public function test_the_host_writes_the_message_and_invites_from_the_panel(): void
    {
        $event = $this->premiumEvent();
        $this->actingAs($event->user);

        Livewire::test(EditEventAppearance::class, ['record' => $event->getRouteKey()])
            ->fillForm(['share_message' => 'Oi! {link}'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionHasUrl('shareOnWhatsapp', $event->refresh()->whatsappShareUrl());

        $this->assertSame('Oi! {link}', $event->share_message);
    }

    public function test_the_rsvp_link_opens_the_rsvp_tab_and_nothing_is_added_at_the_end(): void
    {
        $event = $this->premiumEvent(['share_message' => "Confirma lá:\n👉 {link_confirmacao}"]);

        $this->assertSame("Confirma lá:\n👉 ".url('/maju').'#confirmar-presenca', $event->shareText());
    }

    public function test_the_location_link_opens_the_location_tab(): void
    {
        $event = $this->premiumEvent(['share_message' => "Confirme: {link_confirmacao}\nComo chegar: {link_localizacao}"]);
        $event->locations()->create(['name' => 'Buffet', 'address' => 'Rua das Flores, 100']);

        $this->assertSame(
            'Confirme: '.url('/maju').'#confirmar-presenca'."\nComo chegar: ".url('/maju/localizacao'),
            $event->fresh()->shareText(),
        );
    }

    public function test_the_gifts_and_guestbook_links_open_their_tabs(): void
    {
        $event = Event::factory()->withFeatures(Feature::GuestList, Feature::Guestbook)
            ->create(['slug' => 'maju', 'share_message' => "{link_presentes}\n{link_recados}"]);

        $this->assertSame(
            url('/maju').'#presentes'."\n".url('/maju').'#recados',
            $event->shareText(),
        );
    }

    public function test_without_locations_the_location_link_is_the_invitations(): void
    {
        $event = $this->premiumEvent(['share_message' => 'Como chegar: {link_localizacao}']);

        $this->assertSame('Como chegar: '.url('/maju'), $event->shareText());
    }

    public function test_the_name_is_the_guests_first_name_or_left_out(): void
    {
        $event = $this->premiumEvent(['share_message' => "Oi, {nome}! Tudo bem?\nOii {nome}, vem? {link}"]);
        $guest = $event->guests()->create(['name' => 'Beatriz Lima']);

        $this->assertSame("Oi, Beatriz! Tudo bem?\nOii Beatriz, vem? ".url("/maju/c/{$guest->invite_code}"), $event->shareText($guest));
        $this->assertSame("Oi! Tudo bem?\nOii, vem? ".url('/maju'), $event->shareText());
    }

    public function test_inviting_a_guest_opens_their_chat(): void
    {
        $event = $this->premiumEvent(['share_message' => 'Oi, {nome}! {link}']);
        $guest = $event->guests()->create(['name' => 'Bia', 'whatsapp' => '(19) 98222-0002']);

        $this->assertSame(
            'https://wa.me/5519982220002?text='.rawurlencode('Oi, Bia! '.url("/maju/c/{$guest->invite_code}")),
            $event->whatsappShareUrl($guest),
        );
    }

    public function test_a_guests_tab_links_go_through_their_personal_link(): void
    {
        $event = $this->premiumEvent(['share_message' => 'Confirma: {link_confirmacao}']);
        $guest = $event->guests()->create(['name' => 'Bia']);

        $this->assertSame('Confirma: '.url("/maju/c/{$guest->invite_code}").'#confirmar-presenca', $event->shareText($guest));
        $this->assertSame('Confirma: '.url('/maju').'#confirmar-presenca', $event->shareText());
    }

    public function test_without_a_message_a_guest_still_gets_their_personal_link(): void
    {
        $event = Event::factory()->create(['title' => 'Maria Julia', 'slug' => 'maju']);
        $guest = $event->guests()->create(['name' => 'Bia']);

        $this->assertSame('Maria Julia — '.url("/maju/c/{$guest->invite_code}"), $event->shareText($guest));
    }

    public function test_without_the_guest_list_premium_the_message_isnt_used(): void
    {
        $event = Event::factory()->create(['title' => 'Maria Julia', 'slug' => 'maju', 'share_message' => 'Oi! {link}']);

        $this->assertSame('Maria Julia — '.url('/maju'), $event->shareText());
    }

    public function test_the_field_suggests_the_variables(): void
    {
        $event = $this->premiumEvent();
        $this->actingAs($event->user);

        Livewire::test(EditEventAppearance::class, ['record' => $event->getRouteKey()])
            ->assertSeeHtml('wl-vars-menu')
            ->assertSee(['{link_confirmacao}', '{link_recados}', 'Primeiro nome do convidado'], escape: false);
    }
}
