<?php

namespace Tests\Feature\Events;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Guests\GuestMessage;
use App\Models\Orders\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The demonstration events of the home page: their date moves along with
 * today, they show either moment of an event, and nothing a visitor does
 * on them is saved.
 */
class DemoEventsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(12, 0));

        User::factory()->create(['is_admin' => true]);
        $this->artisan('demos:sync', ['--images' => storage_path('framework/testing/no-demo-images')])->assertSuccessful();

        $this->event = Event::query()->where('slug', 'demo-casamento')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function page(string $url): array
    {
        $props = [];

        $this->get($url)->assertOk()->assertInertia(function ($page) use (&$props) {
            $props = $page->toArray()['props'];
        });

        return $props;
    }

    public function test_the_command_creates_one_demo_per_occasion(): void
    {
        $this->assertSame(
            ['demo-aniversario', 'demo-casamento', 'demo-cha-de-bebe'],
            Event::query()->where('is_demo', true)->orderBy('slug')->pluck('slug')->all(),
        );
        $this->assertNull($this->event->event_date);
        $this->assertTrue($this->event->products()->exists());
        $this->assertTrue($this->event->messages()->whereNotNull('approved_at')->exists());
    }

    public function test_running_it_again_puts_a_demo_back_as_it_was_made(): void
    {
        $this->event->update(['title' => 'Mudado no painel']);
        $this->event->products()->first()->delete();
        $products = $this->event->products()->withTrashed()->count();

        $this->artisan('demos:sync', ['--images' => storage_path('framework/testing/no-demo-images')])->assertSuccessful();

        $this->assertSame('Helena & Rafael', $this->event->fresh()->title);
        $this->assertSame($products, $this->event->products()->count());
        $this->assertSame(3, Event::query()->where('is_demo', true)->count());
    }

    public function test_it_leaves_a_real_event_with_the_same_address_alone(): void
    {
        Event::query()->where('slug', 'demo-aniversario')->forceDelete();
        $real = Event::factory()->create(['slug' => 'demo-aniversario', 'title' => 'Festa de verdade']);

        $this->artisan('demos:sync', ['--images' => storage_path('framework/testing/no-demo-images')])->assertSuccessful();

        $this->assertSame('Festa de verdade', $real->fresh()->title);
        $this->assertFalse($real->fresh()->is_demo);
    }

    public function test_before_the_day_the_date_stays_ahead_of_today(): void
    {
        $props = $this->page('/demo-casamento');

        $this->assertSame('2027-01-02', $props['event']['event_date']);
        $this->assertFalse($props['event']['has_ended']);
        $this->assertSame('before', $props['demo']['moment']);
        $this->assertContains('confirmar-presenca', array_column($props['event']['sections'], 'key'));
        $this->assertContains('localizacao', array_column($props['event']['sections'], 'key'));

        // A month later it's still ahead.
        $this->travel(30)->days();
        $this->assertFalse($this->page('/demo-casamento')['event']['has_ended']);
    }

    public function test_after_the_day_it_shows_the_keepsake_without_saving_anything(): void
    {
        $props = $this->page('/demo-casamento?momento=depois');

        $this->assertTrue($props['event']['has_ended']);
        $this->assertSame('after', $props['demo']['moment']);
        $this->assertNull($props['event']['description']);
        $tabs = array_column($props['event']['sections'], 'key');
        $this->assertNotContains('confirmar-presenca', $tabs);
        $this->assertNotContains('localizacao', $tabs);

        $this->assertNull($this->event->fresh()->event_date);
        $this->assertNull($this->event->fresh()->tabs_closed_after_event_at);
        $this->assertSame(0, $this->event->sections()->where('is_active', false)->count());
    }

    public function test_visits_are_not_counted(): void
    {
        $this->get('/demo-casamento')->assertOk();

        $this->assertSame(0, $this->event->fresh()->visits_count);
    }

    public function test_confirming_presence_goes_through_without_saving(): void
    {
        $this->post('/demo-casamento/rsvp', [
            'guest' => ['name' => 'Visitante Curioso', 'whatsapp' => '(11) 98888-7777'],
            'attending' => true,
            'guests_count' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(0, Guest::query()->count());
    }

    public function test_the_form_is_still_checked(): void
    {
        $this->post('/demo-casamento/rsvp', [
            'guest' => ['name' => ''],
            'attending' => true,
            'guests_count' => 1,
        ])->assertSessionHasErrors('guest.name');
    }

    public function test_a_message_goes_through_without_reaching_the_wall(): void
    {
        $before = GuestMessage::query()->count();

        $this->post('/demo-casamento/messages', [
            'author_name' => 'Visitante',
            'message' => 'Que página linda!',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($before, GuestMessage::query()->count());
    }

    public function test_a_gift_gets_a_pretend_order_and_nothing_is_saved(): void
    {
        $product = $this->event->products()->where('price', 350)->firstOrFail();

        $this->postJson('/demo-casamento/orders', [
            'guest' => ['name' => 'Visitante', 'whatsapp' => '(11) 98888-7777'],
            'fulfillment' => Order::FULFILLMENT_ONLINE,
            'items' => [['event_product_id' => $product->id, 'quantity' => 2]],
        ])
            ->assertOk()
            ->assertJsonPath('order.id', 0)
            ->assertJsonPath('order.total_amount', 700)
            ->assertJsonPath('order.fulfillment', Order::FULFILLMENT_ONLINE);

        $this->postJson('/demo-casamento/orders', [
            'guest' => ['name' => 'Visitante', 'whatsapp' => '(11) 98888-7777'],
            'free_amount' => 150,
        ])->assertOk()->assertJsonPath('order.total_amount', 150);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Guest::query()->count());
        $this->assertSame(0, $product->fresh()->quantity_purchased);
    }

    public function test_it_takes_online_gifts_without_mercado_pago_credentials(): void
    {
        $props = $this->page('/demo-casamento');

        $this->assertTrue($props['event']['accepts_online_gifts']);
        $this->assertNull($props['event']['mp_public_key']);
    }

    public function test_payments_and_cancellations_are_refused(): void
    {
        $this->postJson('/demo-casamento/orders/1/mercadopago-payment', ['formData' => []])->assertNotFound();
        $this->post('/demo-casamento/orders/1/cancel')->assertNotFound();
    }

    public function test_the_tab_closing_schedule_leaves_demos_alone(): void
    {
        $this->artisan('events:close-ended-tabs')->assertSuccessful();

        $this->assertNull($this->event->fresh()->tabs_closed_after_event_at);
    }
}
