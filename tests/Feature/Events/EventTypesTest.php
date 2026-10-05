<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Events\Pages\EditEvent;
use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * More kinds of event, and a story section whose title fits the kind —
 * "Nossa história" is a couple's.
 */
class EventTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_story_title_follows_the_kind_of_event(): void
    {
        $page = fn (Event $event) => $this->get("/{$event->slug}");

        $wedding = Event::factory()->create(['is_published' => true, 'type' => 'casamento', 'story' => 'Nos conhecemos…']);
        $birthday = Event::factory()->create(['is_published' => true, 'type' => 'aniversario', 'story' => 'A Maju faz 5!']);
        $barbecue = Event::factory()->create(['is_published' => true, 'type' => 'churrasco', 'story' => 'O de sempre.']);

        $page($wedding)->assertInertia(fn ($p) => $p->where('event.story_title', 'Nossa história')->where('event.type_label', 'Casamento'));
        $page($birthday)->assertInertia(fn ($p) => $p->where('event.story_title', 'Sobre o aniversário'));
        $page($barbecue)->assertInertia(fn ($p) => $p->where('event.story_title', 'Sobre o evento')->where('event.type_label', 'Churrasco'));
    }

    public function test_the_host_can_write_their_own_title(): void
    {
        $event = Event::factory()->create(['is_published' => true, 'type' => 'aniversario', 'story_title' => 'Sobre a Maju']);

        $this->get("/{$event->slug}")->assertInertia(fn ($p) => $p->where('event.story_title', 'Sobre a Maju'));
    }

    public function test_the_new_kinds_can_be_chosen_on_the_panel(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id, 'type' => 'aniversario']);

        $this->actingAs($host);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['type' => 'futebol', 'story_title' => 'A pelada de sábado'])
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();
        $this->assertSame('futebol', $event->type);
        $this->assertSame('Futebol', $event->typeLabel());
        $this->assertSame('A pelada de sábado', $event->storyTitle());
    }

    public function test_another_kind_reads_as_an_event(): void
    {
        $this->assertSame('Evento', Event::factory()->make(['type' => 'outro'])->typeLabel());
    }
}
