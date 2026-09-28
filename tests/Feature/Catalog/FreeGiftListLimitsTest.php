<?php

namespace Tests\Feature\Catalog;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\ManageEventProducts;
use App\Models\Catalog\EventProduct;
use App\Models\Catalog\ProductTemplate;
use App\Models\Events\Event;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class FreeGiftListLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Event $event;

    private ProductTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        config(['premium.free_gift_limit' => 2]);

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->create(['user_id' => $this->host->id]);
        $this->template = ProductTemplate::factory()->create([
            'name' => 'Jogo de panelas',
            'description' => 'Antiaderente, 5 peças',
            'suggested_price' => 200,
        ]);

        $this->actingAs($this->host);
    }

    private function page(): Testable
    {
        return Livewire::test(ManageEventProducts::class, ['record' => $this->event->getRouteKey()]);
    }

    public function test_a_free_list_only_takes_catalog_items(): void
    {
        $this->page()
            ->callAction(TestAction::make('create')->table(), ['name' => 'Cadeira do Arthur', 'price' => 100, 'quantity_total' => 1])
            ->assertHasActionErrors(['product_template_id' => 'required']);

        $this->assertSame(0, $this->event->products()->count());
    }

    public function test_a_catalog_item_keeps_the_catalog_name_and_description_but_the_price_is_free(): void
    {
        // Even if the browser sends other values for the locked fields.
        $this->page()
            ->callAction(TestAction::make('create')->table(), [
                'product_template_id' => $this->template->id,
                'name' => 'Nome inventado',
                'description' => 'Descrição inventada',
                'price' => 180,
                'quantity_total' => 1,
            ])
            ->assertHasNoActionErrors();

        $product = $this->event->products()->first();
        $this->assertSame('Jogo de panelas', $product->name);
        $this->assertSame('Antiaderente, 5 peças', $product->description);
        $this->assertEquals(180, $product->price);
    }

    public function test_a_free_list_has_no_quotas(): void
    {
        $this->page()
            ->callAction(TestAction::make('create')->table(), [
                'product_template_id' => $this->template->id,
                'price' => 50,
                'quantity_total' => 10,
            ])
            ->assertHasActionErrors(['quantity_total' => 'max']);
    }

    public function test_a_free_list_stops_at_the_limit(): void
    {
        EventProduct::factory()->count(2)->create(['event_id' => $this->event->id]);

        $this->page()
            ->assertSee('Plano gratuito: 2 de 2 presentes')
            ->assertActionVisible('discoverPremium')
            ->assertActionDisabled(TestAction::make('create')->table());
    }

    public function test_a_trashed_gift_cannot_be_restored_past_the_limit(): void
    {
        $trashed = EventProduct::factory()->create(['event_id' => $this->event->id]);
        $trashed->delete();
        EventProduct::factory()->count(2)->create(['event_id' => $this->event->id]);

        $this->page()
            ->callAction(TestAction::make('restore')->table($trashed))
            ->assertNotified('Limite de 2 presentes do plano gratuito');

        $this->assertTrue($trashed->fresh()->trashed());
    }

    public function test_what_a_free_event_already_had_is_kept(): void
    {
        config(['premium.free_gift_limit' => 1]);

        $legacyQuota = EventProduct::factory()->create([
            'event_id' => $this->event->id,
            'name' => 'Cota - Lua de mel',
            'quantity_total' => 20,
        ]);
        EventProduct::factory()->create(['event_id' => $this->event->id]);

        // Over the limit, custom and a quota: still on the public page.
        $this->get("/{$this->event->slug}")->assertInertia(fn ($page) => $page->has('products', 2));

        // A custom item stays editable, and the quota can go down, not up.
        $this->page()
            ->callAction(TestAction::make('edit')->table($legacyQuota), ['name' => 'Cota - Viagem', 'quantity_total' => 21])
            ->assertHasActionErrors(['quantity_total' => 'max']);

        $this->page()
            ->callAction(TestAction::make('edit')->table($legacyQuota), ['name' => 'Cota - Viagem', 'quantity_total' => 15])
            ->assertHasNoActionErrors();

        $this->assertSame('Cota - Viagem', $legacyQuota->fresh()->name);
        $this->assertSame(15, $legacyQuota->fresh()->quantity_total);
    }

    public function test_the_full_gift_list_lifts_every_limit(): void
    {
        $this->event->grantFeature(Feature::FullGiftList);
        EventProduct::factory()->count(2)->create(['event_id' => $this->event->id]);

        $this->page()
            ->assertActionHidden('discoverPremium')
            ->callAction(TestAction::make('create')->table(), ['name' => 'Cota - Lua de mel', 'price' => 50, 'quantity_total' => 30])
            ->assertHasNoActionErrors();

        $this->assertSame(3, $this->event->products()->count());
    }
}
