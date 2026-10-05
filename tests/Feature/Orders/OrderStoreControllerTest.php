<?php

namespace Tests\Feature\Orders;

use App\Enums\Premium\Feature;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Events\EventRsvpSetting;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderStoreControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_pending_order_with_server_computed_total(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create([
            'event_id' => $event->id,
            'price' => 150.00,
            'quantity_total' => 5,
        ]);

        $response = $this->post("/{$event->slug}/orders", [
            'guest' => [
                'name' => 'Maria Teste',
                'whatsapp' => '5511999999999',
                'email' => 'maria@example.com',
            ],
            'message' => 'Parabéns!',
            'items' => [
                ['event_product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['order' => ['id', 'total_amount']]);

        $order = Order::first();

        $this->assertNotNull($order);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertEquals(300.00, (float) $order->total_amount);
        $this->assertSame('Maria Teste', $order->guest->name);
        $this->assertCount(1, $order->items);
    }

    public function test_it_rejects_ordering_more_than_available_quantity(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create([
            'event_id' => $event->id,
            'quantity_total' => 1,
        ]);

        $response = $this->post("/{$event->slug}/orders", [
            'guest' => [
                'name' => 'Maria Teste',
                'whatsapp' => '5511999999999',
            ],
            'items' => [
                ['event_product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Order::count());
    }

    public function test_it_rejects_a_product_that_does_not_belong_to_the_event(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $otherEventProduct = EventProduct::factory()->create();

        $response = $this->post("/{$event->slug}/orders", [
            'guest' => [
                'name' => 'Maria Teste',
                'whatsapp' => '5511999999999',
            ],
            'items' => [
                ['event_product_id' => $otherEventProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertSessionHasErrors('items.0.event_product_id');
        $this->assertSame(0, Order::count());
    }

    public function test_it_reuses_the_existing_guest_when_the_cookie_matches(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);
        $guest = $event->guests()->create([
            'name' => 'Guest Antigo',
            'whatsapp' => '5511888888888',
            'identifier' => (string) Str::uuid(),
        ]);

        $response = $this->withCookie(
            Guest::cookieName($event),
            $guest->identifier,
        )->post("/{$event->slug}/orders", [
            'guest' => [
                'name' => 'Guest Atualizado',
                'whatsapp' => '5511888888888',
            ],
            'items' => [
                ['event_product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk();

        $this->assertSame(1, $event->guests()->count());
        $this->assertSame('Guest Atualizado', $guest->fresh()->name);
    }

    public function test_it_throttles_repeated_requests_from_the_same_ip(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);

        $payload = [
            'guest' => [
                'name' => 'Maria Teste',
                'whatsapp' => '5511999999999',
            ],
            'items' => [
                ['event_product_id' => 999999, 'quantity' => 1],
            ],
        ];

        for ($i = 0; $i < 20; $i++) {
            $this->post("/{$event->slug}/orders", $payload)->assertSessionHasErrors('items.0.event_product_id');
        }

        $this->post("/{$event->slug}/orders", $payload)->assertStatus(429);
    }

    public function test_unpublished_events_return_404(): void
    {
        $event = Event::factory()->create(['is_published' => false]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);

        $response = $this->post("/{$event->slug}/orders", [
            'guest' => [
                'name' => 'Maria Teste',
                'whatsapp' => '5511999999999',
            ],
            'items' => [
                ['event_product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $response->assertNotFound();
    }

    public function test_events_without_the_premium_feature_do_not_take_online_gifts(): void
    {
        $event = Event::factory()->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);

        $this->postJson("/{$event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '5511999999999'],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->assertSame(0, $event->orders()->count());
    }

    public function test_a_guest_can_give_anonymously(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);

        $this->postJson("/{$event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '5511999999999'],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
            'message' => 'Com carinho',
            'anonymous' => true,
        ])->assertOk();

        $this->assertTrue($event->orders()->first()->is_anonymous);
    }

    public function test_an_anonymous_giver_does_not_have_to_identify_themselves(): void
    {
        $event = Event::factory()
            ->withFeatures(Feature::Payments, Feature::GuestList)
            ->withMercadoPago()
            ->withRsvpSettings(['fields' => EventRsvpSetting::fieldsRequiring(['email', 'cpf'])])
            ->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);

        $this->postJson("/{$event->slug}/orders", [
            'guest' => ['name' => '', 'whatsapp' => '', 'email' => '', 'cpf' => ''],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
            'anonymous' => true,
        ])->assertOk();

        $guest = $event->orders()->first()->guest;

        $this->assertSame(Guest::ANONYMOUS_GIVER_NAME, $guest->name);
        $this->assertNull($guest->whatsapp);
        $this->assertNull($guest->email);
        $this->assertNull($guest->cpf);
    }

    public function test_an_anonymous_giver_still_has_their_contact_details_validated(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);

        $this->postJson("/{$event->slug}/orders", [
            'guest' => ['email' => 'not-an-email'],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
            'anonymous' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('guest.email');
    }

    public function test_an_anonymous_gift_keeps_the_name_of_a_guest_the_browser_already_knows(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);
        $guest = Guest::factory()->create(['event_id' => $event->id, 'name' => 'Maria']);

        // JSON requests only carry cookies "with credentials".
        $this->withCredentials()
            ->withCookie(Guest::cookieName($event), $guest->identifier)
            ->postJson("/{$event->slug}/orders", [
                'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
                'anonymous' => true,
            ])->assertOk();

        $this->assertSame($guest->id, $event->orders()->first()->guest_id);
        $this->assertSame('Maria', $guest->fresh()->name);
    }

    public function test_identified_givers_must_still_tell_their_name(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);

        $this->postJson("/{$event->slug}/orders", [
            'guest' => ['name' => ''],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('guest.name');
    }

    public function test_missing_details_get_a_polite_message(): void
    {
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);
        $product = EventProduct::factory()->create(['event_id' => $event->id]);

        $this->postJson("/{$event->slug}/orders", [
            'guest' => ['name' => '', 'whatsapp' => '', 'email' => 'maria@'],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'guest.name' => 'Por favor, informe seu nome.',
            'guest.whatsapp' => 'Por favor, informe seu WhatsApp.',
            'guest.email' => 'Esse e-mail parece incompleto. Pode conferir?',
        ]);
    }
}
