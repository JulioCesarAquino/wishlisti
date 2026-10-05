<?php

namespace Tests\Feature\Orders;

use App\Enums\Premium\Feature;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Services\Orders\OrderPaymentUpdateService;
use App\Support\MercadoPagoPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

/**
 * A gift must never be paid twice: Pix codes expire, a retry cancels the
 * payment left open, a stale payment can't undo a paid order, and a second
 * approved payment is refunded.
 */
class OrderDoublePaymentTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Guest $guest;

    private EventProduct $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $this->guest = $this->event->guests()->create([
            'name' => 'Maria',
            'whatsapp' => '5511999999999',
            'identifier' => (string) Str::uuid(),
        ]);
        $this->product = EventProduct::factory()->create([
            'event_id' => $this->event->id,
            'price' => 150.00,
            'quantity_total' => 1,
            'quantity_purchased' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);

        parent::tearDown();
    }

    private function fake(MPResponse ...$responses): FakeMercadoPagoHttpClient
    {
        $fake = new FakeMercadoPagoHttpClient(...$responses);
        MercadoPagoConfig::setHttpClient($fake);

        return $fake;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(array $attributes = []): Order
    {
        $order = Order::create([
            'event_id' => $this->event->id,
            'guest_id' => $this->guest->id,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 150.00,
            ...$attributes,
        ]);
        $order->items()->create(['event_product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 150.00]);

        return $order;
    }

    private function payment(int $id, string $status, Order $order, string $method = 'pix'): MPResponse
    {
        return new MPResponse(200, [
            'id' => $id,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : 'pending_waiting_transfer',
            'external_reference' => (string) $order->id,
            'payment_method_id' => $method,
        ]);
    }

    public function test_a_pix_code_expires_in_15_minutes(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $order = $this->order();
        $fake = $this->fake($this->payment(1, 'pending', $order));

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'pix', 'payer' => ['email' => 'maria@example.com']],
        ])->assertOk();

        $payload = json_decode($fake->requests[0]->getPayload(), true);
        $this->assertSame(now()->addMinutes(MercadoPagoPayments::PIX_EXPIRATION_MINUTES)->format('Y-m-d\TH:i:s.vP'), $payload['date_of_expiration']);
        $this->assertSame(15, MercadoPagoPayments::PIX_EXPIRATION_MINUTES);
    }

    public function test_card_payments_get_no_expiration(): void
    {
        $order = $this->order();
        $fake = $this->fake($this->payment(1, 'approved', $order, 'master'));

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'master', 'token' => 'tok'],
        ])->assertOk();

        $this->assertArrayNotHasKey('date_of_expiration', json_decode($fake->requests[0]->getPayload(), true));
    }

    public function test_a_stale_payment_expiring_does_not_undo_a_paid_order(): void
    {
        $order = $this->order(['status' => Order::STATUS_PAID, 'payment_id' => '2', 'paid_at' => now()]);
        $this->product->update(['quantity_purchased' => 1]);

        // The Pix left unpaid (payment 1) expires after the card (2) went through.
        $this->fake($this->payment(1, 'cancelled', $order));
        app(OrderPaymentUpdateService::class)->execute($this->event, '1');

        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame('2', $order->fresh()->payment_id);
        $this->assertSame(1, $this->product->fresh()->quantity_purchased);
    }

    public function test_a_second_approved_payment_is_refunded(): void
    {
        $order = $this->order(['status' => Order::STATUS_PAID, 'payment_id' => '2', 'paid_at' => now()]);
        $this->product->update(['quantity_purchased' => 1]);

        $fake = $this->fake($this->payment(1, 'approved', $order), new MPResponse(201, ['id' => 77, 'payment_id' => 1]));
        app(OrderPaymentUpdateService::class)->execute($this->event, '1');

        $this->assertSame('POST', $fake->requests[1]->getMethod());
        $this->assertStringEndsWith('/v1/payments/1/refunds', $fake->requests[1]->getUri());
        $this->assertSame('2', $order->fresh()->payment_id);
        $this->assertSame(1, $this->product->fresh()->quantity_purchased);
    }

    public function test_paying_the_same_order_again_cancels_the_payment_left_open(): void
    {
        $order = $this->order(['payment_id' => '1']);

        $fake = $this->fake(
            $this->payment(1, 'cancelled', $order),            // the cancel
            $this->payment(2, 'approved', $order, 'master'),   // the new payment
        );

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'master', 'token' => 'tok'],
        ])->assertOk();

        $this->assertSame('PUT', $fake->requests[0]->getMethod());
        $this->assertStringEndsWith('/v1/payments/1', $fake->requests[0]->getUri());
        $this->assertSame('cancelled', json_decode($fake->requests[0]->getPayload(), true)['status']);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame('2', $order->fresh()->payment_id);
    }

    public function test_paying_again_is_refused_when_the_open_payment_went_through(): void
    {
        $order = $this->order(['payment_id' => '1']);

        $fake = $this->fake(
            new MPResponse(400, ['message' => 'Payment already approved']), // the cancel
            $this->payment(1, 'approved', $order),                         // re-checking it
        );

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'master', 'token' => 'tok'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['formData' => 'Este presente já foi pago. Obrigado!']);

        $this->assertCount(2, $fake->requests);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_paying_again_works_when_the_open_payment_is_unknown_to_the_current_account(): void
    {
        // Made with other credentials, before the host changed them.
        $order = $this->order(['payment_id' => '1']);

        $fake = $this->fake(
            new MPResponse(404, ['message' => 'Payment not found', 'error' => 'not_found', 'status' => 404]), // the cancel
            $this->payment(2, 'approved', $order, 'master'),                                                // the new payment
        );

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'master', 'token' => 'tok'],
        ])->assertOk();

        $this->assertCount(2, $fake->requests);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame('2', $order->fresh()->payment_id);
    }

    private function orderAgain(): TestResponse
    {
        return $this->withCredentials()
            ->withCookie(Guest::cookieName($this->event), $this->guest->identifier)
            ->postJson("/{$this->event->slug}/orders", [
                'guest' => ['name' => 'Maria', 'whatsapp' => '5511999999999'],
                'items' => [['event_product_id' => $this->product->id, 'quantity' => 1]],
            ]);
    }

    public function test_opening_the_checkout_again_resumes_the_same_order(): void
    {
        // The guest opened the checkout, then reloaded the page and did it again.
        $previous = $this->order();
        $fake = $this->fake();

        $this->orderAgain()->assertOk()->assertJsonPath('order.id', $previous->id)->assertJsonPath('order.payment_id', null);

        $this->assertCount(0, $fake->requests);
        $this->assertSame(1, Order::count());
        $this->assertSame(Order::STATUS_PENDING, $previous->fresh()->status);
    }

    public function test_a_pix_left_open_comes_back_instead_of_a_new_order(): void
    {
        $previous = $this->order(['payment_id' => '1']);
        $fake = $this->fake($this->payment(1, 'pending', $previous)); // asked how it's doing

        $this->orderAgain()
            ->assertOk()
            ->assertJsonPath('order.id', $previous->id)
            ->assertJsonPath('order.payment_id', '1')
            ->assertJsonPath('order.status', Order::STATUS_PENDING);

        // Only looked up, never cancelled: it's the same Pix to pay.
        $this->assertCount(1, $fake->requests);
        $this->assertSame('GET', $fake->requests[0]->getMethod());
        $this->assertSame(1, Order::count());
    }

    public function test_an_expired_pix_starts_a_new_order(): void
    {
        $previous = $this->order(['payment_id' => '1']);
        $this->fake($this->payment(1, 'cancelled', $previous));

        $this->orderAgain()->assertOk()->assertJsonPath('order.payment_id', null);

        $this->assertSame(Order::STATUS_CANCELLED, $previous->fresh()->status);
        $this->assertSame(2, Order::count());
    }

    public function test_a_pix_paid_meanwhile_comes_back_paid(): void
    {
        $previous = $this->order(['payment_id' => '1']);
        $this->fake($this->payment(1, 'approved', $previous));

        $this->orderAgain()
            ->assertOk()
            ->assertJsonPath('order.id', $previous->id)
            ->assertJsonPath('order.status', Order::STATUS_PAID);

        $this->assertSame(1, Order::count());
    }

    public function test_the_same_gift_paid_just_now_is_not_charged_again(): void
    {
        // Paid the Pix in the bank's app; the gift is still in the cart.
        $paid = $this->order(['payment_id' => '1', 'status' => Order::STATUS_PAID, 'paid_at' => now()->subMinutes(10)]);
        $fake = $this->fake();

        $this->orderAgain()
            ->assertOk()
            ->assertJsonPath('order.id', $paid->id)
            ->assertJsonPath('order.status', Order::STATUS_PAID);

        $this->assertCount(0, $fake->requests);
        $this->assertSame(1, Order::count());

        // Long after, giving the same gift again is a new gift.
        $paid->update(['paid_at' => now()->subDay()]);
        $this->orderAgain()->assertOk()->assertJsonPath('order.status', Order::STATUS_PENDING);
        $this->assertSame(2, Order::count());
    }

    public function test_another_gift_closes_the_order_with_no_payment_and_leaves_the_one_with_a_pix(): void
    {
        $noPayment = $this->order();
        $withPix = $this->order(['payment_id' => '1']);
        $other = EventProduct::factory()->create(['event_id' => $this->event->id, 'price' => 80.00]);
        $fake = $this->fake();

        $this->withCredentials()
            ->withCookie(Guest::cookieName($this->event), $this->guest->identifier)
            ->postJson("/{$this->event->slug}/orders", [
                'guest' => ['name' => 'Maria', 'whatsapp' => '5511999999999'],
                'items' => [['event_product_id' => $other->id, 'quantity' => 1]],
            ])->assertOk();

        $this->assertCount(0, $fake->requests);
        $this->assertSame(Order::STATUS_EXPIRED, $noPayment->fresh()->status);
        $this->assertSame(Order::STATUS_PENDING, $withPix->fresh()->status);
    }

    public function test_pix_still_works_if_mercado_pago_refuses_the_expiration(): void
    {
        $order = $this->order();
        $fake = $this->fake(
            new MPResponse(400, ['message' => 'Invalid date_of_expiration', 'cause' => [['description' => 'date_of_expiration is invalid']]]),
            $this->payment(1, 'pending', $order),
        );

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'pix'],
        ])->assertOk();

        $this->assertArrayHasKey('date_of_expiration', json_decode($fake->requests[0]->getPayload(), true));
        $this->assertArrayNotHasKey('date_of_expiration', json_decode($fake->requests[1]->getPayload(), true));
        $this->assertSame('1', $order->fresh()->payment_id);
    }

    public function test_other_refusals_are_not_retried(): void
    {
        $order = $this->order();
        $fake = $this->fake(new MPResponse(400, ['message' => 'Invalid payer email']));

        $this->postJson("/{$this->event->slug}/orders/{$order->id}/mercadopago-payment", [
            'formData' => ['payment_method_id' => 'pix'],
        ])->assertUnprocessable();

        $this->assertCount(1, $fake->requests);
    }
}
