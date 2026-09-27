<?php

namespace Tests\Feature\Filament;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Orders\Orders\OrderResource;
use App\Filament\Resources\Orders\Orders\Pages\ListOrders;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/admin/orders')->assertRedirect('/admin/login');
    }

    public function test_hosts_can_access_the_orders_list(): void
    {
        $host = User::factory()->create(['is_admin' => false]);

        $this->actingAs($host)
            ->get('/admin/orders')
            ->assertOk();
    }

    public function test_hosts_only_see_orders_for_their_own_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $otherHost = User::factory()->create(['is_admin' => false]);

        $event = Event::factory()->create(['user_id' => $host->id]);
        $otherEvent = Event::factory()->create(['user_id' => $otherHost->id]);

        Order::factory()->create(['event_id' => $event->id]);
        Order::factory()->create(['event_id' => $otherEvent->id]);

        $this->actingAs($host);

        $this->assertSame(1, OrderResource::getEloquentQuery()->count());
    }

    public function test_admins_see_every_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create(['is_admin' => false]);
        $otherHost = User::factory()->create(['is_admin' => false]);

        $event = Event::factory()->create(['user_id' => $host->id]);
        $otherEvent = Event::factory()->create(['user_id' => $otherHost->id]);

        Order::factory()->create(['event_id' => $event->id]);
        Order::factory()->create(['event_id' => $otherEvent->id]);

        $this->actingAs($admin);

        $this->assertSame(2, OrderResource::getEloquentQuery()->count());
    }

    private function createOrderWithGuest(Event $event): Order
    {
        $guest = $event->guests()->create([
            'name' => 'Maria Segredo',
            'whatsapp' => '11999999999',
            'identifier' => (string) Str::uuid(),
        ]);

        return Order::factory()->create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
        ]);
    }

    public function test_hosts_cannot_see_guest_identity_on_non_premium_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);
        $this->createOrderWithGuest($event);

        $this->actingAs($host);

        Livewire::test(ListOrders::class)
            ->assertDontSee('Maria Segredo')
            ->assertSee('Identidade oculta');
    }

    public function test_hosts_can_see_guest_identity_on_premium_events(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::GiftGivers)->create(['user_id' => $host->id]);
        $this->createOrderWithGuest($event);

        $this->actingAs($host);

        Livewire::test(ListOrders::class)
            ->assertSee('Maria Segredo');
    }

    public function test_admins_always_see_guest_identity_regardless_of_premium(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);
        $this->createOrderWithGuest($event);

        $this->actingAs($admin);

        Livewire::test(ListOrders::class)
            ->assertSee('Maria Segredo');
    }
}
