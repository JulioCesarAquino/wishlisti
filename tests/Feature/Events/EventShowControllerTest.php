<?php

namespace Tests\Feature\Events;

use App\Enums\Premium\Feature;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventShowControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_a_published_event_with_active_products(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        EventProduct::factory()->create(['event_id' => $event->id, 'is_active' => true, 'name' => 'Liquidificador']);
        EventProduct::factory()->create(['event_id' => $event->id, 'is_active' => false, 'name' => 'Item inativo']);

        $response = $this->get("/{$event->slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('events/show')
            ->has('products', 1)
            ->where('products.0.name', 'Liquidificador'));
    }

    public function test_it_exposes_the_events_location(): void
    {
        $event = Event::factory()->create([
            'is_published' => true,
            'address' => 'Av. Paulista, 1000, São Paulo - SP',
            'latitude' => -23.5613,
            'longitude' => -46.6565,
        ]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.address', 'Av. Paulista, 1000, São Paulo - SP')
            ->where('event.latitude', -23.5613)
            ->where('event.longitude', -46.6565));
    }

    public function test_only_premium_events_take_gifts_online(): void
    {
        $free = Event::factory()->withMercadoPago()->create(['is_published' => true]);
        $premium = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago()->create(['is_published' => true]);

        $this->get("/{$free->slug}")->assertInertia(fn ($page) => $page
            ->where('event.accepts_online_gifts', false)
            ->where('event.mp_public_key', null));

        $this->get("/{$premium->slug}")->assertInertia(fn ($page) => $page
            ->where('event.accepts_online_gifts', true)
            ->where('event.mp_public_key', 'TEST-public-key'));
    }

    public function test_unpublished_events_are_not_found_for_guests(): void
    {
        $event = Event::factory()->create(['is_published' => false]);

        $this->get("/{$event->slug}")->assertNotFound();
    }

    public function test_the_owner_can_preview_their_own_unpublished_event(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id, 'is_published' => false]);

        $this->actingAs($host)
            ->get("/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('is_preview', true));
    }

    public function test_another_host_cannot_preview_someone_elses_unpublished_event(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $otherHost = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $owner->id, 'is_published' => false]);

        $this->actingAs($otherHost)
            ->get("/{$event->slug}")
            ->assertNotFound();
    }

    public function test_an_admin_can_preview_any_unpublished_event(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $event = Event::factory()->create(['is_published' => false]);

        $this->actingAs($admin)
            ->get("/{$event->slug}")
            ->assertOk();
    }

    public function test_a_visit_increments_the_counter_once_per_cookie(): void
    {
        $event = Event::factory()->create(['is_published' => true, 'visits_count' => 0]);

        $first = $this->get("/{$event->slug}");
        $first->assertOk();
        $this->assertSame(1, $event->fresh()->visits_count);

        $cookieName = $event->visitCookieName();
        $cookieValue = $first->headers->getCookies()[0]->getValue();

        $this->withCookie($cookieName, $cookieValue)->get("/{$event->slug}");

        $this->assertSame(1, $event->fresh()->visits_count);
    }

    public function test_the_owners_own_visits_are_not_counted(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id, 'is_published' => true, 'visits_count' => 0]);

        $this->actingAs($host)->get("/{$event->slug}");

        $this->assertSame(0, $event->fresh()->visits_count);
    }
}
