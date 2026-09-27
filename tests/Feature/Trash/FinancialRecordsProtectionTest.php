<?php

namespace Tests\Feature\Trash;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Events\Pages\ManageEventGuests;
use App\Filament\Resources\Events\Events\Pages\ManageEventProducts;
use App\Filament\Resources\Identity\Users\Pages\EditUser;
use App\Filament\Resources\Orders\Orders\Pages\ListOrders;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FinancialRecordsProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->create(['user_id' => $this->host->id]);
    }

    private function guestWithPaidOrder(): Guest
    {
        $guest = Guest::factory()->create(['event_id' => $this->event->id]);
        $product = EventProduct::factory()->create(['event_id' => $this->event->id, 'quantity_purchased' => 1]);
        $order = Order::factory()->create(['event_id' => $this->event->id, 'guest_id' => $guest->id, 'status' => Order::STATUS_PAID]);
        OrderItem::factory()->create(['order_id' => $order->id, 'event_product_id' => $product->id]);

        return $guest;
    }

    public function test_the_database_refuses_to_delete_a_guest_with_orders(): void
    {
        $guest = $this->guestWithPaidOrder();

        $this->expectException(QueryException::class);

        $guest->forceDelete();
    }

    public function test_a_guest_who_gave_a_gift_cannot_be_moved_to_the_trash(): void
    {
        $guest = $this->guestWithPaidOrder();

        $this->actingAs($this->host);

        Livewire::test(ManageEventGuests::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('delete')->table($guest))
            ->assertNotified('Este convidado não pode ser excluído');

        $this->assertFalse($guest->fresh()->trashed());
        $this->assertSame(1, Order::count());
    }

    public function test_bulk_delete_skips_guests_who_gave_gifts(): void
    {
        $giver = $this->guestWithPaidOrder();
        $other = Guest::factory()->create(['event_id' => $this->event->id]);

        $this->actingAs($this->host);

        Livewire::test(ManageEventGuests::class, ['record' => $this->event->getRouteKey()])
            ->selectTableRecords([$giver, $other])
            ->callAction(TestAction::make('delete')->table()->bulk());

        $this->assertFalse($giver->fresh()->trashed());
        $this->assertTrue($other->fresh()->trashed());
    }

    public function test_a_trashed_guest_can_be_restored(): void
    {
        $guest = Guest::factory()->create(['event_id' => $this->event->id]);

        $this->actingAs($this->host);

        Livewire::test(ManageEventGuests::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('delete')->table($guest));

        $this->assertTrue($guest->fresh()->trashed());

        Livewire::test(ManageEventGuests::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('restore')->table($guest));

        $this->assertFalse($guest->fresh()->trashed());
        $this->assertTrue(Activity::where('description', 'restaurou o convidado')->exists());
    }

    public function test_companions_follow_their_host_to_the_trash_and_back(): void
    {
        $host = Guest::factory()->create(['event_id' => $this->event->id, 'rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 3]);
        $companions = Guest::factory()->count(2)->create(['event_id' => $this->event->id, 'companion_of_guest_id' => $host->id, 'rsvp_status' => Guest::RSVP_CONFIRMED]);

        // Trashing one companion alone takes them out of the headcount.
        $companions[0]->delete();
        $this->assertSame(2, $host->fresh()->rsvp_guests_count);

        $companions[0]->restore();
        $this->assertSame(3, $host->fresh()->rsvp_guests_count);

        $host->delete();
        $this->assertSame(0, $this->event->guests()->count());

        $host->fresh()->restore();
        $this->assertSame(3, $this->event->guests()->count());
        $this->assertSame(3, $host->fresh()->rsvp_guests_count);
    }

    public function test_anonymizing_erases_personal_data_but_keeps_the_orders(): void
    {
        $guest = $this->guestWithPaidOrder();
        $guest->update(['name' => 'Maria Silva', 'email' => 'maria@example.com', 'cpf' => '52998224725']);
        $oldIdentifier = $guest->identifier;

        $this->actingAs($this->host);

        Livewire::test(ManageEventGuests::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('anonymize')->table($guest));

        $guest->refresh();

        $this->assertSame(Guest::ANONYMIZED_NAME, $guest->name);
        $this->assertNull($guest->whatsapp);
        $this->assertNull($guest->email);
        $this->assertNull($guest->cpf);
        $this->assertNotSame($oldIdentifier, $guest->identifier);
        $this->assertSame(1, $guest->orders()->count());

        $this->assertTrue(Activity::where('description', 'anonimizou os dados do convidado')->exists());
        $this->assertFalse(Activity::where('attribute_changes', 'like', '%Maria Silva%')->exists());
    }

    public function test_a_guest_whose_gift_was_anonymous_can_be_trashed(): void
    {
        $guest = $this->guestWithPaidOrder();
        $guest->update(['rsvp_status' => Guest::RSVP_CONFIRMED, 'rsvp_guests_count' => 1]);
        Order::query()->update(['is_anonymous' => true]);

        $this->actingAs($this->host);

        // Refusing would reveal they gave something; the trash never purges
        // them while the paid order exists.
        Livewire::test(ManageEventGuests::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('delete')->table($guest));

        $this->assertTrue($guest->fresh()->trashed());
    }

    public function test_orders_still_show_a_guest_that_went_to_the_trash(): void
    {
        $guest = Guest::factory()->create(['event_id' => $this->event->id, 'name' => 'Convidado na Lixeira']);
        $order = Order::factory()->create(['event_id' => $this->event->id, 'guest_id' => $guest->id]);
        $guest->delete();

        $this->assertSame('Convidado na Lixeira', $order->fresh()->guest->name);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListOrders::class)->assertSee('Convidado na Lixeira');
    }

    public function test_a_gift_already_in_orders_cannot_be_deleted(): void
    {
        $this->guestWithPaidOrder();
        $sold = EventProduct::first();
        $unsold = EventProduct::factory()->create(['event_id' => $this->event->id]);

        $this->actingAs($this->host);

        Livewire::test(ManageEventProducts::class, ['record' => $this->event->getRouteKey()])
            ->assertActionDisabled(TestAction::make('delete')->table($sold))
            ->callAction(TestAction::make('delete')->table($unsold));

        $this->assertFalse($sold->fresh()->trashed());
        $this->assertTrue($unsold->fresh()->trashed());
    }

    public function test_a_trashed_gift_leaves_the_public_page_and_cannot_be_ordered(): void
    {
        $this->event->grantFeature(Feature::Payments);
        $this->event->paymentSettings()->create(['mp_access_token' => 'TEST-token', 'mp_public_key' => 'TEST-key']);
        $product = EventProduct::factory()->create(['event_id' => $this->event->id, 'name' => 'Presente Removido']);
        $product->delete();

        $this->get("/{$this->event->slug}")->assertDontSee('Presente Removido');

        $this->postJson("/{$this->event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(404);
    }

    public function test_an_event_with_paid_orders_is_archived_instead_of_deleted(): void
    {
        $this->guestWithPaidOrder();

        $this->actingAs($this->host);

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->assertActionHidden('forceDelete')
            ->callAction('delete')
            ->assertNotified('Este evento não pode ser excluído');

        $this->assertFalse($this->event->fresh()->trashed());

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->callAction('archive');

        $this->assertTrue($this->event->fresh()->isArchived());

        // Guests can't reach it anymore; the host can still preview it.
        auth()->logout();
        $this->get("/{$this->event->slug}")->assertNotFound();

        $this->actingAs($this->host)->get("/{$this->event->slug}")->assertOk();
    }

    public function test_an_event_without_paid_orders_goes_to_the_trash_and_back(): void
    {
        $this->actingAs($this->host);

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->callAction('delete');

        $this->assertTrue($this->event->fresh()->trashed());
        $this->get("/{$this->event->slug}")->assertNotFound();

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->callAction('restore');

        $this->assertFalse($this->event->fresh()->trashed());
    }

    public function test_only_the_admin_deletes_an_event_for_good_with_its_orders(): void
    {
        $this->guestWithPaidOrder();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->callAction('forceDelete');

        $this->assertNull(Event::withTrashed()->find($this->event->id));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, Guest::withTrashed()->count());
        $this->assertTrue(Activity::where('description', 'excluiu definitivamente o evento')->exists());
    }

    public function test_a_host_whose_events_have_orders_cannot_be_deleted(): void
    {
        $this->guestWithPaidOrder();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(EditUser::class, ['record' => $this->host->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('Este anfitrião não pode ser excluído');

        $this->assertNotNull($this->host->fresh());
    }
}
