<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Events\Events\Pages\ManageEventGuests;
use App\Models\Events\Event;
use App\Models\Events\EventLocation;
use App\Models\Guests\Guest;
use App\Services\Guests\GuestListPdfService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The host managing the guest list by hand: a pre-list, fixes, presences
 * confirmed over the phone, the same person joined into one record and
 * the list printed.
 */
class ManageEventGuestsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['title' => 'Casamento da Ana e do Bruno', 'event_date' => '2026-11-14', 'event_time' => '16:00']);
        $this->actingAs($this->event->user);
    }

    private function page(): mixed
    {
        return Livewire::test(ManageEventGuests::class, ['record' => $this->event->getRouteKey()]);
    }

    public function test_the_host_adds_a_guest_without_an_answer(): void
    {
        $this->page()
            ->callAction(TestAction::make('create')->table(), ['name' => 'Tia Rosa', 'whatsapp' => '11977777777'])
            ->assertHasNoFormErrors();

        $guest = $this->event->guests()->firstWhere('name', 'Tia Rosa');

        $this->assertNull($guest->rsvp_status);
        $this->assertNull($guest->rsvp_guests_count);
        $this->assertSame(0, $this->event->rsvpHeadcount()['people']);
    }

    public function test_the_host_adds_someone_already_confirmed(): void
    {
        $this->page()
            ->callAction(TestAction::make('create')->table(), [
                'name' => 'Tio João',
                'rsvp_status' => Guest::RSVP_CONFIRMED,
                'rsvp_guests_count' => 3,
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(3, $this->event->rsvpHeadcount()['people']);
    }

    public function test_the_host_edits_a_guest(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Rosa', 'whatsapp' => '11977777777']);

        $this->page()
            ->callAction(TestAction::make('edit')->table($guest), [
                'name' => 'Tia Rosa',
                'whatsapp' => '11977777777',
                'email' => 'rosa@example.com',
                'rsvp_status' => Guest::RSVP_DECLINED,
            ])
            ->assertHasNoFormErrors();

        $guest->refresh();
        $this->assertSame('Tia Rosa', $guest->name);
        $this->assertSame('rosa@example.com', $guest->email);
        $this->assertSame(Guest::RSVP_DECLINED, $guest->rsvp_status);
    }

    public function test_the_host_confirms_a_guest_and_the_companions_follow(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Rosa', 'rsvp_status' => Guest::RSVP_DECLINED]);
        $companion = $this->event->guests()->create(['name' => 'Lia', 'companion_of_guest_id' => $guest->id, 'rsvp_status' => Guest::RSVP_DECLINED]);

        $this->page()->callAction(TestAction::make('confirm')->table($guest));

        $this->assertSame(Guest::RSVP_CONFIRMED, $guest->refresh()->rsvp_status);
        $this->assertSame(2, $guest->rsvp_guests_count);
        $this->assertSame(Guest::RSVP_CONFIRMED, $companion->refresh()->rsvp_status);
    }

    public function test_the_host_merges_the_same_person(): void
    {
        $father = $this->event->guests()->create(['name' => 'Carlos', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 2, 'rsvp_responded_at' => now()]);
        $listedSon = $this->event->guests()->create(['name' => 'Pedro', 'companion_of_guest_id' => $father->id, 'rsvp_status' => Guest::RSVP_CONFIRMED]);
        $son = $this->event->guests()->create(['name' => 'Pedro Henrique', 'whatsapp' => '11922222222', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 1, 'rsvp_responded_at' => now()]);

        $this->page()
            ->callAction(TestAction::make('merge')->table($listedSon), ['other_guest_id' => $son->id])
            ->assertHasNoFormErrors();

        $this->assertNull(Guest::withTrashed()->find($listedSon->id));
        $this->assertSame(2, $this->event->rsvpHeadcount()['people']);
    }

    public function test_the_list_is_printed_as_a_pdf(): void
    {
        EventLocation::factory()->create(['event_id' => $this->event->id, 'name' => 'Espaço Jardim', 'address' => 'Rua das Flores, 100']);

        $host = $this->event->guests()->create(['name' => 'Carlos', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 3, 'rsvp_responded_at' => now()]);
        $this->event->guests()->create(['name' => 'Pedro', 'companion_of_guest_id' => $host->id, 'rsvp_status' => Guest::RSVP_CONFIRMED]);
        $this->event->guests()->create(['name' => 'Tia Rosa']);

        $this->page()
            ->callAction(TestAction::make('pdf')->table(), ['include_declined' => true, 'show_whatsapp' => false])
            ->assertFileDownloaded('convidados-'.$this->event->slug.'.pdf');

        $html = view('pdf.guest-list', $this->viewData())->render();

        $this->assertStringContainsString('Casamento da Ana e do Bruno', $html);
        $this->assertStringContainsString('Sábado, 14 de novembro de 2026 · 16h', $html);
        $this->assertStringContainsString('Espaço Jardim', $html);
        $this->assertStringContainsString('+ 1 pessoa', $html, 'the one Carlos counted without naming');
        $this->assertStringContainsString('3</strong> pessoas confirmadas', $html);
        $this->assertStringContainsString('1 sem resposta', $html);
    }

    /**
     * What the PDF service hands the view, rendered as HTML so it can be
     * read back.
     *
     * @return array<string, mixed>
     */
    private function viewData(): array
    {
        $captured = [];

        view()->composer('pdf.guest-list', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        app(GuestListPdfService::class)->render($this->event->fresh());

        return $captured;
    }
}
