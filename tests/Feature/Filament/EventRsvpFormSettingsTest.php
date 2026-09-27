<?php

namespace Tests\Feature\Filament;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\EditEvent;
use App\Models\Events\Event;
use App\Models\Premium\FeatureGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventRsvpFormSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosts_cannot_see_the_settings_until_the_admin_enables_the_premium_feature(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormFieldHidden('premium_features')
            ->assertFormFieldHidden('rsvpSettings.required_fields')
            ->assertFormFieldHidden('rsvpSettings.collect_companions');
    }

    public function test_hosts_can_configure_the_form_once_the_feature_is_enabled(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::GuestList)->create([
            'user_id' => $host->id,
            'address' => 'Rua das Flores, 123',
        ]);

        $this->actingAs($host);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormFieldHidden('premium_features')
            ->fillForm([
                'rsvpSettings.required_fields' => ['email', 'cpf'],
                'rsvpSettings.collect_companions' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();

        $this->assertSame(['email', 'cpf'], $event->rsvpRequiredFields());
        $this->assertTrue($event->collectsRsvpCompanions());
        $this->assertTrue($event->hasFeature(Feature::GuestList));
    }

    public function test_at_least_one_contact_field_must_be_required(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::GuestList)->create([
            'user_id' => $host->id,
            'address' => 'Rua das Flores, 123',
        ]);

        $this->actingAs($host);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['rsvpSettings.required_fields' => []])
            ->call('save')
            ->assertHasFormErrors(['rsvpSettings.required_fields']);
    }

    public function test_admins_can_enable_and_disable_premium_features(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $event = Event::factory()->create(['address' => 'Rua das Flores, 123']);

        $this->actingAs($admin);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormFieldHidden('rsvpSettings.required_fields')
            ->fillForm(['premium_features' => [Feature::GuestList->value, Feature::GiftGivers->value]])
            ->assertFormFieldVisible('rsvpSettings.required_fields')
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();

        $this->assertTrue($event->hasFeature(Feature::GuestList));
        $this->assertTrue($event->hasFeature(Feature::GiftGivers));
        $this->assertSame(FeatureGrant::SOURCE_ADMIN, $event->featureGrants->first()->source);
        $this->assertSame(['whatsapp'], $event->rsvpRequiredFields());

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['premium_features' => [Feature::GiftGivers->value, Feature::GuestList->value]])
            ->fillForm(['premium_features' => [Feature::GiftGivers->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();

        $this->assertFalse($event->hasFeature(Feature::GuestList));
        $this->assertTrue($event->hasFeature(Feature::GiftGivers));
    }

    public function test_hosts_cannot_grant_themselves_premium_features(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id, 'address' => 'Rua das Flores, 123']);

        $this->actingAs($host);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->set('data.premium_features', [Feature::GuestList->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($event->fresh()->hasFeature(Feature::GuestList));
    }

    public function test_an_expired_grant_no_longer_unlocks_the_feature(): void
    {
        $event = Event::factory()->create();
        $event->featureGrants()->create([
            'feature' => Feature::GuestList,
            'source' => FeatureGrant::SOURCE_PURCHASE,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertFalse($event->fresh()->hasFeature(Feature::GuestList));
    }
}
