<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Orders\Orders\Pages\ListOrders;
use App\Filament\Resources\Premium\PremiumPurchases\Pages\ListPremiumPurchases;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

/**
 * The admin can see who paid — asked of Mercado Pago when needed, never
 * stored, and each lookup audited.
 */
class PaymentPayerLookupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Event $event;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mercadopago.access_token' => 'PLATFORM-token',
            'services.mercadopago.public_key' => 'PLATFORM-public-key',
        ]);

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->event = Event::factory()->withMercadoPago('HOST-token')->create();
        $guest = $this->event->guests()->create(['name' => 'Presente anônimo', 'identifier' => (string) Str::uuid()]);
        $this->order = Order::factory()->create([
            'event_id' => $this->event->id,
            'guest_id' => $guest->id,
            'status' => Order::STATUS_PAID,
            'is_anonymous' => true,
            'payment_id' => '123',
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

    private function cardPayment(): MPResponse
    {
        return new MPResponse(200, [
            'id' => 123,
            'status' => 'approved',
            'payment_method_id' => 'master',
            'payer' => ['email' => 'segredo@example.com', 'identification' => ['type' => 'CPF', 'number' => '52998224725']],
            'card' => ['cardholder' => ['name' => 'MARIA SEGREDO', 'identification' => ['type' => 'CPF', 'number' => '52998224725']]],
        ]);
    }

    public function test_the_admin_sees_who_paid_an_anonymous_gift(): void
    {
        $fake = $this->fake($this->cardPayment());
        $this->actingAs($this->admin);

        Livewire::test(ListOrders::class)
            ->mountAction(TestAction::make('viewPayer')->table($this->order))
            ->assertMountedActionModalSee(['MARIA SEGREDO', 'segredo@example.com', 'CPF 52998224725', 'não guardados no Wishlisti']);

        // On the host's account, where the gift was paid.
        $this->assertStringEndsWith('/v1/payments/123', $fake->requests[0]->getUri());
        $this->assertStringContainsString('HOST-token', implode(' ', $fake->requests[0]->getHeaders()));
    }

    public function test_each_lookup_is_audited_without_the_payers_data(): void
    {
        $this->fake($this->cardPayment());
        $this->actingAs($this->admin);

        Livewire::test(ListOrders::class)->mountAction(TestAction::make('viewPayer')->table($this->order));

        $activity = Activity::where('event', 'payer_lookup')->sole();

        $this->assertSame('consultou os dados do pagador', $activity->description);
        $this->assertTrue($activity->causer->is($this->admin));
        $this->assertTrue($activity->subject->is($this->order));
        $this->assertStringNotContainsString('segredo@example.com', json_encode($activity->getAttributes()));
        $this->assertStringNotContainsString('52998224725', json_encode($activity->getAttributes()));
    }

    public function test_nothing_is_asked_of_mercado_pago_just_by_listing(): void
    {
        $fake = $this->fake();
        $this->actingAs($this->admin);

        Livewire::test(ListOrders::class)->assertSee('Ver pagador');

        $this->assertCount(0, $fake->requests);
        $this->assertSame(0, Activity::where('event', 'payer_lookup')->count());
    }

    public function test_hosts_cannot_look_up_the_payer(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $this->event->update(['user_id' => $host->id]);
        $this->actingAs($host);

        Livewire::test(ListOrders::class)
            ->assertActionHidden(TestAction::make('viewPayer')->table($this->order));
    }

    public function test_it_says_so_when_mercado_pago_cannot_tell(): void
    {
        $this->fake(new MPResponse(401, ['message' => 'invalid access token']));
        $this->actingAs($this->admin);

        Livewire::test(ListOrders::class)
            ->mountAction(TestAction::make('viewPayer')->table($this->order))
            ->assertMountedActionModalSee('Não foi possível consultar o Mercado Pago agora');
    }

    public function test_premium_payers_are_looked_up_on_the_platform_account(): void
    {
        $purchase = PremiumPurchase::create([
            'event_id' => $this->event->id,
            'user_id' => $this->event->user_id,
            'amount' => 39.90,
            'status' => PremiumPurchase::STATUS_PAID,
            'payment_id' => '123',
        ]);
        $fake = $this->fake($this->cardPayment());
        $this->actingAs($this->admin);

        Livewire::test(ListPremiumPurchases::class)
            ->mountAction(TestAction::make('viewPayer')->table($purchase))
            ->assertMountedActionModalSee('segredo@example.com');

        $this->assertStringContainsString('PLATFORM-token', implode(' ', $fake->requests[0]->getHeaders()));
    }
}
