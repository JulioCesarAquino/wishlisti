<?php

namespace Tests\Feature\Trash;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashPurgeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_purges_only_old_trash_without_paid_orders(): void
    {
        $event = Event::factory()->create();

        $old = Guest::factory()->create(['event_id' => $event->id]);
        $recent = Guest::factory()->create(['event_id' => $event->id]);
        $giver = Guest::factory()->create(['event_id' => $event->id]);
        $abandonedCheckout = Guest::factory()->create(['event_id' => $event->id]);

        $soldProduct = EventProduct::factory()->create(['event_id' => $event->id]);
        $unsoldProduct = EventProduct::factory()->create(['event_id' => $event->id]);

        $paid = Order::factory()->create(['event_id' => $event->id, 'guest_id' => $giver->id, 'status' => Order::STATUS_PAID]);
        OrderItem::factory()->create(['order_id' => $paid->id, 'event_product_id' => $soldProduct->id]);
        Order::factory()->create(['event_id' => $event->id, 'guest_id' => $abandonedCheckout->id, 'status' => Order::STATUS_PENDING]);

        $this->travelTo(now()->subDays(31), function () use ($old, $giver, $abandonedCheckout, $soldProduct, $unsoldProduct) {
            $old->delete();
            $giver->delete();
            $abandonedCheckout->delete();
            $soldProduct->delete();
            $unsoldProduct->delete();
        });
        $recent->delete();

        $this->artisan('trash:purge')->assertSuccessful();

        $this->assertNull(Guest::withTrashed()->find($old->id));
        $this->assertNull(Guest::withTrashed()->find($abandonedCheckout->id));
        $this->assertNotNull(Guest::withTrashed()->find($recent->id));
        $this->assertNotNull(Guest::withTrashed()->find($giver->id));

        $this->assertNull(EventProduct::withTrashed()->find($unsoldProduct->id));
        $this->assertNotNull(EventProduct::withTrashed()->find($soldProduct->id));

        $this->assertSame(1, Order::count());
    }

    public function test_it_purges_old_trashed_events_with_everything_under_them(): void
    {
        $event = Event::factory()->create();
        Guest::factory()->create(['event_id' => $event->id]);
        EventProduct::factory()->create(['event_id' => $event->id]);

        $this->travelTo(now()->subDays(31), fn () => $event->delete());

        $this->artisan('trash:purge')->assertSuccessful();

        $this->assertNull(Event::withTrashed()->find($event->id));
        $this->assertSame(0, Guest::withTrashed()->count());
        $this->assertSame(0, EventProduct::withTrashed()->count());
    }
}
