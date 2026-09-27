<?php

namespace Tests\Feature\Orders;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

class OrderPaymentWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);

        parent::tearDown();
    }

    private function createPendingOrder(Event $event): Order
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
            'total_amount' => 150.00,
        ]);

        $order->items()->create([
            'event_product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 150.00,
        ]);

        return $order;
    }

    public function test_a_payment_notification_updates_the_matching_order(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event);

        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(
            new MPResponse(200, [
                'id' => 999,
                'status' => 'approved',
                'external_reference' => (string) $order->id,
                'payment_method_id' => 'pix',
            ]),
        ));

        $response = $this->postJson("/{$event->slug}/orders/mercadopago-webhook?type=payment&data.id=999");

        $response->assertNoContent();
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_it_ignores_notifications_that_are_not_about_payments(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event);

        $fake = new FakeMercadoPagoHttpClient;
        MercadoPagoConfig::setHttpClient($fake);

        $response = $this->postJson("/{$event->slug}/orders/mercadopago-webhook?type=merchant_order&data.id=999");

        $response->assertNoContent();
        $this->assertCount(0, $fake->requests);
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }
}
