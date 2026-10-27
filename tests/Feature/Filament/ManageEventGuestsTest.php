<?php

namespace Tests\Feature\Filament;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\ManageEventGuests;
use App\Models\Events\Event;
use App\Models\Events\EventLocation;
use App\Models\Guests\Guest;
use App\Services\Guests\GuestListPdfService;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
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

        $this->event = Event::factory()->withFeatures(Feature::GuestList)->withRsvpSettings(['child_age_limit' => 10])->create(['title' => 'Casamento da Ana e do Bruno', 'event_date' => '2026-11-14', 'event_time' => '16:00']);
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

    public function test_the_host_names_the_companions_of_a_headcount_only_answer(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Carlos', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 4, 'rsvp_children_count' => 1, 'rsvp_responded_at' => now()]);
        $preListed = $this->event->guests()->create(['name' => 'Marina', 'whatsapp' => '19911112222']);

        $this->page()
            ->callAction(TestAction::make('companions')->table($guest), [
                'companions' => [
                    ['name' => 'Pedro', 'age' => 8],
                    ['name' => 'Marina Almeida', 'whatsapp' => '(19) 91111-2222'],
                ],
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(2, $guest->companions()->count());
        $this->assertSame($guest->id, $preListed->refresh()->companion_of_guest_id, 'the pre-listed guest is reused');
        $this->assertSame('Marina Almeida', $preListed->name);
        $this->assertSame(Guest::RSVP_CONFIRMED, $preListed->rsvp_status);
        $this->assertSame(4, $guest->refresh()->rsvp_guests_count, 'the one left unnamed still counts');
        $this->assertSame(['people' => 4, 'children' => 1], $this->event->rsvpHeadcount(), 'Pedro is the child the guest already counted');

        // Taking someone off the list leaves them unnamed again.
        $this->page()
            ->callAction(TestAction::make('companions')->table($guest), [
                'companions' => [['id' => $preListed->id, 'name' => 'Marina Almeida', 'whatsapp' => '19911112222']],
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(1, $guest->companions()->count());
        $this->assertNull(Guest::firstWhere('name', 'Pedro'));
        $this->assertSame(4, $guest->refresh()->rsvp_guests_count);
    }

    public function test_the_host_invites_a_guest_by_their_first_name(): void
    {
        $this->event->update(['share_message' => 'Oi, {nome}! {link_confirmacao}']);
        $guest = $this->event->guests()->create(['name' => 'Beatriz Lima', 'whatsapp' => '19982220002']);

        $this->page()->assertActionHasUrl(
            TestAction::make('invite')->table($guest),
            'https://wa.me/5519982220002?text='.rawurlencode('Oi, Beatriz! '.url("/{$this->event->slug}/c/{$guest->invite_code}").'#confirmar-presenca'),
        );
    }

    public function test_the_host_marks_that_a_guest_wont_come_and_keeps_them_on_the_list(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Tio João', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 3, 'rsvp_responded_at' => now()]);
        $companion = $this->event->guests()->create(['name' => 'Tia Rosa', 'companion_of_guest_id' => $guest->id, 'rsvp_status' => Guest::RSVP_CONFIRMED]);

        $this->page()
            ->assertActionVisible(TestAction::make('decline')->table($guest))
            ->callAction(TestAction::make('decline')->table($guest));

        $this->assertSame(Guest::RSVP_DECLINED, $guest->refresh()->rsvp_status);
        $this->assertNull($guest->rsvp_guests_count);
        $this->assertSame(Guest::RSVP_DECLINED, $companion->refresh()->rsvp_status);
        $this->assertFalse($guest->trashed());
        $this->assertSame(0, $this->event->rsvpHeadcount()['people']);

        $this->page()->assertActionHidden(TestAction::make('decline')->table($guest));
    }

    public function test_a_companion_who_wont_come_leaves_the_party_and_stays_on_the_list(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Tio João', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 3, 'rsvp_children_count' => 3, 'rsvp_responded_at' => now()]);
        $companion = $this->event->guests()->create(['name' => 'Tia Rosa', 'companion_of_guest_id' => $guest->id, 'rsvp_status' => Guest::RSVP_CONFIRMED]);

        $this->page()->callAction(TestAction::make('decline')->table($companion));

        $companion->refresh();
        $this->assertSame(Guest::RSVP_DECLINED, $companion->rsvp_status);
        $this->assertNull($companion->companion_of_guest_id);
        $this->assertFalse($companion->trashed());

        $guest->refresh();
        $this->assertSame(Guest::RSVP_CONFIRMED, $guest->rsvp_status);
        $this->assertSame(2, $guest->rsvp_guests_count);
        $this->assertSame(2, $guest->rsvp_children_count);
        $this->assertSame(2, $this->event->rsvpHeadcount()['people']);
    }

    public function test_the_host_marks_several_guests_that_wont_come(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Tio João', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 2, 'rsvp_responded_at' => now()]);
        $companion = $this->event->guests()->create(['name' => 'Tia Rosa', 'companion_of_guest_id' => $guest->id, 'rsvp_status' => Guest::RSVP_CONFIRMED]);
        $other = $this->event->guests()->create(['name' => 'Bia']);

        $this->page()
            ->selectTableRecords([$guest, $companion, $other])
            ->callAction(TestAction::make('decline')->table()->bulk());

        foreach ([$guest, $companion, $other] as $record) {
            $this->assertSame(Guest::RSVP_DECLINED, $record->refresh()->rsvp_status);
        }
        $this->assertSame(0, $this->event->rsvpHeadcount()['people']);
    }

    public function test_the_host_gives_a_guest_a_companion_limit_of_their_own(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Tia Rosa']);

        $this->page()
            ->callAction(TestAction::make('edit')->table($guest), ['name' => 'Tia Rosa', 'max_companions' => 6])
            ->assertHasNoFormErrors();

        $this->assertSame(6, $guest->refresh()->max_companions);
        $this->assertSame(6, $guest->allowedCompanions());

        $this->page()
            ->callAction(TestAction::make('edit')->table($guest), ['name' => 'Tia Rosa', 'max_companions' => null])
            ->assertHasNoFormErrors();

        $this->assertNull($guest->refresh()->max_companions);
        $this->assertSame(Guest::MAX_PARTY_SIZE - 1, $guest->allowedCompanions());
    }

    public function test_the_host_copies_a_guests_invitation_link(): void
    {
        $guest = $this->event->guests()->create(['name' => 'Beatriz Lima']);

        $this->page()
            ->mountAction(TestAction::make('invite_link')->table($guest))
            ->assertSchemaStateSet(['url' => url("/{$this->event->slug}/c/{$guest->invite_code}")]);
    }

    public function test_merging_and_naming_companions_are_premium(): void
    {
        $event = Event::factory()->create();
        $this->actingAs($event->user);

        $guest = $event->guests()->create(['name' => 'Carlos', 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 2, 'rsvp_responded_at' => now()]);
        $other = $event->guests()->create(['name' => 'Carlos A.']);

        Livewire::test(ManageEventGuests::class, ['record' => $event->getRouteKey()])
            ->assertActionHasIcon(TestAction::make('merge')->table($guest), Heroicon::OutlinedLockClosed)
            ->assertActionHasIcon(TestAction::make('companions')->table($guest), Heroicon::OutlinedLockClosed)
            ->mountAction(TestAction::make('merge')->table($guest))
            ->assertMountedActionModalSee('Recurso premium: Lista nominal de convidados')
            ->assertMountedActionModalDontSee('É a mesma pessoa que');

        $this->assertNotNull($other->fresh());
        $this->assertSame(0, $guest->companions()->count());
    }
}
