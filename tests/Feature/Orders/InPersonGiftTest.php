<?php

namespace Tests\Feature\Orders;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\EditEventPayment;
use App\Filament\Resources\Orders\Orders\Pages\ListOrders;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\User;
use App\Services\Guests\GuestDestroyService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class InPersonGiftTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Event $event;

    private EventProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->create(['user_id' => $this->host->id, 'is_published' => true]);
        $this->product = EventProduct::factory()->create(['event_id' => $this->event->id, 'price' => 200, 'quantity_total' => 1]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function reserve(array $overrides = []): TestResponse
    {
        return $this->postJson("/{$this->event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
            'fulfillment' => Order::FULFILLMENT_IN_PERSON,
            ...$overrides,
        ]);
    }

    public function test_a_free_event_takes_reservations_and_sets_the_item_aside(): void
    {
        $this->reserve(['message' => 'Levo no dia!'])
            ->assertOk()
            ->assertJsonPath('order.fulfillment', Order::FULFILLMENT_IN_PERSON);

        $order = Order::first();
        $this->assertSame(Order::STATUS_RESERVED, $order->status);
        $this->assertSame('Levo no dia!', $order->message);
        $this->assertTrue($this->product->fresh()->isSoldOut());

        // Nobody else can pick the same item.
        $this->reserve(['guest' => ['name' => 'João', 'whatsapp' => '11988888888']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_a_free_event_does_not_take_online_gifts(): void
    {
        $this->reserve(['fulfillment' => Order::FULFILLMENT_ONLINE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_the_guest_is_identified_with_the_fields_the_host_requires(): void
    {
        $this->event->grantFeature(Feature::GuestList);
        $this->event->rsvpSettings()->create(['required_fields' => ['cpf']]);

        $this->reserve(['guest' => ['name' => 'Maria']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('guest.cpf')
            ->assertJsonMissingValidationErrors('guest.whatsapp');

        $this->reserve(['guest' => ['name' => 'Maria', 'cpf' => '529.982.247-25']])->assertOk();

        $this->assertSame('52998224725', Guest::first()->cpf);
    }

    public function test_the_guest_can_give_up_and_the_item_goes_back_to_the_list(): void
    {
        $this->reserve();
        $order = Order::first();
        $cookie = Guest::cookieName($this->event);

        // Someone else's browser can't undo it.
        $this->withCookie($cookie, 'not-the-guest')
            ->post("/{$this->event->slug}/orders/{$order->id}/cancel")
            ->assertForbidden();

        $this->withCookie($cookie, $order->guest->identifier)
            ->post("/{$this->event->slug}/orders/{$order->id}/cancel")
            ->assertRedirect();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertFalse($this->product->fresh()->isSoldOut());
    }

    public function test_the_page_lists_the_guests_own_reservations(): void
    {
        $this->reserve();
        $order = Order::first();

        $this->withCookie(Guest::cookieName($this->event), $order->guest->identifier)
            ->get("/{$this->event->slug}")
            ->assertInertia(fn ($page) => $page
                ->where('event.accepts_in_person_gifts', true)
                ->where('event.accepts_online_gifts', false)
                ->where('guest.reservations.0.id', $order->id)
                ->where('guest.reservations.0.status', Order::STATUS_RESERVED));
    }

    public function test_the_host_marks_a_gift_as_received_or_frees_it(): void
    {
        $this->reserve();
        $received = Order::first();

        $second = EventProduct::factory()->create(['event_id' => $this->event->id, 'quantity_total' => 1]);
        $this->reserve([
            'guest' => ['name' => 'João', 'whatsapp' => '11988888888'],
            'items' => [['event_product_id' => $second->id, 'quantity' => 1]],
        ]);
        $freed = Order::latest('id')->first();

        $this->actingAs($this->host);

        Livewire::test(ListOrders::class)
            ->callAction(TestAction::make('markReceived')->table($received))
            ->callAction(TestAction::make('cancelReservation')->table($freed));

        $this->assertSame(Order::STATUS_RECEIVED, $received->fresh()->status);
        $this->assertTrue($this->product->fresh()->isSoldOut());
        $this->assertSame(Order::STATUS_CANCELLED, $freed->fresh()->status);
        $this->assertFalse($second->fresh()->isSoldOut());

        // Once handed over, the guest can't give up anymore.
        $this->withCookie(Guest::cookieName($this->event), $received->guest->identifier)
            ->post("/{$this->event->slug}/orders/{$received->id}/cancel")
            ->assertSessionHasErrors('order');
    }

    public function test_an_anonymous_reservation_hides_the_guest_from_the_host(): void
    {
        $this->reserve(['anonymous' => true, 'message' => 'Surpresa!']);

        $this->actingAs($this->host);

        Livewire::test(ListOrders::class)
            ->assertSee('Presente anônimo — entrega pelo convidado')
            ->assertSee('Surpresa!')
            ->assertDontSee('Maria');
    }

    public function test_a_premium_event_offers_both_and_the_host_can_turn_in_person_off(): void
    {
        $this->event->grantFeature(Feature::Payments);
        $this->event->paymentSettings()->create(['mp_access_token' => 'TEST-token', 'mp_public_key' => 'TEST-key']);

        $this->reserve()->assertOk();
        $this->assertTrue($this->event->fresh()->acceptsOnlineGifts());

        $this->actingAs($this->host);

        Livewire::test(EditEventPayment::class, ['record' => $this->event->getRouteKey()])
            ->fillForm(['giftSettings.allow_in_person' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->event->fresh()->acceptsInPersonGifts());

        $other = EventProduct::factory()->create(['event_id' => $this->event->id]);
        $this->reserve(['items' => [['event_product_id' => $other->id, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_a_reservation_cannot_be_sent_to_mercado_pago(): void
    {
        $this->event->grantFeature(Feature::Payments);
        $this->event->paymentSettings()->create(['mp_access_token' => 'TEST-token', 'mp_public_key' => 'TEST-key']);
        $this->reserve();

        $this->postJson("/{$this->event->slug}/orders/".Order::first()->id.'/mercadopago-payment', [
            'formData' => ['payment_method_id' => 'pix'],
        ])->assertStatus(409);
    }

    public function test_purging_a_guest_gives_their_reserved_items_back(): void
    {
        $this->reserve();
        $guest = Guest::first();
        $guest->delete();

        app(GuestDestroyService::class)->execute($guest);

        $this->assertNull(Guest::withTrashed()->find($guest->id));
        $this->assertFalse($this->product->fresh()->isSoldOut());
    }
}
