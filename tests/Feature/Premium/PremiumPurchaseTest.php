<?php

namespace Tests\Feature\Premium;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Contacts\Contacts\ContactResource;
use App\Filament\Resources\Events\Events\Pages\EditEventRsvp;
use App\Filament\Resources\Events\Events\Pages\PurchaseEventPremium;
use App\Filament\Resources\Premium\PremiumPurchases\PremiumPurchaseResource;
use App\Models\Events\Event;
use App\Models\Premium\FeatureGrant;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

class PremiumPurchaseTest extends TestCase
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
        $this->event = Event::factory()->withMercadoPago('HOST-token')->create(['user_id' => $this->host->id]);
    }

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  ...$payments
     */
    private function fakeMercadoPago(array ...$payments): FakeMercadoPagoHttpClient
    {
        $fake = new FakeMercadoPagoHttpClient(...array_map(fn (array $payment) => new MPResponse(201, $payment), $payments));
        MercadoPagoConfig::setHttpClient($fake);

        return $fake;
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(string $status, int $purchaseId = 1, float $amount = 39.90): array
    {
        return [
            'id' => 555,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : 'pending_waiting_transfer',
            'external_reference' => "premium-{$purchaseId}",
            'payment_method_id' => $status === 'approved' ? 'master' : 'pix',
            'transaction_amount' => $amount,
        ];
    }

    public function test_the_premium_page_sells_the_plan(): void
    {
        $this->actingAs($this->host);

        $this->get(PurchaseEventPremium::getUrl(['record' => $this->event]))
            ->assertOk()
            ->assertSee('Wishlisti Premium por R$ 39,90')
            ->assertSee(Feature::Payments->headline())
            ->assertSee(Feature::GuestList->headline())
            ->assertSee(Feature::FullGiftList->headline())
            ->assertSee(Feature::Contacts->headline())
            ->assertSee('premium-payment-brick');
    }

    public function test_the_checkout_is_hidden_when_the_platform_account_is_not_configured(): void
    {
        config(['services.mercadopago.access_token' => null]);

        $this->actingAs($this->host);

        $this->get(PurchaseEventPremium::getUrl(['record' => $this->event]))
            ->assertSee('Pagamento indisponível no momento')
            ->assertDontSee('premium-payment-brick');
    }

    public function test_an_approved_payment_unlocks_the_features_right_away(): void
    {
        $fake = $this->fakeMercadoPago($this->payment('approved'));

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'master', 'token' => 'card-token', 'transaction_amount' => 1])
            ->assertReturned(['payment_id' => '555', 'status' => PremiumPurchase::STATUS_PAID])
            ->assertNotified('Premium ativado!');

        // Charged on the platform account, for the configured price — never
        // on the host's account or for an amount sent by the browser.
        $request = $fake->requests[0];
        $this->assertStringContainsString('PLATFORM-token', implode(' ', $request->getHeaders()));
        $payload = json_decode($request->getPayload(), true);
        $this->assertEquals(39.90, $payload['transaction_amount']);
        $this->assertSame('premium-1', $payload['external_reference']);

        $event = $this->event->fresh();
        $this->assertTrue($event->hasFeature(Feature::Payments));
        $this->assertTrue($event->hasFeature(Feature::GuestList));
        $this->assertTrue($this->host->fresh()->hasFeature(Feature::Contacts));

        $grant = $event->featureGrants->first();
        $this->assertSame(FeatureGrant::SOURCE_PURCHASE, $grant->source);
        $this->assertSame(1, $grant->premium_purchase_id);
    }

    public function test_a_pix_payment_unlocks_the_features_once_the_webhook_confirms_it(): void
    {
        $this->fakeMercadoPago($this->payment('pending'), $this->payment('approved'), $this->payment('approved'));

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'pix'])
            ->assertReturned(['payment_id' => '555', 'status' => PremiumPurchase::STATUS_PENDING]);

        $this->assertFalse($this->event->fresh()->hasFeature(Feature::GuestList));

        $this->postJson('/webhooks/mercadopago/premium', ['type' => 'payment', 'data' => ['id' => '555']])->assertNoContent();
        // Mercado Pago retries notifications; applying it twice is harmless.
        $this->postJson('/webhooks/mercadopago/premium', ['type' => 'payment', 'data' => ['id' => '555']])->assertNoContent();

        $this->assertTrue($this->event->fresh()->hasFeature(Feature::GuestList));
        $this->assertSame(PremiumPurchase::STATUS_PAID, PremiumPurchase::first()->status);
        $this->assertSame(count(config('premium.event_features')) + count(config('premium.host_features')), FeatureGrant::count());
    }

    public function test_a_refund_revokes_only_what_the_purchase_unlocked(): void
    {
        $this->event->grantFeature(Feature::Payments);

        $this->fakeMercadoPago($this->payment('approved'), $this->payment('refunded'));

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'master']);

        $this->assertTrue($this->event->fresh()->hasFeature(Feature::GuestList));

        $this->postJson('/webhooks/mercadopago/premium', ['type' => 'payment', 'data' => ['id' => '555']]);

        $event = $this->event->fresh();
        $this->assertFalse($event->hasFeature(Feature::GuestList));
        $this->assertFalse($this->host->fresh()->hasFeature(Feature::Contacts));
        // Unlocked by the admin before the purchase: stays.
        $this->assertTrue($event->hasFeature(Feature::Payments));
        $this->assertSame(PremiumPurchase::STATUS_CANCELLED, PremiumPurchase::first()->status);
    }

    public function test_a_payment_below_the_price_unlocks_nothing(): void
    {
        $this->fakeMercadoPago($this->payment('approved', amount: 1.00));

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'master']);

        $this->assertFalse($this->event->fresh()->hasFeature(Feature::GuestList));
    }

    public function test_an_event_that_already_has_everything_is_not_charged_again(): void
    {
        $this->event->syncFeatures(config('premium.event_features'));
        $this->host->grantFeature(Feature::Contacts);
        $fake = $this->fakeMercadoPago();

        $this->actingAs($this->host);

        $this->get(PurchaseEventPremium::getUrl(['record' => $this->event]))
            ->assertSee('Este evento é Premium')
            ->assertDontSee('premium-payment-brick');

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'master'])
            ->assertReturned(['error' => 'Este evento já tem todos os recursos Premium.']);

        $this->assertSame([], $fake->requests);
        $this->assertSame(0, PremiumPurchase::count());
    }

    public function test_a_host_cannot_buy_for_someone_elses_event(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get(PurchaseEventPremium::getUrl(['record' => $this->event]))->assertNotFound();
    }

    public function test_locked_features_lead_to_the_premium_page(): void
    {
        $this->actingAs($this->host);

        $premiumUrl = PurchaseEventPremium::getUrl(['record' => $this->event]);

        $this->get(EditEventRsvp::getUrl(['record' => $this->event]))
            ->assertSee('Conhecer o Premium')
            ->assertSee($premiumUrl);

        $this->get(ContactResource::getUrl('index'))
            ->assertSee('Conhecer o Premium')
            ->assertSee($premiumUrl);
    }

    public function test_only_the_admin_sees_the_premium_sales(): void
    {
        $this->actingAs($this->host);
        $this->get(PremiumPurchaseResource::getUrl('index'))->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(PremiumPurchaseResource::getUrl('index'))->assertOk();
    }
}
