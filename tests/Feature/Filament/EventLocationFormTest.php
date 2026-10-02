<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Events\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Events\Pages\EditEventLocation;
use App\Models\Events\Event;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventLocationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Repeater::fake();
    }

    public function test_an_address_is_required_to_create_an_event(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'type' => 'aniversario',
                'title' => 'Festa da Maria',
                'locations' => [['name' => 'Local do evento', 'address' => '']],
            ])
            ->call('create')
            ->assertHasFormErrors(['locations.0.address' => 'required']);

        $this->assertSame(0, Event::count());
    }

    public function test_an_event_needs_at_least_one_location(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'type' => 'aniversario',
                'title' => 'Festa da Maria',
                'locations' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['locations']);

        $this->assertSame(0, Event::count());
    }

    public function test_latitude_requires_longitude_and_vice_versa(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'type' => 'aniversario',
                'title' => 'Festa da Maria',
                'locations' => [[
                    'name' => 'Local do evento',
                    'address' => 'Rua das Flores, 123',
                    'latitude' => -23.5613,
                    'longitude' => '',
                ]],
            ])
            ->call('create')
            ->assertHasFormErrors(['locations.0.longitude' => 'required_with']);
    }

    public function test_an_event_can_be_created_with_a_full_location(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        Livewire::test(CreateEvent::class)
            ->fillForm([
                'type' => 'aniversario',
                'title' => 'Festa da Maria',
                'locations' => [[
                    'name' => 'Local do evento',
                    'address' => 'Rua das Flores, 123, São Paulo - SP',
                    'maps_url' => 'https://maps.app.goo.gl/abc',
                    'latitude' => -23.5613,
                    'longitude' => -46.6565,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $location = Event::first()->locations->sole();

        $this->assertSame('Rua das Flores, 123, São Paulo - SP', $location->address);
        $this->assertSame('https://maps.app.goo.gl/abc', $location->maps_url);
        $this->assertSame('local-do-evento', $location->slug);
        $this->assertEqualsWithDelta(-23.5613, $location->latitude, 0.0001);
        $this->assertEqualsWithDelta(-46.6565, $location->longitude, 0.0001);
    }

    public function test_a_host_can_have_several_locations_in_their_own_order(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withLocation()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(EditEventLocation::class, ['record' => $event->getRouteKey()])
            ->fillForm(['locations' => [
                ['name' => 'Cerimônia', 'address' => 'Igreja Matriz'],
                ['name' => 'Festa', 'slug' => 'a-festa', 'address' => 'Salão Azul'],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $locations = $event->fresh()->locations;

        $this->assertSame(['Cerimônia', 'Festa'], $locations->pluck('name')->all());
        $this->assertSame(['cerimonia', 'a-festa'], $locations->pluck('slug')->all());
    }

    public function test_two_locations_cannot_share_a_link(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withLocation()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(EditEventLocation::class, ['record' => $event->getRouteKey()])
            ->fillForm(['locations' => [
                ['name' => 'Cerimônia', 'slug' => 'local', 'address' => 'Igreja Matriz'],
                ['name' => 'Festa', 'slug' => 'local', 'address' => 'Salão Azul'],
            ]])
            ->call('save')
            ->assertHasFormErrors(['locations.0.slug', 'locations.1.slug']);
    }

    public function test_locations_with_the_same_name_get_distinct_links(): void
    {
        $event = Event::factory()->create();

        $first = $event->locations()->create(['name' => 'Festa', 'address' => 'Salão Azul']);
        $second = $event->locations()->create(['name' => 'Festa', 'address' => 'Salão Verde']);

        $this->assertSame('festa', $first->slug);
        $this->assertSame('festa-2', $second->slug);
    }
}
