<?php

namespace Tests\Feature\Orders;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Orders\Orders\Pages\ListOrders;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

/**
 * A guest who opens the checkout and gives up leaves a pending order
 * behind; those are closed after a while, so the hosts see the gifts.
 */
class OrderExpireAbandonedTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
    }

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = [], int $minutesAgo = 180): Order
    {
        $this->travel(-$minutesAgo)->minutes();
        $order = Order::factory()->create(['event_id' => $this->event->id, ...$attributes]);
        $this->travelBack();

        return $order;
    }

    public function test_orders_left_without_a_payment_are_closed_after_two_hours(): void
    {
        $abandoned = $this->order();
        $recent = $this->order(minutesAgo: 30);
        $paid = $this->order(['status' => Order::STATUS_PAID]);
        $reserved = $this->order(['status' => Order::STATUS_RESERVED, 'fulfillment' => Order::FULFILLMENT_IN_PERSON]);

        $this->artisan('orders:expire-abandoned')->assertSuccessful();

        $this->assertSame(Order::STATUS_EXPIRED, $abandoned->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $recent->fresh()->status);
        $this->assertSame(Order::STATUS_PAID, $paid->fresh()->status);
        $this->assertSame(Order::STATUS_RESERVED, $reserved->fresh()->status);
    }

    public function test_a_started_payment_is_checked_with_mercado_pago(): void
    {
        $unpaidPix = $this->order(['payment_id' => '111']);
        $openBoleto = $this->order(['payment_id' => '222']);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(
            new MPResponse(200, ['id' => 111, 'status' => 'cancelled', 'status_detail' => 'expired', 'external_reference' => (string) $unpaidPix->id, 'payment_method_id' => 'pix']),
            new MPResponse(200, ['id' => 222, 'status' => 'pending', 'status_detail' => 'pending_waiting_payment', 'external_reference' => (string) $openBoleto->id, 'payment_method_id' => 'bolbradesco']),
        ));

        $this->artisan('orders:expire-abandoned')->assertSuccessful();

        $this->assertSame(Order::STATUS_CANCELLED, $unpaidPix->fresh()->status);
        // Still open (a boleto has days): asked again later, not right away.
        $this->assertSame(Order::STATUS_PENDING, $openBoleto->fresh()->status);
        $this->assertTrue($openBoleto->fresh()->updated_at->isAfter(now()->subMinute()));
    }

    /**
     * @return array<string, mixed>
     */
    private function pix(int $id, string $status, Order $order): array
    {
        return ['id' => $id, 'status' => $status, 'status_detail' => $status, 'external_reference' => (string) $order->id, 'payment_method_id' => 'pix'];
    }

    public function test_a_pix_still_open_hours_later_is_cancelled(): void
    {
        $order = $this->order(['payment_id' => '111']);

        $fake = new FakeMercadoPagoHttpClient(
            new MPResponse(200, $this->pix(111, 'pending', $order)),   // how it's doing
            new MPResponse(200, $this->pix(111, 'cancelled', $order)), // the cancel
        );
        MercadoPagoConfig::setHttpClient($fake);

        $this->artisan('orders:expire-abandoned')->assertSuccessful();

        $this->assertSame('PUT', $fake->requests[1]->getMethod());
        $this->assertSame('cancelled', json_decode($fake->requests[1]->getPayload(), true)['status']);
        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
    }

    public function test_a_pix_paid_just_as_it_would_be_cancelled_counts_as_paid(): void
    {
        $order = $this->order(['payment_id' => '111']);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(
            new MPResponse(200, $this->pix(111, 'pending', $order)),
            new MPResponse(400, ['message' => 'Payment already approved']), // the cancel, refused
            new MPResponse(200, $this->pix(111, 'approved', $order)),      // asked again
        ));

        $this->artisan('orders:expire-abandoned')->assertSuccessful();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_a_payment_unknown_to_the_events_account_is_closed(): void
    {
        // Made with the credentials the host had before.
        $order = $this->order(['payment_id' => '111']);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(
            new MPResponse(404, ['message' => 'Payment not found', 'error' => 'not_found', 'status' => 404]),
        ));

        $this->artisan('orders:expire-abandoned')->assertSuccessful();

        $this->assertSame(Order::STATUS_EXPIRED, $order->fresh()->status);
    }

    public function test_the_list_opens_on_the_gifts_with_the_rest_in_tabs_of_their_own(): void
    {
        $guest = fn (string $name) => Guest::factory()->create(['event_id' => $this->event->id, 'name' => $name]);
        Order::factory()->create(['event_id' => $this->event->id, 'guest_id' => $guest('Paga Silva')->id, 'status' => Order::STATUS_PAID]);
        Order::factory()->create(['event_id' => $this->event->id, 'guest_id' => $guest('Espera Souza')->id, 'status' => Order::STATUS_PENDING]);
        Order::factory()->create(['event_id' => $this->event->id, 'guest_id' => $guest('Desistiu Lima')->id, 'status' => Order::STATUS_EXPIRED]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListOrders::class)
            ->assertSee('Paga Silva')
            ->assertDontSee('Espera Souza')
            ->assertDontSee('Desistiu Lima')
            ->set('activeTab', 'aguardando')
            ->assertSee('Espera Souza')
            ->assertDontSee('Paga Silva')
            ->set('activeTab', 'nao-concluidos')
            ->assertSee('Desistiu Lima')
            ->assertSee('Não concluído')
            ->assertDontSee('Espera Souza');
    }

    public function test_a_guest_who_pays_after_the_order_was_closed_still_can(): void
    {
        $order = $this->order(['status' => Order::STATUS_EXPIRED, 'total_amount' => 50]);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(new MPResponse(201, [
            'id' => 333,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => (string) $order->id,
            'payment_method_id' => 'master',
        ])));

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'master', 'token' => 'tok'],
        ])->assertOk();

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }
}
