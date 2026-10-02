<?php

namespace Tests\Feature\Events;

use App\Enums\Events\PageSection;
use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\EditEventSections;
use App\Models\Events\Event;
use App\Models\Events\EventSection;
use App\Models\Guests\GuestMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventSectionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, bool>  $active  by type value; the rest stay on
     * @param  list<string>|null  $order  type values, in order
     */
    private function configure(Event $event, array $active = [], ?array $order = null): void
    {
        $event->ensureSections();

        foreach ($event->sections()->get() as $section) {
            $section->update([
                'is_active' => $active[$section->type->value] ?? true,
                'position' => $order ? array_search($section->type->value, $order, true) : $section->position,
            ]);
        }

        $event->unsetRelation('sections');
    }

    public function test_events_nobody_configured_show_every_tab_with_content_in_the_default_order(): void
    {
        $event = Event::factory()->withLocation()->create(['is_published' => true]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page->where('event.sections', [
            ['key' => 'inicio', 'label' => 'Início'],
            ['key' => 'galeria', 'label' => 'Galeria'],
            ['key' => 'presentes', 'label' => 'Presentes'],
            ['key' => 'confirmar-presenca', 'label' => 'Confirmar presença'],
            ['key' => 'localizacao', 'label' => 'Localização'],
        ]));
    }

    public function test_the_menu_follows_the_hosts_choice_and_order(): void
    {
        $event = Event::factory()->withLocation()->withFeatures(Feature::Guestbook)->create(['is_published' => true]);

        $this->configure(
            $event,
            active: ['galeria' => false],
            order: ['inicio', 'localizacao', 'confirmar_presenca', 'recado', 'presentes', 'galeria'],
        );

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.sections.0.key', 'inicio')
            ->where('event.sections.1.key', 'localizacao')
            ->where('event.sections.2.key', 'confirmar-presenca')
            ->where('event.sections.3.key', 'recados')
            ->where('event.sections.4.key', 'presentes')
            ->has('event.sections', 5));
    }

    public function test_the_home_tab_cannot_be_turned_off(): void
    {
        $event = Event::factory()->create();
        $event->ensureSections();

        $home = $event->sections()->get()->first(fn (EventSection $section) => $section->type === PageSection::Home);
        $home->update(['is_active' => false]);

        $this->assertTrue($home->fresh()->is_active);
        $this->assertTrue($event->fresh()->showsSection(PageSection::Home));
    }

    public function test_a_turned_off_location_tab_is_not_found_even_from_a_shared_link(): void
    {
        $event = Event::factory()->withLocation(['name' => 'Festa'])->create(['is_published' => true]);

        $this->get("/{$event->slug}/localizacao/festa")->assertOk();

        $this->configure($event, active: ['localizacao' => false]);

        $this->get("/{$event->slug}/localizacao/festa")->assertNotFound();
        $this->get("/{$event->slug}/localizacao")->assertNotFound();
        $this->get("/{$event->slug}")->assertOk();
    }

    public function test_a_turned_off_rsvp_tab_takes_no_answers(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        $this->configure($event, active: ['confirmar_presenca' => false]);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '5511999999999'],
            'attending' => true,
            'guests_count' => 1,
        ])->assertNotFound();

        $this->assertSame(0, $event->guests()->count());
    }

    public function test_a_turned_off_guestbook_takes_no_messages(): void
    {
        $event = Event::factory()->withFeatures(Feature::Guestbook)->create(['is_published' => true]);
        $this->configure($event, active: ['recado' => false]);

        $this->post("/{$event->slug}/messages", [
            'author_name' => 'Tia Rosa',
            'message' => 'Felicidades!',
        ])->assertNotFound();

        $this->assertSame(0, GuestMessage::count());
    }

    public function test_the_host_configures_the_tabs_in_the_panel(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withLocation()->create(['user_id' => $host->id, 'is_published' => true]);

        $this->actingAs($host);

        $component = Livewire::test(EditEventSections::class, ['record' => $event->getRouteKey()])
            ->assertSee('Galeria')
            ->assertSee('Confirmar presença');

        $this->assertCount(count(PageSection::cases()), $event->sections()->get());

        // Keyed "record-{id}", which ties each item to its row.
        $keys = collect($component->get('data.sections'))
            ->mapWithKeys(fn (array $section, string $key) => [
                ($section['type'] instanceof PageSection ? $section['type']->value : $section['type']) => $key,
            ]);
        $item = fn (string $type, bool $active) => [$keys[$type] => ['type' => $type, 'is_active' => $active]];

        // Set whole (not merged, as fillForm() does), so the order sticks.
        $component
            ->set('data.sections', [
                ...$item('inicio', true),
                ...$item('localizacao', true),
                ...$item('galeria', false),
                ...$item('presentes', true),
                ...$item('confirmar_presenca', true),
                ...$item('recado', true),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();

        $this->assertSame(
            [PageSection::Home, PageSection::Location, PageSection::Gifts, PageSection::Rsvp],
            $event->visibleSections(),
        );
    }
}
