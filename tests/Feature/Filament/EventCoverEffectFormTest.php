<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Events\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Events\Pages\EditEventAppearance;
use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventCoverEffectFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_effect_intensity_defaults_to_the_maximum(): void
    {
        $host = User::factory()->create(['is_admin' => false]);

        $this->actingAs($host);

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'type' => 'aniversario',
                'title' => 'Festa da Maria',
                'address' => 'Rua das Flores, 123',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(100, Event::first()->appearance->cover_effect_intensity);
    }

    public function test_cover_effect_intensity_can_be_lowered_to_disable_the_effect(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(EditEventAppearance::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['appearance.cover_effect_intensity' => 100])
            ->fillForm(['appearance.cover_effect_intensity' => 0])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $event->fresh()->appearance->cover_effect_intensity);
    }
}
