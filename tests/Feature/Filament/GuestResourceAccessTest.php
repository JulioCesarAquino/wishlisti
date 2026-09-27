<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Guests\Guests\GuestResource;
use App\Filament\Resources\Guests\Guests\Pages\ListGuests;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GuestResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/admin/guests')->assertRedirect('/admin/login');
    }

    public function test_hosts_can_access_the_guests_list(): void
    {
        $host = User::factory()->create(['is_admin' => false]);

        $this->actingAs($host)
            ->get('/admin/guests')
            ->assertOk();
    }

    public function test_hosts_only_see_guests_for_their_own_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $otherHost = User::factory()->create(['is_admin' => false]);

        $event = Event::factory()->create(['user_id' => $host->id]);
        $otherEvent = Event::factory()->create(['user_id' => $otherHost->id]);

        $event->guests()->create([
            'name' => 'Convidado Próprio',
            'whatsapp' => '11999999999',
            'identifier' => (string) Str::uuid(),
        ]);
        $otherEvent->guests()->create([
            'name' => 'Convidado Alheio',
            'whatsapp' => '11988888888',
            'identifier' => (string) Str::uuid(),
        ]);

        $this->actingAs($host);

        $this->assertSame(1, GuestResource::getEloquentQuery()->count());
    }

    public function test_admins_see_every_guest(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create(['is_admin' => false]);
        $otherHost = User::factory()->create(['is_admin' => false]);

        $event = Event::factory()->create(['user_id' => $host->id]);
        $otherEvent = Event::factory()->create(['user_id' => $otherHost->id]);

        $event->guests()->create([
            'name' => 'Convidado Próprio',
            'whatsapp' => '11999999999',
            'identifier' => (string) Str::uuid(),
        ]);
        $otherEvent->guests()->create([
            'name' => 'Convidado Alheio',
            'whatsapp' => '11988888888',
            'identifier' => (string) Str::uuid(),
        ]);

        $this->actingAs($admin);

        $this->assertSame(2, GuestResource::getEloquentQuery()->count());
    }

    private function createGuestWithPaidOrder(Event $event): Guest
    {
        $guest = $event->guests()->create([
            'name' => 'Convidado Generoso',
            'whatsapp' => '11999999999',
            'identifier' => (string) Str::uuid(),
        ]);

        Order::factory()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'status' => Order::STATUS_PAID,
        ]);

        return $guest;
    }

    public function test_hosts_see_how_many_gifts_each_guest_gave(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);
        $guest = $this->createGuestWithPaidOrder($event);

        $this->actingAs($host);

        Livewire::test(ListGuests::class)
            ->assertTableColumnFormattedStateSet('paid_orders_count', '1', $guest);
    }

    public function test_anonymous_gifts_do_not_give_the_guest_away(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $confirmed = $this->createGuestWithPaidOrder($event);
        $confirmed->update(['rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 1]);
        Order::query()->update(['is_anonymous' => true]);

        // Only linked to the event by an anonymous gift: not listed at all.
        $onlyGave = Guest::factory()->create(['event_id' => $event->id, 'name' => 'Doador Discreto']);
        Order::factory()->create(['event_id' => $event->id, 'guest_id' => $onlyGave->id, 'status' => Order::STATUS_PAID, 'is_anonymous' => true]);

        $this->actingAs($host);

        Livewire::test(ListGuests::class)
            ->assertCanSeeTableRecords([$confirmed])
            ->assertCanNotSeeTableRecords([$onlyGave])
            ->assertTableColumnFormattedStateSet('paid_orders_count', '0', $confirmed);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListGuests::class)->assertCanSeeTableRecords([$confirmed, $onlyGave]);
    }
}
