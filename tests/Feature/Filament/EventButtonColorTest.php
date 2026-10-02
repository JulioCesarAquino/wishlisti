<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Events\Events\Pages\EditEventAppearance;
use App\Models\Events\Event;
use App\Models\Events\EventAppearance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventButtonColorTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_without_a_button_color_get_the_lively_default(): void
    {
        $event = Event::factory()->withAppearance(['secondary_color' => '#8a9a7e'])->create(['is_published' => true]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.button_color', EventAppearance::DEFAULT_BUTTON_COLOR)
            ->where('event.secondary_color', '#8a9a7e'));
    }

    public function test_the_button_color_is_apart_from_the_secondary_color(): void
    {
        $event = Event::factory()->withAppearance([
            'secondary_color' => '#e8d5c4',
            'button_color' => '#8b2343',
        ])->create(['is_published' => true]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.button_color', '#8b2343')
            ->where('event.secondary_color', '#e8d5c4'));
    }

    public function test_a_ready_made_color_fills_the_button_color(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(EditEventAppearance::class, ['record' => $event->getRouteKey()])
            ->fillForm(['appearance.button_color_preset' => '#2b6cb0'])
            ->assertFormSet(['appearance.button_color' => '#2b6cb0'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('#2b6cb0', $event->fresh()->appearance->button_color);
    }

    public function test_any_color_from_the_picker_can_be_saved(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withAppearance(['button_color' => '#123456'])->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(EditEventAppearance::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['appearance.button_color_preset' => null])
            ->fillForm(['appearance.button_color' => '#7fa190'])
            ->assertFormSet(['appearance.button_color_preset' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('#7fa190', $event->fresh()->appearance->button_color);
    }
}
