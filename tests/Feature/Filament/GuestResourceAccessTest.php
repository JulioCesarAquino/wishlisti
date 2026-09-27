<?php

namespace Tests\Feature\Filament;

use App\Enums\Premium\Feature;
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

    public function test_hosts_cannot_see_the_gift_count_on_non_premium_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);
        $this->createGuestWithPaidOrder($event);

        $this->actingAs($host);

        Livewire::test(ListGuests::class)
            ->assertSee('🔒');
    }

    public function test_hosts_can_see_the_gift_count_on_premium_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::GiftGivers)->create(['user_id' => $host->id]);
        $this->createGuestWithPaidOrder($event);

        $this->actingAs($host);

        Livewire::test(ListGuests::class)
            ->assertDontSee('🔒');
    }

    public function test_admins_always_see_the_gift_count_regardless_of_premium(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);
        $this->createGuestWithPaidOrder($event);

        $this->actingAs($admin);

        Livewire::test(ListGuests::class)
            ->assertDontSee('🔒');
    }
}
