<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Audit\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Events\Events\Pages\EditEvent;
use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dates are stored in UTC; the panel shows them in Brasília time — but the
 * event's time, typed in Brasília time, is kept as typed.
 */
class PanelTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_audit_shows_brasilia_time(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // 15:07 UTC is 12:07 in Brasília.
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(15, 7));
        Event::factory()->create(['title' => 'Casamento Leide & Rafael']);

        $this->actingAs($admin);

        Livewire::test(ListActivityLogs::class)
            ->assertSee('06/10/2026 12:07')
            ->assertDontSee('06/10/2026 15:07');
    }

    public function test_the_event_time_is_kept_as_typed(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id, 'event_date' => '2026-10-30']);

        $this->actingAs($host);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['event_time' => '16:00'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertStringStartsWith('16:00', $event->fresh()->event_time);
        $this->assertSame('2026-10-30T16:00:00-03:00', $event->fresh()->startsAt()->toIso8601String());
    }
}
