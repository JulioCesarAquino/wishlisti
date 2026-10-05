<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Events\Pages\ManageEventCoHosts;
use App\Filament\Resources\Guests\Guests\Pages\ListGuests;
use App\Models\Events\Event;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * More than one person can run an event (the couple): co-hosts edit
 * everything but the co-host list, and can't trash the event.
 */
class EventCoHostsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $partner;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['is_admin' => false, 'name' => 'Leide']);
        $this->partner = User::factory()->create(['is_admin' => false, 'name' => 'Rafael', 'email' => 'rafael@example.com']);
        $this->event = Event::factory()->create(['user_id' => $this->owner->id, 'is_published' => false]);
    }

    public function test_the_owner_adds_a_co_host_by_e_mail(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(ManageEventCoHosts::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('addCoHost')->table(), ['email' => 'Rafael@Example.com'])
            ->assertNotified('Rafael agora também cuida deste evento.');

        $this->assertTrue($this->event->coHosts()->whereKey($this->partner->id)->exists());
        $this->assertTrue(Activity::where('event', 'co_host_added')->exists());
    }

    public function test_only_existing_accounts_can_be_added(): void
    {
        $this->actingAs($this->owner);

        Livewire::test(ManageEventCoHosts::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('addCoHost')->table(), ['email' => 'ninguem@example.com'])
            ->assertNotified('Não encontramos uma conta com esse e-mail. A pessoa precisa ter uma conta no Wishlisti (pedindo um convite na página inicial).');

        $this->assertSame(0, $this->event->coHosts()->count());
        $this->assertSame(2, User::count());
    }

    public function test_a_co_host_runs_the_event(): void
    {
        $this->event->coHosts()->attach($this->partner->id);
        $this->actingAs($this->partner);

        Livewire::test(ListEvents::class)->assertCanSeeTableRecords([$this->event]);

        $this->get(EventResource::getUrl('edit', ['record' => $this->event]))->assertOk();

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->fillForm(['title' => 'Leide & Rafael'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Leide & Rafael', $this->event->fresh()->title);

        // The unpublished page too, as a preview.
        $this->get("/{$this->event->slug}")->assertOk();
    }

    public function test_a_co_host_sees_the_event_guests(): void
    {
        $this->event->coHosts()->attach($this->partner->id);
        $guest = $this->event->guests()->create(['name' => 'Tia Rosa', 'identifier' => (string) str()->uuid()]);
        $this->actingAs($this->partner);

        Livewire::test(ListGuests::class)->assertCanSeeTableRecords([$guest]);
    }

    public function test_a_co_host_cannot_manage_co_hosts_nor_trash_the_event(): void
    {
        $this->event->coHosts()->attach($this->partner->id);
        $this->actingAs($this->partner);

        Livewire::test(ManageEventCoHosts::class, ['record' => $this->event->getRouteKey()])
            ->assertSee('Rafael')
            ->assertActionHidden(TestAction::make('addCoHost')->table())
            ->assertActionHidden(TestAction::make('removeCoHost')->table($this->partner));

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);

        $this->assertFalse($this->partner->can('delete', $this->event));
    }

    public function test_the_owner_removes_a_co_host_who_then_loses_access(): void
    {
        $this->event->coHosts()->attach($this->partner->id);
        $this->actingAs($this->owner);

        Livewire::test(ManageEventCoHosts::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('removeCoHost')->table($this->partner));

        $this->assertSame(0, $this->event->coHosts()->count());

        $this->actingAs($this->partner);
        $this->get(EventResource::getUrl('edit', ['record' => $this->event]))->assertNotFound();
        $this->get("/{$this->event->slug}")->assertNotFound();
    }

    public function test_someone_else_cannot_reach_the_event(): void
    {
        $stranger = User::factory()->create(['is_admin' => false]);
        $this->actingAs($stranger);

        Livewire::test(ListEvents::class)->assertCanNotSeeTableRecords([$this->event]);
        $this->get(EventResource::getUrl('co-hosts', ['record' => $this->event]))->assertNotFound();
    }
}
