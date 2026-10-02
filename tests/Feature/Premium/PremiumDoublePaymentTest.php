<?php

namespace Tests\Feature\Premium;

use App\Filament\Resources\Events\Events\Pages\PurchaseEventPremium;
use App\Models\Events\Event;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use App\Services\Premium\PremiumPurchaseUpdateService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

/**
 * The Premium must never be paid twice: one payment open at a time, the
 * open one cancelled before another, and a second approval refunded.
 */
class PremiumDoublePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mercadopago.access_token' => 'PLATFORM-token',
            'services.mercadopago.public_key' => 'PLATFORM-public-key',
            'premium.price' => 39.90,
        ]);

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->create(['user_id' => $this->host->id]);
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
     * @param  array<string, mixed>  $extra
     */
    private function payment(int $id, string $status, int $purchaseId, string $method = 'pix', array $extra = []): MPResponse
    {
        return new MPResponse(200, [
            'id' => $id,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : 'pending_waiting_transfer',
            'external_reference' => "premium-{$purchaseId}",
            'payment_method_id' => $method,
            'transaction_amount' => 39.90,
            ...$extra,
        ]);
    }

    private function openPixPurchase(): PremiumPurchase
    {
        return PremiumPurchase::create([
            'event_id' => $this->event->id,
            'user_id' => $this->host->id,
            'amount' => 39.90,
            'status' => PremiumPurchase::STATUS_PENDING,
            'payment_id' => '1',
            'payment_method' => 'pix',
            'payment_url' => 'https://www.mercadopago.com.br/payments/1/ticket',
        ]);
    }

    public function test_the_pix_lasts_15_minutes_and_its_link_is_kept(): void
    {
        Carbon::setTestNow('2026-10-02 12:00:00');
        $fake = $this->fake($this->payment(1, 'pending', 1, extra: [
            'point_of_interaction' => ['transaction_data' => ['ticket_url' => 'https://www.mercadopago.com.br/payments/1/ticket']],
        ]));

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'pix']);

        $payload = json_decode($fake->requests[0]->getPayload(), true);
        $this->assertSame(now()->addMinutes(15)->format('Y-m-d\TH:i:s.vP'), $payload['date_of_expiration']);
        $this->assertSame('https://www.mercadopago.com.br/payments/1/ticket', PremiumPurchase::first()->payment_url);
    }

    public function test_an_open_payment_takes_the_place_of_the_form(): void
    {
        $this->openPixPurchase();
        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->assertSee('Pagamento aguardando confirmação')
            ->assertSee('Ver o código Pix')
            ->assertSee('Pagar de outro jeito')
            ->assertDontSee('premium-payment-brick', false)
            ->set('payAnotherWay', true)
            ->assertSee('o pagamento em aberto é cancelado')
            ->assertSee('premium-payment-brick', false);
    }

    public function test_an_expired_pix_no_longer_holds_the_form_back(): void
    {
        $this->openPixPurchase();
        PremiumPurchase::query()->update(['created_at' => now()->subMinutes(16)]);
        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->assertDontSee('Pagamento aguardando confirmação')
            ->assertSee('premium-payment-brick', false);
    }

    public function test_paying_another_way_cancels_the_open_payment_first(): void
    {
        $open = $this->openPixPurchase();
        $fake = $this->fake(
            $this->payment(1, 'cancelled', $open->id),          // the cancel
            $this->payment(2, 'approved', 2, 'master'),         // the new one
        );

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'master', 'token' => 'tok'])
            ->assertReturned(['payment_id' => '2', 'status' => PremiumPurchase::STATUS_PAID]);

        $this->assertSame('PUT', $fake->requests[0]->getMethod());
        $this->assertStringEndsWith('/v1/payments/1', $fake->requests[0]->getUri());
        $this->assertSame(PremiumPurchase::STATUS_CANCELLED, $open->fresh()->status);
    }

    public function test_paying_again_is_refused_when_the_open_payment_went_through(): void
    {
        $open = $this->openPixPurchase();
        $fake = $this->fake(
            new MPResponse(400, ['message' => 'Payment already approved']), // the cancel
            $this->payment(1, 'approved', $open->id),                      // re-checking it
        );

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'master', 'token' => 'tok'])
            ->assertReturned(['error' => 'Este evento já tem todos os recursos Premium.']);

        $this->assertCount(2, $fake->requests);
        $this->assertSame(PremiumPurchase::STATUS_PAID, $open->fresh()->status);
        $this->assertSame(1, PremiumPurchase::count());
    }

    public function test_a_second_purchase_paid_after_the_first_is_refunded(): void
    {
        $first = $this->openPixPurchase();
        $second = PremiumPurchase::create([
            'event_id' => $this->event->id,
            'user_id' => $this->host->id,
            'amount' => 39.90,
            'status' => PremiumPurchase::STATUS_PENDING,
            'payment_id' => '2',
        ]);

        $fake = $this->fake(
            $this->payment(1, 'approved', $first->id),
            $this->payment(2, 'approved', $second->id),
            new MPResponse(201, ['id' => 77, 'payment_id' => 2]),
        );

        $service = app(PremiumPurchaseUpdateService::class);
        $service->execute('1');
        $service->execute('2');

        $this->assertSame(PremiumPurchase::STATUS_PAID, $first->fresh()->status);
        $this->assertSame(PremiumPurchase::STATUS_CANCELLED, $second->fresh()->status);
        $this->assertStringEndsWith('/v1/payments/2/refunds', $fake->requests[2]->getUri());
        $this->assertStringContainsString('PLATFORM-token', implode(' ', $fake->requests[2]->getHeaders()));
        $this->assertTrue($first->featureGrants()->exists());
        $this->assertFalse($second->featureGrants()->exists());
    }

    public function test_the_host_can_cancel_the_open_pix(): void
    {
        $open = $this->openPixPurchase();
        $fake = $this->fake($this->payment(1, 'cancelled', $open->id));

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('cancelPayment')->schemaComponent('pendingPayment', schema: 'content'))
            ->assertNotified('Pagamento cancelado')
            ->assertRedirect(PurchaseEventPremium::getUrl(['record' => $this->event]));

        // Reloaded, the page offers the form again.
        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->assertDontSee('Pagamento aguardando confirmação')
            ->assertSee('premium-payment-brick', false);

        $this->assertSame('PUT', $fake->requests[0]->getMethod());
        $this->assertStringEndsWith('/v1/payments/1', $fake->requests[0]->getUri());
        $this->assertSame(PremiumPurchase::STATUS_CANCELLED, $open->fresh()->status);
    }

    public function test_a_pix_already_paid_is_not_cancelled_but_confirmed(): void
    {
        $open = $this->openPixPurchase();
        $this->fake(
            new MPResponse(400, ['message' => 'Payment already approved']), // the cancel
            $this->payment(1, 'approved', $open->id),                      // checking it
        );

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('cancelPayment')->schemaComponent('pendingPayment', schema: 'content'))
            ->assertNotified('Pagamento confirmado — Premium ativado!');

        $this->assertSame(PremiumPurchase::STATUS_PAID, $open->fresh()->status);
    }
}
