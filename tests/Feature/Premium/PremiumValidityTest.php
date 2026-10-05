<?php

namespace Tests\Feature\Premium;

use App\Enums\Premium\Feature;
use App\Filament\Pages\PlatformSettingsPage;
use App\Filament\Resources\Events\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Events\Pages\PurchaseEventPremium;
use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use App\Models\Premium\PremiumPurchase;
use App\Models\User;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Net\MPDefaultHttpClient;
use MercadoPago\Net\MPResponse;
use Tests\Support\FakeMercadoPagoHttpClient;
use Tests\TestCase;

/**
 * The Premium is bought for an event on a date and ends some days after
 * it, so one purchase can't be reused, edit after edit, for another party.
 */
class PremiumValidityTest extends TestCase
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
            'premium.grace_days' => 60,
            'premium.date_window_days' => 90,
        ]);

        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(12, 0));

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->withMercadoPago('HOST-token')->create([
            'user_id' => $this->host->id,
            'event_date' => '2026-10-30',
        ]);
    }

    protected function tearDown(): void
    {
        MercadoPagoConfig::setHttpClient(new MPDefaultHttpClient);

        parent::tearDown();
    }

    private function buyPremium(float $amount = 39.90): void
    {
        MercadoPagoConfig::setHttpClient(new FakeMercadoPagoHttpClient(new MPResponse(201, [
            'id' => 555,
            'status' => 'approved',
            'status_detail' => 'accredited',
            'external_reference' => 'premium-1',
            'payment_method_id' => 'master',
            'transaction_amount' => $amount,
        ])));

        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->call('pay', ['payment_method_id' => 'master', 'token' => 'card-token'])
            ->assertReturned(['payment_id' => '555', 'status' => PremiumPurchase::STATUS_PAID]);
    }

    private function editDate(string $date): Testable
    {
        return Livewire::test(EditEvent::class, ['record' => $this->event->getRouteKey()])
            ->fillForm(['event_date' => $date])
            ->call('save');
    }

    public function test_the_premium_lasts_until_the_grace_period_after_the_event(): void
    {
        $this->buyPremium();

        $this->assertSame('2026-10-30', PremiumPurchase::first()->event_date->toDateString());

        // 30/10 + 60 days = 29/12, until the end of the day in Brasília.
        $this->travelTo(now()->setDate(2026, 12, 29)->setTime(23, 0));
        $this->assertTrue($this->event->fresh()->hasFeature(Feature::Payments));

        $this->travelTo(now()->setDate(2026, 12, 30)->setTime(12, 0));
        $event = $this->event->fresh();
        $this->assertFalse($event->hasFeature(Feature::Payments));
        $this->assertFalse($event->hasFeature(Feature::GuestList));
        // The host's own features aren't tied to the event.
        $this->assertTrue($this->host->fresh()->hasFeature(Feature::Contacts));
    }

    public function test_the_host_moves_the_date_within_the_window_and_the_premium_follows(): void
    {
        $this->buyPremium();

        $this->editDate('2026-11-20')->assertHasNoFormErrors();

        $this->travelTo(now()->setDate(2027, 1, 18)->setTime(12, 0));
        $this->assertTrue($this->event->fresh()->hasFeature(Feature::Payments));

        $this->travelTo(now()->setDate(2027, 1, 20)->setTime(12, 0));
        $this->assertFalse($this->event->fresh()->hasFeature(Feature::Payments));
    }

    public function test_the_host_cannot_move_the_date_beyond_the_window(): void
    {
        $this->buyPremium();

        $this->editDate('2027-10-30')->assertHasFormErrors(['event_date']);
        $this->editDate('')->assertHasFormErrors(['event_date']);

        $this->assertSame('2026-10-30', $this->event->fresh()->event_date->toDateString());
    }

    public function test_moving_the_date_never_brings_back_an_ended_premium(): void
    {
        $this->buyPremium();

        $this->travelTo(now()->setDate(2027, 1, 10)->setTime(12, 0));
        $this->editDate('2027-01-20')->assertHasNoFormErrors();

        $this->assertFalse($this->event->fresh()->hasFeature(Feature::Payments));
    }

    public function test_the_admin_moves_the_date_anywhere_and_the_premium_goes_with_it(): void
    {
        $this->buyPremium();
        $this->travelTo(now()->setDate(2027, 1, 10)->setTime(12, 0));

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->editDate('2027-10-30')->assertHasNoFormErrors();

        $this->assertSame('2027-10-30', PremiumPurchase::first()->event_date->toDateString());
        $this->assertTrue($this->event->fresh()->hasFeature(Feature::Payments));
    }

    public function test_the_premium_needs_a_date_that_has_not_passed(): void
    {
        $this->event->update(['event_date' => null]);
        $this->actingAs($this->host);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->assertSee('Informe a data do evento antes de comprar o Premium')
            ->call('pay', ['payment_method_id' => 'master', 'token' => 'card-token'])
            ->assertReturned(['error' => 'Informe a data do evento antes de comprar o Premium: ele vale para essa data.']);

        $this->event->update(['event_date' => '2026-10-01']);

        Livewire::test(PurchaseEventPremium::class, ['record' => $this->event->getRouteKey()])
            ->assertSee('A data deste evento já passou');

        $this->assertSame(0, PremiumPurchase::count());
    }

    public function test_after_the_premium_the_approved_messages_stay_but_no_new_ones(): void
    {
        $this->buyPremium();
        GuestMessage::factory()->approved()->create(['event_id' => $this->event->id, 'message' => 'Felicidades!']);
        $this->event->update(['is_published' => true]);

        $this->travelTo(now()->setDate(2027, 1, 10)->setTime(12, 0));

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.has_guestbook', false)
            ->where('event.accepts_online_gifts', false)
            ->where('messages.0.message', 'Felicidades!'));
    }

    public function test_the_admin_sets_the_price_and_the_grace_period(): void
    {
        $this->buyPremium();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(PlatformSettingsPage::class)
            ->fillForm(['premium_price' => 49.90, 'premium_grace_days' => 30, 'premium_date_window_days' => 15])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(49.90, PlatformSettings::premiumPrice());
        $this->assertSame(15, PlatformSettings::premiumDateWindowDays());

        // A shorter grace period ends the Premium that's already been bought sooner.
        $this->travelTo(now()->setDate(2026, 12, 1)->setTime(12, 0));
        $this->assertFalse($this->event->fresh()->hasFeature(Feature::Payments));
    }

    public function test_only_the_admin_opens_the_settings(): void
    {
        $this->actingAs($this->host)->get(PlatformSettingsPage::getUrl())->assertForbidden();

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get(PlatformSettingsPage::getUrl())->assertOk();
    }
}
