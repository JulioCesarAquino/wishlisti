<?php

namespace Tests\Feature\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Services\Guests\GuestResolveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestResolveServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_new_guest_when_nothing_matches(): void
    {
        $event = Event::factory()->create();

        $guest = app(GuestResolveService::class)->execute(
            $event,
            ['name' => 'Maria Nova', 'whatsapp' => '11999999999'],
            null,
        );

        $this->assertSame(1, $event->guests()->count());
        $this->assertSame('Maria Nova', $guest->name);
    }

    public function test_it_reuses_a_guest_from_another_device_by_matching_whatsapp(): void
    {
        $event = Event::factory()->create();
        $existing = $event->guests()->create([
            'name' => 'Maria Convidada',
            'whatsapp' => '(11) 99999-9999',
            'identifier' => (string) Str::uuid(),
        ]);

        // No cookie this time (new browser/device), but the phone number
        // (typed with different formatting) matches an existing guest.
        $guest = app(GuestResolveService::class)->execute(
            $event,
            ['name' => 'Maria Convidada', 'whatsapp' => '11999999999', 'email' => 'maria@example.com'],
            null,
        );

        $this->assertSame(1, $event->guests()->count());
        $this->assertSame($existing->id, $guest->id);
        $this->assertSame('maria@example.com', $guest->fresh()->email);
    }

    public function test_it_does_not_merge_guests_with_different_whatsapp_numbers(): void
    {
        $event = Event::factory()->create();
        $event->guests()->create([
            'name' => 'Maria Convidada',
            'whatsapp' => '11999999999',
            'identifier' => (string) Str::uuid(),
        ]);

        app(GuestResolveService::class)->execute(
            $event,
            ['name' => 'Outra Pessoa', 'whatsapp' => '11988888888'],
            null,
        );

        $this->assertSame(2, $event->guests()->count());
    }

    public function test_whatsapp_matching_is_scoped_to_the_same_event(): void
    {
        $eventA = Event::factory()->create();
        $eventB = Event::factory()->create();

        $eventA->guests()->create([
            'name' => 'Maria Convidada',
            'whatsapp' => '11999999999',
            'identifier' => (string) Str::uuid(),
        ]);

        app(GuestResolveService::class)->execute(
            $eventB,
            ['name' => 'Maria Convidada', 'whatsapp' => '11999999999'],
            null,
        );

        $this->assertSame(1, $eventA->guests()->count());
        $this->assertSame(1, $eventB->guests()->count());
    }

    public function test_the_cookie_identifier_still_takes_priority_over_whatsapp_matching(): void
    {
        $event = Event::factory()->create();

        $byCookie = $event->guests()->create([
            'name' => 'Nome',
            'whatsapp' => '11999999999',
            'identifier' => (string) Str::uuid(),
        ]);

        $byWhatsapp = $event->guests()->create([
            'name' => 'Outro Registro',
            'whatsapp' => '11988888888',
            'identifier' => (string) Str::uuid(),
        ]);

        $guest = app(GuestResolveService::class)->execute(
            $event,
            ['name' => 'Nome Atualizado', 'whatsapp' => $byWhatsapp->whatsapp],
            $byCookie->identifier,
        );

        $this->assertSame($byCookie->id, $guest->id);
        $this->assertSame('Nome Atualizado', $guest->name);
    }
}
