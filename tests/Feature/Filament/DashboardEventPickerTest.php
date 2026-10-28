<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Guests\Guests\GuestResource;
use App\Filament\Resources\Guests\Guests\Pages\ListGuests;
use App\Filament\Resources\Guests\Guests\Widgets\ListGuestsOverviewWidget;
use App\Filament\Widgets\GuestsOverviewWidget;
use App\Filament\Widgets\OrdersOverviewWidget;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Number;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel's totals and the guest list about one event, or all of them,
 * opening on the user's default event.
 */
class DashboardEventPickerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $wedding;

    private Event $party;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->wedding = Event::factory()->create(['title' => 'Casamento']);
        $this->party = Event::factory()->create(['title' => 'Aniversário']);

        $this->confirmed($this->wedding, 'Leide', 2);
        $this->confirmed($this->party, 'Paulo', 5);

        $this->actingAs($this->admin);
    }

    private function confirmed(Event $event, string $name, int $people): Guest
    {
        return $event->guests()->create([
            'name' => $name,
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_guests_count' => $people,
            'rsvp_responded_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, string>
     */
    private function cards(string $widget, array $props = []): array
    {
        $instance = Livewire::test($widget, $props)->instance();
        $stats = (fn () => $this->getStats())->call($instance);

        return collect($stats)->mapWithKeys(fn (Stat $stat) => [(string) $stat->getLabel() => (string) $stat->getValue()])->all();
    }

    public function test_the_totals_follow_the_picked_event_or_all_of_them(): void
    {
        $this->assertSame('7', $this->cards(GuestsOverviewWidget::class)['Total de pessoas confirmadas']);
        $this->assertSame('2', $this->cards(GuestsOverviewWidget::class, ['pageFilters' => ['event_id' => $this->wedding->id]])['Total de pessoas confirmadas']);
        $this->assertSame('5', $this->cards(ListGuestsOverviewWidget::class, ['tableFilters' => ['event_id' => ['value' => $this->party->id]]])['Total de pessoas confirmadas']);

        Order::factory()->create(['event_id' => $this->wedding->id, 'guest_id' => $this->wedding->guests()->first()->id, 'status' => Order::STATUS_PAID, 'total_amount' => 100]);
        Order::factory()->create(['event_id' => $this->party->id, 'guest_id' => $this->party->guests()->first()->id, 'status' => Order::STATUS_PAID, 'total_amount' => 250]);

        $raised = fn (array $props = []): string => $this->cards(OrdersOverviewWidget::class, $props)['Total arrecadado'];

        $this->assertSame(Number::currency(350, in: 'BRL', locale: 'pt_BR'), $raised());
        $this->assertSame(Number::currency(100, in: 'BRL', locale: 'pt_BR'), $raised(['pageFilters' => ['event_id' => $this->wedding->id]]));
    }

    public function test_the_dashboard_opens_on_the_default_event(): void
    {
        Livewire::test(Dashboard::class)->assertSet('filters.event_id', null);

        $this->admin->forceFill(['default_event_id' => $this->party->id])->save();
        session()->flush();

        Livewire::test(Dashboard::class)
            ->assertSet('filters.event_id', $this->party->id)
            ->set('filters.event_id', null)
            ->assertSet('filters.event_id', null);
    }

    public function test_the_user_sets_their_default_event(): void
    {
        Livewire::test(Dashboard::class)
            ->callAction('defaultEvent', ['default_event_id' => $this->wedding->id])
            ->assertHasNoActionErrors()
            ->assertSet('filters.event_id', $this->wedding->id);

        $this->assertSame($this->wedding->id, $this->admin->fresh()->default_event_id);

        Livewire::test(Dashboard::class)
            ->callAction('defaultEvent', ['default_event_id' => null])
            ->assertSet('filters.event_id', null);

        $this->assertNull($this->admin->fresh()->default_event_id);
    }

    public function test_the_guest_list_opens_filtered_by_the_default_event(): void
    {
        $this->admin->forceFill(['default_event_id' => $this->wedding->id])->save();

        Livewire::test(ListGuests::class)
            ->assertCanSeeTableRecords($this->wedding->guests)
            ->assertCanNotSeeTableRecords($this->party->guests)
            ->filterTable('event_id', null)
            ->assertCanSeeTableRecords(Guest::all());
    }

    public function test_a_host_only_picks_their_own_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $own = Event::factory()->create(['user_id' => $host->id, 'title' => 'Meu evento']);
        $this->confirmed($own, 'Bia', 3);
        $host->forceFill(['default_event_id' => $this->wedding->id])->save();

        $this->actingAs($host);

        Livewire::test(Dashboard::class)->assertSet('filters.event_id', null);

        // Someone else's event in the URL counts as none: their own only.
        $this->assertSame('3', $this->cards(GuestsOverviewWidget::class, ['pageFilters' => ['event_id' => $this->wedding->id]])['Total de pessoas confirmadas']);
    }

    /**
     * As the browser does: every component on the page in one request, the
     * widgets taking the page's reactive properties. A widget expecting a
     * table the page doesn't have broke all of them (a TypeError, which
     * Livewire turns into a 419, "page expired").
     */
    public function test_the_pages_requests_go_through_with_their_widgets(): void
    {
        foreach (['/admin?filters[event_id]='.$this->wedding->id, GuestResource::getUrl()] as $url) {
            preg_match_all('/wire:snapshot="([^"]+)"/', $this->get($url)->assertOk()->getContent(), $matches);

            $this->postJson('/livewire/update', [
                'components' => array_map(fn (string $snapshot) => [
                    'snapshot' => html_entity_decode($snapshot, ENT_QUOTES),
                    'updates' => (object) [],
                    'calls' => [],
                ], $matches[1]),
            ], ['X-Livewire' => 'true'])->assertOk();
        }
    }
}
