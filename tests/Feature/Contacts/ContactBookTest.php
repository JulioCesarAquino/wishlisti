<?php

namespace Tests\Feature\Contacts;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Contacts\Contacts\ContactResource;
use App\Filament\Resources\Contacts\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Contacts\Pages\ListContacts;
use App\Filament\Resources\Identity\Users\Pages\EditUser;
use App\Models\Contacts\Contact;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\User;
use App\Services\Contacts\ContactImportService;
use App\Services\Guests\GuestImportService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactBookTest extends TestCase
{
    use RefreshDatabase;

    private function hostWithContacts(): User
    {
        $host = User::factory()->create(['is_admin' => false]);
        $host->grantFeature(Feature::Contacts);

        return $host;
    }

    public function test_hosts_without_the_premium_feature_cannot_open_the_contact_book(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get(ContactResource::getUrl('index'))->assertForbidden();
    }

    public function test_hosts_with_the_feature_only_see_their_own_contacts(): void
    {
        $host = $this->hostWithContacts();
        $mine = Contact::factory()->create(['user_id' => $host->id]);
        $theirs = Contact::factory()->create();

        $this->actingAs($host);

        $this->get(ContactResource::getUrl('index'))->assertOk();

        Livewire::test(ListContacts::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_host_creates_contacts_in_their_own_book(): void
    {
        $host = $this->hostWithContacts();

        $this->actingAs($host);

        Livewire::test(CreateContact::class)
            ->fillForm(['name' => 'Tia Rosa', 'cpf' => '529.982.247-25'])
            ->call('create')
            ->assertHasNoFormErrors();

        $contact = Contact::firstWhere('name', 'Tia Rosa');

        $this->assertSame($host->id, $contact->user_id);
        $this->assertSame('52998224725', $contact->cpf);
    }

    public function test_a_contact_needs_at_least_one_way_to_be_recognised(): void
    {
        $this->actingAs($this->hostWithContacts());

        Livewire::test(CreateContact::class)
            ->fillForm(['name' => 'Tia Rosa'])
            ->call('create')
            ->assertHasFormErrors(['whatsapp']);
    }

    public function test_the_admin_unlocks_the_contact_book_for_a_host(): void
    {
        $host = User::factory()->create(['is_admin' => false]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(EditUser::class, ['record' => $host->getRouteKey()])
            ->fillForm(['premium_features' => [Feature::Contacts->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($host->fresh()->hasFeature(Feature::Contacts));
    }

    public function test_importing_an_event_adds_its_guests_without_duplicating_known_contacts(): void
    {
        $host = $this->hostWithContacts();
        $event = Event::factory()->create(['user_id' => $host->id]);

        $known = Contact::factory()->create([
            'user_id' => $host->id,
            'name' => 'Maria (prima)',
            'whatsapp' => '(11) 99999-9999',
            'email' => null,
        ]);

        Guest::factory()->create(['event_id' => $event->id, 'name' => 'Maria', 'whatsapp' => '11999999999', 'email' => 'maria@example.com']);
        Guest::factory()->create(['event_id' => $event->id, 'name' => 'João', 'whatsapp' => '11988888888']);

        $created = app(ContactImportService::class)->execute($event);

        $this->assertSame(1, $created);
        $this->assertSame(2, $host->contacts()->count());

        // The name the host gave is kept; only the missing e-mail is filled.
        $known->refresh();
        $this->assertSame('Maria (prima)', $known->name);
        $this->assertSame('maria@example.com', $known->email);
    }

    public function test_the_import_action_is_limited_to_the_hosts_own_events(): void
    {
        $host = $this->hostWithContacts();
        $otherEvent = Event::factory()->create();
        Guest::factory()->create(['event_id' => $otherEvent->id]);

        $this->actingAs($host);

        Livewire::test(ListContacts::class)
            ->callAction('importFromEvent', ['event_id' => $otherEvent->id])
            ->assertHasActionErrors(['event_id']);

        $this->assertSame(0, Contact::count());
    }

    public function test_contacts_can_be_pre_registered_as_guests_of_a_new_event(): void
    {
        $host = $this->hostWithContacts();
        $event = Event::factory()->create(['user_id' => $host->id, 'is_published' => true]);

        $contacts = Contact::factory()->count(2)->create(['user_id' => $host->id]);
        Guest::factory()->create(['event_id' => $event->id, 'whatsapp' => $contacts[0]->whatsapp]);

        $this->actingAs($host);

        Livewire::test(ListContacts::class)
            ->selectTableRecords($contacts)
            ->callAction(TestAction::make('addToEvent')->table()->bulk(), ['event_id' => $event->id])
            ->assertHasNoActionErrors();

        $this->assertSame(2, $event->guests()->count());

        $preRegistered = $event->guests()->firstWhere('name', $contacts[1]->name);
        $this->assertNull($preRegistered->rsvp_status);

        // When they confirm, the RSVP lands on the pre-registered guest.
        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => $contacts[1]->name, 'whatsapp' => $contacts[1]->whatsapp],
            'attending' => true,
            'guests_count' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $event->guests()->count());
        $this->assertSame(Guest::RSVP_CONFIRMED, $preRegistered->fresh()->rsvp_status);
    }

    public function test_contacts_from_another_host_are_never_added_to_an_event(): void
    {
        $event = Event::factory()->create();
        $foreign = Contact::factory()->create();

        $created = app(GuestImportService::class)->execute($event, [$foreign]);

        $this->assertSame(0, $created);
        $this->assertSame(0, $event->guests()->count());
    }
}
