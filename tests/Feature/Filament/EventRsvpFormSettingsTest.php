<?php

namespace Tests\Feature\Filament;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\EditEventPremium;
use App\Filament\Resources\Events\Events\Pages\EditEventRsvp;
use App\Models\Events\Event;
use App\Models\Events\EventRsvpSetting;
use App\Models\Premium\FeatureGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventRsvpFormSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_the_feature_hosts_only_set_the_childrens_age_limit(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        $this->get(EditEventRsvp::getUrl(['record' => $event]))
            ->assertOk()
            ->assertSee('Recurso premium: Lista nominal de convidados');

        Livewire::test(EditEventRsvp::class, ['record' => $event->getRouteKey()])
            ->assertFormFieldDoesNotExist('rsvpSettings.fields.age')
            ->fillForm(['rsvpSettings.child_age_limit' => 5])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(5, $event->fresh()->childAgeLimit());
    }

    public function test_hosts_choose_each_field_once_the_feature_is_enabled(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::GuestList)->create([
            'user_id' => $host->id,
        ]);

        $this->actingAs($host);

        Livewire::test(EditEventRsvp::class, ['record' => $event->getRouteKey()])
            ->assertFormSet([
                'rsvpSettings.fields.whatsapp' => EventRsvpSetting::FIELD_REQUIRED,
                'rsvpSettings.fields.age' => EventRsvpSetting::FIELD_HIDDEN,
            ])
            ->fillForm([
                'rsvpSettings.fields.whatsapp' => EventRsvpSetting::FIELD_OPTIONAL,
                'rsvpSettings.fields.email' => EventRsvpSetting::FIELD_REQUIRED,
                'rsvpSettings.fields.cpf' => EventRsvpSetting::FIELD_REQUIRED,
                'rsvpSettings.fields.age' => EventRsvpSetting::FIELD_OPTIONAL,
                'rsvpSettings.collect_companions' => true,
                'rsvpSettings.companion_fields.whatsapp' => EventRsvpSetting::FIELD_HIDDEN,
                'rsvpSettings.companion_fields.age' => EventRsvpSetting::FIELD_REQUIRED,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();

        $this->assertSame(['email', 'cpf'], $event->rsvpRequiredFields());
        $this->assertSame(EventRsvpSetting::FIELD_OPTIONAL, $event->rsvpFields()['age']);
        $this->assertTrue($event->collectsRsvpCompanions());
        $this->assertSame(EventRsvpSetting::FIELD_HIDDEN, $event->rsvpCompanionFields()['whatsapp']);
        $this->assertSame(EventRsvpSetting::FIELD_REQUIRED, $event->rsvpCompanionFields()['age']);
    }

    public function test_a_form_can_ask_only_for_the_name_and_the_age(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->withFeatures(Feature::GuestList)->create(['user_id' => $host->id]);

        $this->actingAs($host);

        Livewire::test(EditEventRsvp::class, ['record' => $event->getRouteKey()])
            // Only once no contact is required.
            ->assertDontSee('você não terá como falar com os convidados')
            ->fillForm([
                'rsvpSettings.fields.whatsapp' => EventRsvpSetting::FIELD_HIDDEN,
                'rsvpSettings.fields.email' => EventRsvpSetting::FIELD_HIDDEN,
                'rsvpSettings.fields.cpf' => EventRsvpSetting::FIELD_HIDDEN,
                'rsvpSettings.fields.age' => EventRsvpSetting::FIELD_REQUIRED,
            ])
            ->assertSee('você não terá como falar com os convidados')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $event->fresh()->rsvpRequiredFields());
    }

    public function test_admins_can_enable_and_disable_premium_features(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $event = Event::factory()->create();

        $this->actingAs($admin);

        Livewire::test(EditEventPremium::class, ['record' => $event->getRouteKey()])
            ->fillForm(['premium_features' => [Feature::GuestList->value, Feature::Payments->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();

        $this->assertTrue($event->hasFeature(Feature::GuestList));
        $this->assertTrue($event->hasFeature(Feature::Payments));
        $this->assertSame(FeatureGrant::SOURCE_ADMIN, $event->featureGrants->first()->source);
        $this->assertSame(['whatsapp'], $event->rsvpRequiredFields());

        Livewire::test(EditEventPremium::class, ['record' => $event->getRouteKey()])
            ->assertFormSet(['premium_features' => [Feature::Payments->value, Feature::GuestList->value]])
            ->fillForm(['premium_features' => [Feature::Payments->value]])
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $event->fresh();

        $this->assertFalse($event->hasFeature(Feature::GuestList));
        $this->assertTrue($event->hasFeature(Feature::Payments));
    }

    public function test_hosts_cannot_grant_themselves_premium_features(): void
    {
        $host = User::factory()->create(['is_admin' => false]);
        $event = Event::factory()->create(['user_id' => $host->id]);

        $this->actingAs($host);

        $this->get(EditEventPremium::getUrl(['record' => $event]))->assertForbidden();

        $this->assertFalse($event->fresh()->hasFeature(Feature::GuestList));
    }

    public function test_the_admin_can_prepare_the_settings_before_unlocking_the_feature(): void
    {
        $event = Event::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(EditEventRsvp::getUrl(['record' => $event]))
            ->assertOk()
            ->assertSee('não está liberado neste evento');
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
