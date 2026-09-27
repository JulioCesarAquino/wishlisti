<?php

namespace Tests\Feature\Orders;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use App\Services\Orders\OrderPaymentUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

class OrderPaymentUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);

        parent::tearDown();
    }

    private function createPendingOrder(Event $event, int $quantity = 2): Order
    {
        $guest = $event->guests()->create([
            'name' => 'Maria Teste',
            'whatsapp' => '5511999999999',
            'identifier' => (string) Str::uuid(),
        ]);

        $product = EventProduct::factory()->create([
            'event_id' => $event->id,
            'price' => 150.00,
            'quantity_total' => 5,
            'quantity_purchased' => 0,
        ]);

        $order = Order::create([
            'event_id' => $event->id,
            'guest_id' => $guest->id,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 150.00 * $quantity,
        ]);

        $order->items()->create([
            'event_product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => 150.00,
        ]);

        return $order;
    }

    public function test_an_approved_payment_marks_the_order_as_paid_and_deducts_stock(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event, quantity: 2);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(
            new MPResponse(200, [
                'id' => 999,
                'status' => 'approved',
                'external_reference' => (string) $order->id,
                'payment_method_id' => 'pix',
            ]),
        ));

        app(OrderPaymentUpdateService::class)->execute($event, '999');

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame('999', $order->payment_id);
        $this->assertSame('pix', $order->payment_method);
        $this->assertNotNull($order->paid_at);

        $product = $order->items->first()->eventProduct;
        $this->assertSame(2, $product->fresh()->quantity_purchased);
    }

    public function test_repeated_notifications_for_the_same_payment_do_not_double_count_stock(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event, quantity: 2);

        $approvedResponse = fn () => new MPResponse(200, [
            'id' => 999,
            'status' => 'approved',
            'external_reference' => (string) $order->id,
            'payment_method_id' => 'pix',
        ]);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient($approvedResponse(), $approvedResponse()));

        $service = app(OrderPaymentUpdateService::class);
        $service->execute($event, '999');
        $service->execute($event, '999');

        $product = $order->items->first()->eventProduct;
        $this->assertSame(2, $product->fresh()->quantity_purchased);
    }

    public function test_a_refund_after_payment_restocks_the_items(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event, quantity: 2);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(
            new MPResponse(200, [
                'id' => 999,
                'status' => 'approved',
                'external_reference' => (string) $order->id,
                'payment_method_id' => 'pix',
            ]),
        ));
        app(OrderPaymentUpdateService::class)->execute($event, '999');

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(
            new MPResponse(200, [
                'id' => 999,
                'status' => 'refunded',
                'external_reference' => (string) $order->id,
                'payment_method_id' => 'pix',
            ]),
        ));
        app(OrderPaymentUpdateService::class)->execute($event, '999');

        $order->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);

        $product = $order->items->first()->eventProduct;
        $this->assertSame(0, $product->fresh()->quantity_purchased);
    }

    public function test_it_does_nothing_when_the_host_has_not_configured_mercado_pago(): void
    {
        $event = Event::factory()->create();
        $order = $this->createPendingOrder($event);

        $fake = new FakeMercadoPagoHttpClient;
        MercadoPagoConfig::setHttpClient($fake);

        app(OrderPaymentUpdateService::class)->execute($event, '999');

        $this->assertCount(0, $fake->requests);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }
}
