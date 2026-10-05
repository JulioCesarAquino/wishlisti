<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Events\Pages\EditEventPayment;
use App\Filament\Resources\Events\Events\Pages\ManageEventMessages;
use App\Filament\Resources\Events\Events\Pages\ManageEventProducts;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Events\EventGiftSetting;
use App\Models\Guests\Guest;
use App\Models\Guests\GuestMessage;
use App\Models\Orders\Order;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * For hosts who'd rather not ask for gifts: display modes, contributions
 * of any amount and the guestbook.
 */
class HeartfeltGiftsTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->create(['user_id' => $this->host->id, 'is_published' => true]);
    }

    private function makePremium(bool $freeAmount = true): void
    {
        $this->event->syncFeatures(config('premium.event_features'));
        $this->event->paymentSettings()->create(['mp_access_token' => 'TEST-token', 'mp_public_key' => 'TEST-key']);
        $this->event->giftSettings()->create(['allow_free_amount' => $freeAmount]);
        $this->event->unsetRelation('giftSettings');
    }

    public function test_the_host_chooses_how_gifts_show_on_the_page(): void
    {
        $this->actingAs($this->host);

        Livewire::test(ManageEventProducts::class, ['record' => $this->event->getRouteKey()])
            ->callAction('displaySettings', [
                'display_mode' => EventGiftSetting::DISPLAY_NONE,
                'gift_message' => 'Sua presença já é tudo!',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(EventGiftSetting::DISPLAY_NONE, $this->event->fresh()->giftDisplayMode());

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.gift_display_mode', EventGiftSetting::DISPLAY_NONE)
            ->where('event.gift_message', 'Sua presença já é tudo!'));
    }

    public function test_the_no_gifts_mode_hides_the_list_and_refuses_gifts(): void
    {
        $product = EventProduct::factory()->create(['event_id' => $this->event->id]);
        $this->event->giftSettings()->create(['display_mode' => EventGiftSetting::DISPLAY_NONE]);

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->has('products', 0)
            ->where('event.gift_message', EventGiftSetting::DEFAULT_GIFT_MESSAGE));

        $this->postJson("/{$this->event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
            'fulfillment' => Order::FULFILLMENT_IN_PERSON,
        ])->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_the_discreet_mode_still_lists_the_gifts(): void
    {
        EventProduct::factory()->create(['event_id' => $this->event->id]);
        $this->event->giftSettings()->create(['display_mode' => EventGiftSetting::DISPLAY_DISCREET]);

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->has('products', 1)
            ->where('event.gift_display_mode', EventGiftSetting::DISPLAY_DISCREET));
    }

    public function test_a_guest_can_contribute_any_amount_on_a_premium_event(): void
    {
        $this->makePremium();

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page->where('event.accepts_free_amount', true));

        $this->postJson("/{$this->event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'free_amount' => 123.45,
            'message' => 'De coração',
            'anonymous' => true,
        ])->assertOk()->assertJsonPath('order.total_amount', 123.45);

        $order = Order::first();
        $this->assertTrue($order->is_free_amount);
        $this->assertTrue($order->is_anonymous);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(0, $order->items()->count());
    }

    public function test_the_same_contribution_opened_again_is_still_one_order(): void
    {
        $this->makePremium();
        $contribute = fn (float $amount, ?string $identifier = null) => ($identifier
            ? $this->withCredentials()->withCookie(Guest::cookieName($this->event), $identifier)
            : $this)->postJson("/{$this->event->slug}/orders", [
                'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
                'free_amount' => $amount,
                'message' => 'Com carinho',
            ]);

        $first = $contribute(50)->assertOk()->json('order.id');
        $identifier = Order::find($first)->guest->identifier;

        // The page reloaded, the same contribution opened again.
        $this->assertSame($first, $contribute(50, $identifier)->assertOk()->json('order.id'));
        $this->assertSame(1, Order::count());

        // Another amount: the guest changed their mind.
        $other = $contribute(80, $identifier)->assertOk()->json('order.id');
        $this->assertNotSame($first, $other);
        $this->assertSame(Order::STATUS_EXPIRED, Order::find($first)->status);
    }

    public function test_contributions_need_the_host_to_accept_them(): void
    {
        $this->makePremium(freeAmount: false);

        $this->postJson("/{$this->event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'free_amount' => 50,
        ])->assertUnprocessable()->assertJsonValidationErrors('free_amount');

        // And a free event never takes them, whatever the setting says.
        $free = Event::factory()->create(['is_published' => true]);
        $free->giftSettings()->create(['allow_free_amount' => true]);

        $this->postJson("/{$free->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'free_amount' => 50,
        ])->assertUnprocessable()->assertJsonValidationErrors('free_amount');
    }

    public function test_the_host_turns_contributions_on_in_the_payments_page(): void
    {
        $this->makePremium(freeAmount: false);

        $this->actingAs($this->host);

        Livewire::test(EditEventPayment::class, ['record' => $this->event->getRouteKey()])
            ->fillForm(['giftSettings.allow_free_amount' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->event->fresh()->acceptsFreeAmount());
    }

    public function test_guestbook_messages_only_show_once_approved(): void
    {
        $this->makePremium();

        $this->post("/{$this->event->slug}/messages", [
            'author_name' => 'Tia Rosa',
            'message' => 'Felicidades ao casal!',
        ])->assertRedirect();

        $message = GuestMessage::first();
        $this->assertFalse($message->isApproved());

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.has_guestbook', true)
            ->has('messages', 0));

        $this->actingAs($this->host);

        Livewire::test(ManageEventMessages::class, ['record' => $this->event->getRouteKey()])
            ->callAction(TestAction::make('approve')->table($message));

        auth()->logout();

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->has('messages', 1)
            ->where('messages.0.author_name', 'Tia Rosa'));
    }

    public function test_the_guestbook_is_premium(): void
    {
        $this->post("/{$this->event->slug}/messages", [
            'author_name' => 'Tia Rosa',
            'message' => 'Felicidades!',
        ])->assertSessionHasErrors('message');

        $this->assertSame(0, GuestMessage::count());

        $this->actingAs($this->host);

        $this->get(ManageEventMessages::getUrl(['record' => $this->event]))
            ->assertOk()
            ->assertSee('Recurso premium: Mural de recados')
            ->assertSee('Conhecer o Premium');
    }
}
