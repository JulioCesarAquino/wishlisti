<?php

namespace Tests\Feature\Guests;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Events\EventRsvpSetting;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RsvpCompanionsTest extends TestCase
{
    use RefreshDatabase;

    private function premiumEvent(array $requiredFields = ['whatsapp'], bool $collectCompanions = true): Event
    {
        return Event::factory()
            ->withFeatures(Feature::GuestList)
            ->withRsvpSettings([
                'collect_companions' => $collectCompanions,
                'fields' => EventRsvpSetting::fieldsRequiring($requiredFields),
                'companion_fields' => EventRsvpSetting::fieldsRequiring($requiredFields),
            ])
            ->create(['is_published' => true]);
    }

    public function test_free_events_ignore_the_custom_settings(): void
    {
        $event = Event::factory()
            ->withRsvpSettings(['collect_companions' => true, 'fields' => EventRsvpSetting::fieldsRequiring(['cpf'])])
            ->create(['is_published' => true]);

        $this->assertSame(['whatsapp'], $event->rsvpRequiredFields());
        $this->assertFalse($event->collectsRsvpCompanions());

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 3,
        ])->assertSessionHasNoErrors();

        $guest = Guest::firstWhere('name', 'Maria');

        $this->assertSame(3, $guest->rsvp_guests_count);
        $this->assertSame(0, $guest->companions()->count());
    }

    public function test_the_host_chosen_fields_become_mandatory(): void
    {
        $event = $this->premiumEvent(['email', 'cpf'], collectCompanions: false);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria'],
            'attending' => false,
        ])->assertSessionHasErrors(['guest.email', 'guest.cpf'])
            ->assertSessionDoesntHaveErrors('guest.whatsapp');

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'email' => 'maria@example.com', 'cpf' => '529.982.247-25'],
            'attending' => false,
        ])->assertSessionHasNoErrors();

        $guest = Guest::firstWhere('name', 'Maria');

        $this->assertNull($guest->whatsapp);
        $this->assertSame('52998224725', $guest->cpf);
    }

    public function test_an_invalid_cpf_is_rejected(): void
    {
        $event = $this->premiumEvent(['cpf'], collectCompanions: false);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'cpf' => '111.111.111-11'],
            'attending' => false,
        ])->assertSessionHasErrors('guest.cpf');
    }

    public function test_it_stores_each_companion_with_the_mandatory_fields(): void
    {
        $event = $this->premiumEvent(['whatsapp', 'email']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999', 'email' => 'maria@example.com'],
            'attending' => true,
            'guests_count' => 3,
            'companions' => [
                ['name' => 'João', 'whatsapp' => '11988888888', 'email' => 'joao@example.com'],
                ['name' => 'Ana', 'whatsapp' => '11977777777', 'email' => 'ana@example.com'],
            ],
        ])->assertSessionHasNoErrors();

        $host = Guest::firstWhere('name', 'Maria');

        $this->assertSame(3, $host->rsvp_guests_count);
        $this->assertEqualsCanonicalizing(['João', 'Ana'], $host->companions()->pluck('name')->all());
        $this->assertTrue($host->companions->every(fn (Guest $companion) => $companion->rsvp_status === Guest::RSVP_CONFIRMED));
    }

    public function test_companions_must_match_the_headcount_and_have_the_mandatory_fields(): void
    {
        $event = $this->premiumEvent(['email']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'email' => 'maria@example.com'],
            'attending' => true,
            'guests_count' => 3,
            'companions' => [
                ['name' => 'João'],
            ],
        ])->assertSessionHasErrors(['companions', 'companions.0.email']);
    }

    public function test_a_companion_who_confirms_on_their_own_becomes_an_individual_confirmation(): void
    {
        $event = $this->premiumEvent(['whatsapp']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 3,
            'companions' => [
                ['name' => 'João', 'whatsapp' => '11988888888'],
                ['name' => 'Ana', 'whatsapp' => '11977777777'],
            ],
        ]);

        $host = Guest::firstWhere('name', 'Maria');
        $joao = Guest::firstWhere('name', 'João');

        // João answers from his own phone, typing the number formatted.
        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'João Silva', 'whatsapp' => '(11) 98888-8888'],
            'attending' => true,
            'guests_count' => 2,
            'companions' => [
                ['name' => 'Pedro', 'whatsapp' => '11966666666'],
            ],
        ])->assertSessionHasNoErrors();

        $joao->refresh();

        $this->assertNull($joao->companion_of_guest_id);
        $this->assertSame('João Silva', $joao->name);
        $this->assertSame(2, $joao->rsvp_guests_count);
        $this->assertSame(2, $host->fresh()->rsvp_guests_count);
        $this->assertSame(['Ana'], $host->companions()->pluck('name')->all());
        $this->assertSame(4, (int) $event->guests()->where('rsvp_status', Guest::RSVP_CONFIRMED)->sum('rsvp_guests_count'));
    }

    public function test_companions_are_recognised_by_email_or_cpf_too(): void
    {
        $event = $this->premiumEvent(['email', 'cpf']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'email' => 'maria@example.com', 'cpf' => '52998224725'],
            'attending' => true,
            'guests_count' => 2,
            'companions' => [
                ['name' => 'João', 'email' => 'joao@example.com', 'cpf' => '15350946056'],
            ],
        ]);

        // Different e-mail casing and a formatted CPF still match João.
        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'João', 'email' => 'outro@example.com', 'cpf' => '153.509.460-56'],
            'attending' => false,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $event->guests()->count());
        $this->assertSame(1, Guest::firstWhere('name', 'Maria')->rsvp_guests_count);
        $this->assertSame(Guest::RSVP_DECLINED, Guest::firstWhere('name', 'João')->rsvp_status);
    }

    public function test_someone_who_already_confirmed_individually_is_not_counted_again(): void
    {
        $event = $this->premiumEvent(['whatsapp']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'João', 'whatsapp' => '11988888888'],
            'attending' => true,
            'guests_count' => 1,
        ]);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 2,
            'companions' => [
                ['name' => 'João', 'whatsapp' => '11988888888'],
            ],
        ])->assertSessionHasNoErrors();

        $host = Guest::firstWhere('name', 'Maria');

        $this->assertSame(1, $host->rsvp_guests_count);
        $this->assertNull(Guest::firstWhere('name', 'João')->companion_of_guest_id);
        $this->assertSame(2, $event->guests()->count());
    }

    public function test_resubmitting_replaces_the_companion_list(): void
    {
        $event = $this->premiumEvent(['whatsapp']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 3,
            'companions' => [
                ['name' => 'João', 'whatsapp' => '11988888888'],
                ['name' => 'Ana', 'whatsapp' => '11977777777'],
            ],
        ]);

        $ana = Guest::firstWhere('name', 'Ana');
        Order::factory()->create(['event_id' => $event->id, 'guest_id' => $ana->id]);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 2,
            'companions' => [
                ['name' => 'Pedro', 'whatsapp' => '11966666666'],
            ],
        ])->assertSessionHasNoErrors();

        $host = Guest::firstWhere('name', 'Maria');

        $this->assertSame(['Pedro'], $host->companions()->pluck('name')->all());
        $this->assertNull(Guest::firstWhere('name', 'João'));

        // Ana gave a gift, so she stays as a guest — just without an RSVP.
        $ana->refresh();
        $this->assertNull($ana->companion_of_guest_id);
        $this->assertNull($ana->rsvp_status);
    }

    public function test_declining_removes_the_companions(): void
    {
        $event = $this->premiumEvent(['whatsapp']);

        $payload = [
            'guest' => ['name' => 'Maria', 'whatsapp' => '11999999999'],
            'attending' => true,
            'guests_count' => 2,
            'companions' => [['name' => 'João', 'whatsapp' => '11988888888']],
        ];

        $this->post("/{$event->slug}/rsvp", $payload);
        $this->post("/{$event->slug}/rsvp", [...$payload, 'attending' => false])->assertSessionHasNoErrors();

        $this->assertSame(1, $event->guests()->count());
        $this->assertNull(Guest::firstWhere('name', 'Maria')->rsvp_guests_count);
    }

    public function test_missing_details_get_a_polite_message(): void
    {
        $event = $this->premiumEvent(['whatsapp', 'cpf']);

        $this->post("/{$event->slug}/rsvp", [
            'guest' => ['name' => 'Maria', 'whatsapp' => '', 'cpf' => '111.111.111-11'],
            'attending' => true,
            'guests_count' => 2,
            'companions' => [['name' => '', 'whatsapp' => '11988887777', 'cpf' => '529.982.247-25']],
        ])->assertSessionHasErrors([
            'guest.whatsapp' => 'Por favor, informe seu WhatsApp.',
            'guest.cpf' => 'Esse CPF não parece válido. Pode conferir os números?',
            'companions.0.name' => 'Por favor, informe o nome do acompanhante.',
        ]);
    }
}
