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

class OrderPaymentStoreControllerTest extends TestCase
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

    public function test_it_creates_the_payment_using_the_server_computed_amount(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event);

        $fake = new FakeMercadoPagoHttpClient(
            new MPResponse(201, [
                'id' => 999,
                'status' => 'approved',
                'status_detail' => 'accredited',
                'external_reference' => (string) $order->id,
                'payment_method_id' => 'pix',
            ]),
        );
        MercadoPagoConfig::setHttpClient($fake);

        $response = $this->postJson("/{$event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => [
                'payment_method_id' => 'master',
                'token' => 'card-token-abc',
                'issuer_id' => '310',
                'installments' => 1,
                'payer' => [
                    'email' => 'maria@example.com',
                    'identification' => ['type' => 'CPF', 'number' => '12345678909'],
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['id' => '999', 'status' => 'approved']);

        $payload = json_decode($fake->requests[0]->getPayload(), true);
        $this->assertEquals(150.0, $payload['transaction_amount']);
        $this->assertSame((string) $order->id, $payload['external_reference']);

        // These only ever have a rule for `payment_method_id`; regressing to
        // `$request->validated('formData')` would silently drop the rest.
        $this->assertSame('card-token-abc', $payload['token']);
        $this->assertSame('310', $payload['issuer_id']);
        $this->assertSame('12345678909', $payload['payer']['identification']['number']);

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_it_rejects_paying_an_order_that_is_not_pending(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event);
        $order->update(['status' => Order::STATUS_PAID]);

        $fake = new FakeMercadoPagoHttpClient;
        MercadoPagoConfig::setHttpClient($fake);

        $response = $this->postJson("/{$event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'pix'],
        ]);

        $response->assertStatus(409);
        $this->assertCount(0, $fake->requests);
    }

    public function test_it_throttles_repeated_requests_from_the_same_ip(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($event);
        $order->update(['status' => Order::STATUS_PAID]);

        $payload = ['formData' => ['payment_method_id' => 'pix']];

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/{$event->slug}/orders/{$order->id}/mercadopago-payment", $payload)
                ->assertStatus(409);
        }

        $this->postJson("/{$event->slug}/orders/{$order->id}/mercadopago-payment", $payload)
            ->assertStatus(429);
    }

    public function test_it_rejects_an_order_that_does_not_belong_to_the_event(): void
    {
        $event = Event::factory()->withMercadoPago()->create();
        $otherEvent = Event::factory()->withMercadoPago()->create();
        $order = $this->createPendingOrder($otherEvent);

        $response = $this->postJson("/{$event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'pix'],
        ]);

        $response->assertNotFound();
    }
}
