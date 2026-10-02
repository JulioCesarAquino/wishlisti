<?php

namespace Tests\Feature\Filament;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\EditEventPayment;
use App\Models\Events\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Public Key and the Access Token are easily swapped when copied from
 * Mercado Pago — then every payment is turned down. Caught on save.
 */
class EventPaymentCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_KEY = 'APP_USR-7a1d2f3e-1234-4c5d-8e9f-0123456789ab';

    private const ACCESS_TOKEN = 'APP_USR-1234567890123456-100226-0123456789abcdef0123456789abcdef-987654321';

    private User $host;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->withFeatures(Feature::Payments)->create(['user_id' => $this->host->id]);
        $this->actingAs($this->host);
    }

    public function test_the_public_key_comes_first_as_on_mercado_pago(): void
    {
        Livewire::test(EditEventPayment::class, ['record' => $this->event->getRouteKey()])
            ->assertSeeInOrder(['Public Key', 'Access Token']);
    }

    public function test_correct_credentials_are_saved(): void
    {
        Livewire::test(EditEventPayment::class, ['record' => $this->event->getRouteKey()])
            ->fillForm([
                'paymentSettings.mp_public_key' => self::PUBLIC_KEY,
                'paymentSettings.mp_access_token' => self::ACCESS_TOKEN,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = $this->event->fresh()->paymentSettings;
        $this->assertSame(self::PUBLIC_KEY, $settings->mp_public_key);
        $this->assertSame(self::ACCESS_TOKEN, $settings->mp_access_token);
    }

    public function test_swapped_credentials_are_caught(): void
    {
        Livewire::test(EditEventPayment::class, ['record' => $this->event->getRouteKey()])
            ->fillForm([
                'paymentSettings.mp_public_key' => self::ACCESS_TOKEN,
                'paymentSettings.mp_access_token' => self::PUBLIC_KEY,
            ])
            ->call('save')
            ->assertHasFormErrors(['paymentSettings.mp_public_key', 'paymentSettings.mp_access_token'])
            ->assertSee('Isso parece o Access Token. Aqui vai a Public Key')
            ->assertSee('Isso parece a Public Key. Aqui vai o Access Token');

        $this->assertNull($this->event->fresh()->paymentSettings->mp_access_token);
    }

    public function test_something_that_is_not_a_credential_is_caught(): void
    {
        Livewire::test(EditEventPayment::class, ['record' => $this->event->getRouteKey()])
            ->fillForm(['paymentSettings.mp_access_token' => 'meu-token'])
            ->call('save')
            ->assertHasFormErrors(['paymentSettings.mp_access_token']);
    }

    public function test_a_key_already_saved_never_blocks_the_rest_of_the_page(): void
    {
        $this->event->paymentSettings()->create(['mp_access_token' => 'TEST-token', 'mp_public_key' => 'TEST-old-format']);

        Livewire::test(EditEventPayment::class, ['record' => $this->event->getRouteKey()])
            ->fillForm(['giftSettings.allow_free_amount' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($this->event->fresh()->giftSettings->allow_free_amount);
    }
}
