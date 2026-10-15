<?php

namespace Tests\Feature\Events;

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

    public function test_without_a_message_it_is_the_title_and_the_link(): void
    {
        $event = Event::factory()->create(['title' => 'Maria Julia', 'slug' => 'maju']);

        $this->assertSame('Maria Julia — '.url('/maju'), $event->shareText());
    }

    public function test_the_link_goes_where_the_host_put_it(): void
    {
        $event = Event::factory()->create([
            'slug' => 'maju',
            'share_message' => "Oi, tudo bem?\n\nConfirme na aba *Confirmar presença*:\n👉 {link}\n\nBeijos!",
        ]);

        $this->assertSame(
            "Oi, tudo bem?\n\nConfirme na aba *Confirmar presença*:\n👉 ".url('/maju')."\n\nBeijos!",
            $event->shareText(),
        );
    }

    public function test_without_the_placeholder_the_link_goes_at_the_end(): void
    {
        $event = Event::factory()->create(['slug' => 'maju', 'share_message' => 'Vem comemorar com a gente! 🎈']);

        $this->assertSame("Vem comemorar com a gente! 🎈\n\n".url('/maju'), $event->shareText());
    }

    public function test_the_public_page_shares_the_message_on_whatsapp(): void
    {
        $event = Event::factory()->create(['is_published' => true, 'slug' => 'maju', 'share_message' => 'Oi! {link}']);

        $this->get('/maju')->assertInertia(fn ($page) => $page
            ->where('event.whatsapp_share_url', 'https://wa.me/?text='.rawurlencode('Oi! '.url('/maju'))));
    }

    public function test_the_host_writes_the_message_and_invites_from_the_panel(): void
    {
        $event = Event::factory()->create();
        $this->actingAs($event->user);

        Livewire::test(EditEventAppearance::class, ['record' => $event->getRouteKey()])
            ->fillForm(['share_message' => 'Oi! {link}'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertActionHasUrl('shareOnWhatsapp', $event->refresh()->whatsappShareUrl());

        $this->assertSame('Oi! {link}', $event->share_message);
    }
}
