<?php

namespace Tests\Feature\Guests;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use App\Models\Events\EventRsvpSetting;
use App\Models\Guests\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * A family shares a phone: one person's answer must never overwrite
 * another's, and nobody leaves the list without a trace.
 */
class SharedPhoneRsvpTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()
            ->withFeatures(Feature::GuestList)
            ->withRsvpSettings([
                'collect_companions' => true,
                'fields' => EventRsvpSetting::fieldsRequiring([]),
                'companion_fields' => EventRsvpSetting::fieldsRequiring([]),
            ])
            ->create(['is_published' => true]);
    }

    private function rsvp(array $guest, array $companions = [], ?string $cookie = null): string
    {
        $request = $cookie ? $this->withCookie(Guest::cookieName($this->event), $cookie) : $this;

        $response = $request->post("/{$this->event->slug}/rsvp", [
            'guest' => $guest,
            'attending' => true,
            'guests_count' => 1 + count($companions),
            'companions' => $companions,
        ])->assertSessionHasNoErrors();

        return $response->getCookie(Guest::cookieName($this->event))->getValue();
    }

    public function test_someone_else_answering_on_the_same_phone_doesnt_replace_the_first_answer(): void
    {
        // Flávia answers for her family on Breno's phone…
        $cookie = $this->rsvp(['name' => 'Flávia Barbosa de oliveira', 'whatsapp' => '(61) 99878-9696'], [
            ['name' => 'Breno Dias'], ['name' => 'Danilo Dias'], ['name' => 'Juan guilherme'],
        ]);

        // …and later someone else answers on the same phone.
        $this->rsvp(['name' => 'Julio Cezar Aquino'], [['name' => 'Bia Cezar Aquino']], $cookie);

        $flavia = Guest::firstWhere('name', 'Flávia Barbosa de oliveira');

        $this->assertNotNull($flavia);
        $this->assertSame(4, $flavia->rsvp_guests_count);
        $this->assertEqualsCanonicalizing(['Breno Dias', 'Danilo Dias', 'Juan guilherme'], $flavia->companions()->pluck('name')->all());
        $this->assertSame(2, Guest::firstWhere('name', 'Julio Cezar Aquino')->rsvp_guests_count);
        $this->assertSame(6, $this->event->rsvpHeadcount()['people']);
    }

    public function test_someone_else_giving_the_same_number_doesnt_replace_the_first_answer(): void
    {
        $this->rsvp(['name' => 'Liduina', 'whatsapp' => '61982064218'], [['name' => 'Laura eva']]);
        $this->rsvp(['name' => 'Renata', 'whatsapp' => '61982064218'], [['name' => 'Ester vargas']]);

        $this->assertSame(['Laura eva'], Guest::firstWhere('name', 'Liduina')->companions()->pluck('name')->all());
        $this->assertSame(['Ester vargas'], Guest::firstWhere('name', 'Renata')->companions()->pluck('name')->all());
        $this->assertSame(4, $this->event->rsvpHeadcount()['people']);
    }

    public function test_the_same_person_answering_again_updates_their_answer(): void
    {
        $cookie = $this->rsvp(['name' => 'Flávia', 'whatsapp' => '61911112222']);
        $this->rsvp(['name' => 'Flavia Barbosa'], [], $cookie);
        $this->rsvp(['name' => 'flávia barbosa', 'whatsapp' => '(61) 91111-2222']);

        $this->assertSame(1, $this->event->guests()->count());
        $this->assertSame('flávia barbosa', $this->event->guests()->first()->name);
    }

    public function test_companions_taken_off_a_list_go_to_the_trash_with_a_trace(): void
    {
        $cookie = $this->rsvp(['name' => 'Flávia'], [['name' => 'Breno'], ['name' => 'Danilo']]);
        $this->rsvp(['name' => 'Flávia'], [['name' => 'Breno']], $cookie);

        $danilo = Guest::onlyTrashed()->firstWhere('name', 'Danilo');

        $this->assertNotNull($danilo, 'restorable from the trash');
        $this->assertNull($danilo->companion_of_guest_id);
        $this->assertNull($danilo->rsvp_status);
        $this->assertSame(2, Guest::firstWhere('name', 'Flávia')->rsvp_guests_count);
        $this->assertTrue(Activity::where('subject_id', $danilo->id)->where('event', 'companion_removed')->exists());

        $danilo->restore();
        $this->assertNull($danilo->fresh()->deleted_at);
    }
}
