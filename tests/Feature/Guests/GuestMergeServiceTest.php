<?php

namespace Tests\Feature\Guests;

use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Guests\GuestMessage;
use App\Models\Orders\Order;
use App\Services\Guests\GuestMergeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GuestMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The father lists his son by name only; the son confirms on his own
     * with his WhatsApp — two records, the son counted twice.
     *
     * @return array{Event, Guest, Guest, Guest}
     */
    private function sonCountedTwice(): array
    {
        $event = Event::factory()->create();

        $father = $event->guests()->create([
            'name' => 'Carlos',
            'whatsapp' => '11911111111',
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_guests_count' => 2,
            'rsvp_responded_at' => now()->subDay(),
        ]);
        $listedSon = $event->guests()->create([
            'name' => 'Pedro',
            'age' => 12,
            'companion_of_guest_id' => $father->id,
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_responded_at' => now()->subDay(),
        ]);
        $son = $event->guests()->create([
            'name' => 'Pedro Henrique',
            'whatsapp' => '11922222222',
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_guests_count' => 1,
            'rsvp_responded_at' => now(),
        ]);

        return [$event, $father, $listedSon, $son];
    }

    public function test_the_own_confirmation_prevails_and_the_son_is_counted_once(): void
    {
        [$event, $father, $listedSon, $son] = $this->sonCountedTwice();

        $this->assertSame(3, $event->rsvpHeadcount()['people']);

        // Whichever row the host started from.
        $kept = app(GuestMergeService::class)->execute($listedSon, $son);

        $this->assertTrue($kept->is($son));
        $this->assertSame('Pedro Henrique', $kept->name);
        $this->assertSame('11922222222', $kept->whatsapp);
        $this->assertSame(12, $kept->age, 'the age only the father told is kept');
        $this->assertNull($kept->companion_of_guest_id);
        $this->assertNull(Guest::withTrashed()->find($listedSon->id));
        $this->assertSame(1, $father->refresh()->rsvp_guests_count);
        $this->assertSame(2, $event->rsvpHeadcount()['people']);
    }

    public function test_gifts_and_messages_move_to_the_record_that_stays(): void
    {
        [$event, , $listedSon, $son] = $this->sonCountedTwice();

        $order = Order::factory()->create(['event_id' => $event->id, 'guest_id' => $listedSon->id, 'status' => Order::STATUS_PAID]);
        $message = GuestMessage::factory()->create(['event_id' => $event->id, 'guest_id' => $listedSon->id]);

        app(GuestMergeService::class)->execute($son, $listedSon);

        $this->assertSame($son->id, $order->refresh()->guest_id);
        $this->assertSame($son->id, $message->refresh()->guest_id);
    }

    public function test_a_listed_companion_absorbs_a_pre_listed_guest_without_answer(): void
    {
        [$event, $father, $listedSon] = $this->sonCountedTwice();

        $preListed = $event->guests()->create(['name' => 'Pedro', 'email' => 'pedro@example.com']);

        $kept = app(GuestMergeService::class)->execute($preListed, $listedSon);

        $this->assertTrue($kept->is($listedSon));
        $this->assertSame('pedro@example.com', $kept->email);
        $this->assertSame($father->id, $kept->companion_of_guest_id);
        $this->assertSame(2, $father->refresh()->rsvp_guests_count, 'still the same two people');
    }

    public function test_between_two_own_answers_the_latest_wins_and_takes_the_companions(): void
    {
        $event = Event::factory()->create();

        $old = $event->guests()->create([
            'name' => 'Ana',
            'whatsapp' => '11933333333',
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_guests_count' => 2,
            'rsvp_responded_at' => now()->subDays(2),
        ]);
        $companion = $event->guests()->create([
            'name' => 'Bia',
            'companion_of_guest_id' => $old->id,
            'rsvp_status' => Guest::RSVP_CONFIRMED,
        ]);
        $latest = $event->guests()->create([
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'rsvp_status' => Guest::RSVP_CONFIRMED,
            'rsvp_guests_count' => 1,
            'rsvp_responded_at' => now(),
        ]);

        $kept = app(GuestMergeService::class)->execute($old, $latest);

        $this->assertTrue($kept->is($latest));
        $this->assertSame('11933333333', $kept->whatsapp);
        $this->assertSame($latest->id, $companion->refresh()->companion_of_guest_id);
        $this->assertSame(2, $kept->rsvp_guests_count);
        $this->assertSame(2, $event->rsvpHeadcount()['people']);
    }

    public function test_a_guest_cant_be_merged_with_their_own_companion(): void
    {
        [, $father, $listedSon] = $this->sonCountedTwice();

        $this->expectException(InvalidArgumentException::class);

        app(GuestMergeService::class)->execute($father, $listedSon);
    }

    public function test_guests_of_different_events_cant_be_merged(): void
    {
        $guest = Event::factory()->create()->guests()->create(['name' => 'Ana']);
        $other = Event::factory()->create()->guests()->create(['name' => 'Ana']);

        $this->expectException(InvalidArgumentException::class);

        app(GuestMergeService::class)->execute($guest, $other);
    }
}
