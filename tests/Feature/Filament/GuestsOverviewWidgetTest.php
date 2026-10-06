<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\GuestsOverviewWidget;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The cards over the guest list count the same guests as the list.
 */
class GuestsOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function confirmed(Event $event, string $name, int $people): Guest
    {
        return $event->guests()->create([
            'name' => $name,
            'identifier' => (string) Str::uuid(),
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_guests_count' => $people,
            'rsvp_responded_at' => now(),
        ]);
    }

    /**
     * @return array<string, string> card label => value
     */
    private function cards(): array
    {
        $widget = Livewire::test(GuestsOverviewWidget::class)->instance();
        $stats = (fn () => $this->getStats())->call($widget);

        return collect($stats)->mapWithKeys(fn (Stat $stat) => [(string) $stat->getLabel() => (string) $stat->getValue()])->all();
    }

    public function test_guests_of_an_event_in_the_trash_are_not_counted(): void
    {
        $wedding = Event::factory()->create();
        $this->confirmed($wedding, 'Leide', 2);
        $this->confirmed($wedding, 'Paulo', 1);

        $trashed = Event::factory()->create();
        $this->confirmed($trashed, 'Teste', 3);
        $trashed->delete();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $cards = $this->cards();

        $this->assertSame('2', $cards['Confirmaram presença']);
        $this->assertSame('3', $cards['Total de pessoas confirmadas']);
    }

    public function test_a_host_only_counts_their_own_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $this->confirmed(Event::factory()->create(['user_id' => $host->id]), 'Leide', 2);
        $this->confirmed(Event::factory()->create(), 'De outro anfitrião', 5);

        $this->actingAs($host);

        $cards = $this->cards();

        $this->assertSame('1', $cards['Confirmaram presença']);
        $this->assertSame('2', $cards['Total de pessoas confirmadas']);
    }
}
