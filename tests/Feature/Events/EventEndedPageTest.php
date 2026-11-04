<?php

namespace Tests\Feature\Events;

use App\Enums\Premium\Feature;
use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use App\Models\Orders\Order;
use App\Models\Premium\FeatureGrant;
use App\Services\Premium\PremiumValidityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Once the event's day has gone by, the page becomes a keepsake: no way
 * there, no invitation text — the story, the gifts and the messages stay.
 */
class EventEndedPageTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        config(['premium.grace_days' => 60]);

        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));

        $this->event = Event::factory()->withLocation(['name' => 'Festa'])->create([
            'event_date' => '2026-10-30',
            'event_time' => '16:00:00',
            'description' => 'Venha celebrar com a gente!',
            'story' => 'Nos conhecemos na faculdade.',
            'is_published' => true,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function tabs(): array
    {
        $tabs = [];

        $this->get("/{$this->event->slug}")->assertInertia(function ($page) use (&$tabs) {
            $tabs = array_column($page->toArray()['props']['event']['sections'], 'key');
        });

        return $tabs;
    }

    /**
     * A premium bought for the event: its features end some days after it.
     *
     * @param  array<int, Feature>  $features
     */
    private function grantPremium(array $features): void
    {
        $expiresAt = app(PremiumValidityService::class)->expiresAt($this->event->event_date);

        foreach ($features as $feature) {
            $this->event->featureGrants()->create([
                'feature' => $feature,
                'source' => FeatureGrant::SOURCE_PURCHASE,
                'expires_at' => $expiresAt,
            ]);
        }
    }

    public function test_the_page_is_unchanged_on_the_day_of_the_event(): void
    {
        // Late guests still need the way there.
        $this->travelTo(now()->setDate(2026, 10, 30)->setTime(23, 0));

        $this->assertFalse($this->event->hasEnded());
        $this->assertContains('localizacao', $this->tabs());

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.has_ended', false)
            ->where('event.description', 'Venha celebrar com a gente!')
            ->has('event.locations', 1));
    }

    public function test_the_event_ends_the_day_after_in_brasilia(): void
    {
        // 23h of the 30th in Brasília is already the 31st in UTC.
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(2, 30));
        $this->assertFalse($this->event->hasEnded());

        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(3, 0));
        $this->assertTrue($this->event->hasEnded());
    }

    public function test_an_event_without_a_date_never_ends(): void
    {
        $this->event->update(['event_date' => null]);

        $this->travelTo(now()->addYears(2));

        $this->assertFalse($this->event->fresh()->hasEnded());
    }

    public function test_after_the_event_the_location_section_is_gone(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2));

        $this->assertNotContains('localizacao', $this->tabs());

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.has_ended', true)
            ->has('event.locations', 0));

        // Links shared before the event land on the page itself.
        $location = $this->event->locations->first();
        $this->get("/{$this->event->slug}/localizacao")->assertRedirect("/{$this->event->slug}");
        $this->get("/{$this->event->slug}/localizacao/{$location->slug}")->assertRedirect("/{$this->event->slug}");
    }

    public function test_after_the_event_the_home_keeps_the_story_but_not_the_description(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 2));

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.description', null)
            ->where('event.story', 'Nos conhecemos na faculdade.'));
    }

    public function test_after_the_event_the_guestbook_stays_only_with_messages(): void
    {
        $this->grantPremium([Feature::Guestbook]);

        $this->assertContains('recados', $this->tabs());

        $this->travelTo(now()->setDate(2026, 11, 2));

        // Premium still on, but nothing to read: no tab.
        $this->assertNotContains('recados', $this->tabs());

        GuestMessage::factory()->approved()->create(['event_id' => $this->event->id]);

        $this->assertContains('recados', $this->tabs());
    }

    public function test_after_the_event_a_guestbook_turned_off_stays_off(): void
    {
        $this->grantPremium([Feature::Guestbook]);
        GuestMessage::factory()->approved()->create(['event_id' => $this->event->id]);
        $this->event->ensureSections();
        $this->event->sections()->where('type', 'recado')->update(['is_active' => false]);

        $this->travelTo(now()->setDate(2026, 11, 2));

        $this->assertNotContains('recados', $this->tabs());
    }

    public function test_gifts_stay_available_through_the_premium_grace_period_after_the_event(): void
    {
        $this->event = Event::factory()->withMercadoPago()->create(['event_date' => '2026-10-30', 'is_published' => true]);
        $this->grantPremium([Feature::Payments]);
        $product = EventProduct::factory()->create(['event_id' => $this->event->id, 'quantity_total' => 5]);

        // The day after: still taking online gifts.
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(12, 0));

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.accepts_online_gifts', true)
            ->has('products', 1));
        $this->assertContains('presentes', $this->tabs());

        // The last day of the grace period (60 days after the event).
        $this->travelTo(now()->setDate(2026, 12, 29)->setTime(12, 0));
        $this->assertTrue($this->event->fresh()->acceptsOnlineGifts());

        // Past it: the list stays, with gifts handed over in person.
        $this->travelTo(now()->setDate(2026, 12, 30)->setTime(12, 0));

        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page
            ->where('event.accepts_online_gifts', false)
            ->where('event.accepts_in_person_gifts', true)
            ->has('products', 1));

        $this->postJson("/{$this->event->slug}/orders", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'items' => [['event_product_id' => $product->id, 'quantity' => 1]],
            'fulfillment' => Order::FULFILLMENT_IN_PERSON,
        ])->assertOk();
    }
}
