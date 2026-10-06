<?php

namespace Tests\Feature\Guests;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\ManageEventGuests;
use App\Models\Events\Event;
use App\Models\Events\EventRsvpSetting;
use App\Models\Guests\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The RSVP form field by field — the age among them — and "children under
 * X don't pay", for the host to know how many pay.
 */
class RsvpAgesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $fields
     */
    private function premiumEvent(array $fields, bool $companions = true, ?int $childAgeLimit = 5): Event
    {
        return Event::factory()
            ->withFeatures(Feature::GuestList)
            ->withRsvpSettings([
                'collect_companions' => $companions,
                'fields' => [...EventRsvpSetting::DEFAULT_FIELDS, ...$fields],
                'companion_fields' => [...EventRsvpSetting::DEFAULT_FIELDS, ...$fields],
                'child_age_limit' => $childAgeLimit,
            ])
            ->create(['is_published' => true]);
    }

    public function test_a_form_with_only_name_and_age_keeps_each_persons_age(): void
    {
        $event = $this->premiumEvent([
            'whatsapp' => EventRsvpSetting::FIELD_HIDDEN,
            'email' => EventRsvpSetting::FIELD_HIDDEN,
            'age' => EventRsvpSetting::FIELD_REQUIRED,
        ]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.rsvp_fields.age', EventRsvpSetting::FIELD_REQUIRED)
            ->where('event.rsvp_fields.whatsapp', EventRsvpSetting::FIELD_HIDDEN)
            ->where('event.rsvp_child_age_limit', 5)
            // Each person's age tells: no need to ask how many children.
            ->where('event.rsvp_asks_children_count', false));

        $this->post("/{$event->slug}/rsvp", [
            // A WhatsApp sent anyway isn't kept: the form doesn't ask for it.
            'guest' => ['name' => 'Ana', 'age' => 34, 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 3,
            'companions' => [
                ['name' => 'Bruno', 'age' => 36],
                ['name' => 'Lia', 'age' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $ana = Guest::where('name', 'Ana')->firstOrFail();
        $this->assertSame(34, $ana->age);
        $this->assertNull($ana->whatsapp);
        $this->assertSame(3, Guest::where('name', 'Lia')->firstOrFail()->age);

        $this->assertSame(['people' => 3, 'children' => 1, 'paying' => 2], $event->fresh()->rsvpHeadcount());

        // What the host sees on the guest list.
        $this->actingAs($event->user);

        Livewire::test(ManageEventGuests::class, ['record' => $event->getRouteKey()])
            ->assertSee('3 pessoas confirmadas · 2 pagantes · 1 criança com menos de 5 anos')
            ->assertSee('Não paga');
    }

    public function test_companions_have_fields_of_their_own(): void
    {
        // The guest gives a WhatsApp; the children they bring, a name and an age.
        $event = Event::factory()
            ->withFeatures(Feature::GuestList)
            ->withRsvpSettings([
                'collect_companions' => true,
                'fields' => [...EventRsvpSetting::DEFAULT_FIELDS, 'whatsapp' => EventRsvpSetting::FIELD_REQUIRED],
                'companion_fields' => [
                    'whatsapp' => EventRsvpSetting::FIELD_HIDDEN,
                    'email' => EventRsvpSetting::FIELD_HIDDEN,
                    'cpf' => EventRsvpSetting::FIELD_HIDDEN,
                    'age' => EventRsvpSetting::FIELD_REQUIRED,
                ],
                'child_age_limit' => 5,
            ])
            ->create(['is_published' => true]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.rsvp_fields.whatsapp', EventRsvpSetting::FIELD_REQUIRED)
            ->where('event.rsvp_companion_fields.whatsapp', EventRsvpSetting::FIELD_HIDDEN)
            ->where('event.rsvp_companion_fields.age', EventRsvpSetting::FIELD_REQUIRED)
            ->where('event.rsvp_asks_children_count', false));

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 3,
            'companions' => [['name' => 'Lia', 'age' => 3], ['name' => 'Théo']],
        ])->assertSessionHasErrors(['companions.1.age']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 3,
            'companions' => [['name' => 'Lia', 'age' => 3], ['name' => 'Théo', 'age' => 7]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['people' => 3, 'children' => 1, 'paying' => 2], $event->fresh()->rsvpHeadcount());
    }

    public function test_a_required_age_must_be_given_for_everyone(): void
    {
        $event = $this->premiumEvent(['age' => EventRsvpSetting::FIELD_REQUIRED]);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 2,
            'companions' => [['name' => 'Lia', 'whatsapp' => '11988888888']],
        ])->assertSessionHasErrors([
            'guest.age' => 'Por favor, informe sua idade.',
            'companions.0.age' => 'Por favor, informe a idade do acompanhante.',
        ]);
    }

    public function test_with_just_a_headcount_the_form_asks_how_many_are_children(): void
    {
        // A free event: the age limit doesn't need the premium.
        $event = Event::factory()->withRsvpSettings(['child_age_limit' => 5])->create(['is_published' => true]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page->where('event.rsvp_asks_children_count', true));

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 4,
        ])->assertSessionHasErrors('children_count');

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 4,
            'children_count' => 5,
        ])->assertSessionHasErrors(['children_count' => 'O número de crianças não pode passar o total de pessoas.']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 4,
            'children_count' => 2,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Guest::firstOrFail()->rsvp_children_count);
        $this->assertSame(['people' => 4, 'children' => 2, 'paying' => 2], $event->fresh()->rsvpHeadcount());
    }

    public function test_companions_without_their_age_fall_back_to_asking_how_many_are_children(): void
    {
        $event = $this->premiumEvent(['age' => EventRsvpSetting::FIELD_HIDDEN]);

        $this->assertTrue($event->asksRsvpChildrenCount());
    }

    public function test_without_an_age_limit_nothing_about_children_is_asked(): void
    {
        $event = Event::factory()->create(['is_published' => true]);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Ana', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 2,
            'children_count' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Guest::firstOrFail()->rsvp_children_count);
        $this->assertSame(['people' => 2, 'children' => null, 'paying' => null], $event->fresh()->rsvpHeadcount());
    }

    public function test_free_events_keep_the_original_form_whatever_was_chosen(): void
    {
        $event = Event::factory()
            ->withRsvpSettings(['fields' => [...EventRsvpSetting::DEFAULT_FIELDS, 'age' => EventRsvpSetting::FIELD_REQUIRED, 'whatsapp' => EventRsvpSetting::FIELD_HIDDEN]])
            ->create(['is_published' => true]);

        $this->assertSame(EventRsvpSetting::DEFAULT_FIELDS, $event->rsvpFields());
    }
}
