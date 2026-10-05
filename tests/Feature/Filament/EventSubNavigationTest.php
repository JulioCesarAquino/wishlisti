<?php

namespace Tests\Feature\Filament;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\EditEventLocation;
use App\Filament\Resources\Events\Events\Pages\EditEventPayment;
use App\Filament\Resources\Events\Events\Pages\ManageEventProducts;
use App\Models\Events\Event;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventSubNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function pages(): array
    {
        return ['edit', 'co-hosts', 'location', 'appearance', 'sections', 'products', 'guests', 'rsvp', 'payment', 'premium'];
    }

    public function test_the_payments_page_is_locked_without_the_premium_feature(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        $this->get(EditEventPayment::getUrl(['record' => $event]))
            ->assertOk()
            ->assertSee('Recurso premium: Receber presentes online')
            ->assertSee('Conhecer o Premium');

        Livewire::test(EditEventPayment::class, ['record' => $event->getRouteKey()])
            ->assertFormFieldDoesNotExist('paymentSettings.mp_access_token')
            ->call('save')
            ->assertForbidden();
    }

    public function test_a_host_can_open_every_page_of_their_event(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        foreach ($this->pages() as $page) {
            $this->get(EventResource::getUrl($page, ['record' => $event]))->assertOk();
        }

        $this->get(EventResource::getUrl('edit', ['record' => $event]))
            ->assertSee('Localização')
            ->assertSee('Confirmação de presença')
            ->assertSee(EventResource::getUrl('premium', ['record' => $event]))
            ->assertDontSee(EventResource::getUrl('premium-grants', ['record' => $event]));

        $this->get(EventResource::getUrl('premium-grants', ['record' => $event]))->assertForbidden();
    }

    public function test_a_host_cannot_open_pages_of_someone_elses_event(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => false]));

        foreach ($this->pages() as $page) {
            $this->get(EventResource::getUrl($page, ['record' => $event]))->assertNotFound();
        }
    }

    public function test_only_the_admin_sees_the_grants_page(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(EventResource::getUrl('edit', ['record' => $event]))
            ->assertSee(EventResource::getUrl('premium-grants', ['record' => $event]));

        $this->get(EventResource::getUrl('premium-grants', ['record' => $event]))->assertOk();
    }

    public function test_each_page_only_saves_its_own_part(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::Payments)->withMercadoPago('TEST-original')->withLocation([
            'address' => 'Rua Antiga, 1',
        ])->create([
            'user_id' => $host->id,
        ]);

        $this->actingAs($host);
        Repeater::fake();

        Livewire::test(EditEventLocation::class, ['record' => $event->getRouteKey()])
            ->fillForm(['locations' => [['name' => 'Local do evento', 'address' => 'Rua Nova, 2']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();
        $this->assertSame('Rua Nova, 2', $event->locations->sole()->address);
        $this->assertSame('TEST-original', $event->paymentSettings->mp_access_token);

        Livewire::test(EditEventPayment::class, ['record' => $event->getRouteKey()])
            ->fillForm(['paymentSettings.mp_access_token' => 'TEST-novo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('TEST-novo', $event->fresh()->paymentSettings->mp_access_token);

        // Saving the page without retyping the token keeps it.
        Livewire::test(EditEventPayment::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['paymentSettings.mp_access_token' => null, 'paymentSettings.mp_public_key' => 'TEST-public-key'])
            ->call('save')
            ->assertHasNoFormErrors();

        $event->refresh();
        $this->assertSame('TEST-novo', $event->paymentSettings->mp_access_token);
        $this->assertSame('TEST-public-key', $event->paymentSettings->mp_public_key);
    }

    public function test_products_are_managed_from_their_own_page(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::FullGiftList)->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(ManageEventProducts::class, ['record' => $event->getRouteKey()])
            ->callAction(TestAction::make('create')->table(), ['name' => 'Jogo de panelas', 'price' => 250, 'quantity_total' => 1])
            ->assertHasNoActionErrors();

        $this->assertSame(['Jogo de panelas'], $event->products()->pluck('name')->all());
    }
}
