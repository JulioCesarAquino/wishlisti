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

    public function test_it_exposes_the_events_locations_in_order(): void
    {
        $event = Event::factory()->create(['is_published' => true]);
        $event->locations()->create(['name' => 'Festa', 'address' => 'Salão Azul', 'position' => 1]);
        $event->locations()->create([
            'name' => 'Cerimônia',
            'address' => 'Av. Paulista, 1000, São Paulo - SP',
            'maps_url' => 'https://maps.app.goo.gl/abc',
            'latitude' => -23.5613,
            'longitude' => -46.6565,
            'position' => 0,
        ]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->has('event.locations', 2)
            ->where('event.locations.0.name', 'Cerimônia')
            ->where('event.locations.0.slug', 'cerimonia')
            ->where('event.locations.0.address', 'Av. Paulista, 1000, São Paulo - SP')
            ->where('event.locations.0.maps_url', 'https://maps.app.goo.gl/abc')
            ->where('event.locations.0.latitude', -23.5613)
            ->where('event.locations.0.longitude', -46.6565)
            ->where('event.locations.0.url', url("/{$event->slug}/localizacao/cerimonia"))
            ->where('event.locations.1.name', 'Festa')
            ->where('initial_section', null)
            ->where('initial_location', null));
    }

    public function test_it_exposes_the_start_time_and_what_the_countdown_counts_to(): void
    {
        $event = Event::factory()->create(['is_published' => true, 'event_date' => '2026-10-30', 'event_time' => '16:00']);
        $event->locations()->create(['name' => 'Cerimônia', 'start_time' => '16:00', 'address' => 'Igreja', 'position' => 0]);
        $event->locations()->create(['name' => 'Festa', 'start_time' => '18:30', 'address' => 'Salão', 'position' => 1]);
        $event->locations()->create(['name' => 'Hotel', 'address' => 'Centro', 'position' => 2]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.event_time', '16h')
            // Brasília time, whatever the server's timezone.
            ->where('event.starts_at', '2026-10-30T16:00:00-03:00')
            ->where('event.locations.0.start_time', '16h')
            ->where('event.locations.1.start_time', '18h30')
            ->where('event.locations.2.start_time', null));
    }

    public function test_without_a_time_the_countdown_counts_to_the_start_of_the_day(): void
    {
        $event = Event::factory()->create(['is_published' => true, 'event_date' => '2026-10-30', 'event_time' => null]);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.event_time', null)
            ->where('event.starts_at', '2026-10-30T00:00:00-03:00'));
    }

    public function test_a_time_without_a_date_is_not_shown(): void
    {
        $event = Event::factory()->create(['is_published' => true, 'event_date' => null, 'event_time' => '16:00']);

        $this->get("/{$event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.event_time', null)
            ->where('event.starts_at', null));
    }

    public function test_a_location_link_opens_the_page_on_that_location(): void
    {
        $event = Event::factory()->withLocation(['name' => 'Festa'])->create(['is_published' => true]);

        $this->get("/{$event->slug}/localizacao/festa")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/show')
                ->where('initial_section', 'localizacao')
                ->where('initial_location', 'festa'));

        $this->get("/{$event->slug}/localizacao")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('initial_section', 'localizacao')
                ->where('initial_location', null));
    }

    public function test_a_location_link_only_finds_locations_of_that_event(): void
    {
        $event = Event::factory()->withLocation(['name' => 'Festa'])->create(['is_published' => true]);
        Event::factory()->withLocation(['name' => 'Cerimônia'])->create(['is_published' => true]);

        $this->get("/{$event->slug}/localizacao/cerimonia")->assertNotFound();
    }

    public function test_the_location_tab_is_not_found_without_locations(): void
    {
        $event = Event::factory()->create(['is_published' => true]);

        $this->get("/{$event->slug}/localizacao")->assertNotFound();
    }

    public function test_location_links_of_unpublished_events_are_not_found(): void
    {
        $event = Event::factory()->withLocation(['name' => 'Festa'])->create(['is_published' => false]);

        $this->get("/{$event->slug}/localizacao/festa")->assertNotFound();
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
