<?php

namespace Tests\Feature\Guests;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\Pages\ManageEventMessages;
use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anonymous guestbook messages: shown as "Anônimo" on the page and to the
 * host; the name given, if any, only reaches the admin.
 */
class AnonymousGuestMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $host;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = User::factory()->create(['is_admin' => false]);
        $this->event = Event::factory()->withFeatures(Feature::Guestbook)->create(['user_id' => $this->host->id, 'is_published' => true]);
    }

    public function test_a_name_is_required_unless_anonymous(): void
    {
        $this->post("/{$this->event->slug}/messages", ['author_name' => '', 'message' => 'Felicidades!'])
            ->assertSessionHasErrors(['author_name' => 'Por favor, informe seu nome, ou marque "Enviar anonimamente".']);

        $this->post("/{$this->event->slug}/messages", ['author_name' => '', 'message' => 'Felicidades!', 'anonymous' => true])
            ->assertSessionHasNoErrors();

        $message = GuestMessage::sole();
        $this->assertTrue($message->is_anonymous);
        $this->assertNull($message->author_name);
    }

    public function test_the_page_never_receives_the_name_of_an_anonymous_author(): void
    {
        $this->post("/{$this->event->slug}/messages", ['author_name' => 'Tia Rosa', 'message' => 'Felicidades!', 'anonymous' => true]);
        GuestMessage::query()->update(['approved_at' => now()]);

        $this->get("/{$this->event->slug}")
            ->assertInertia(fn ($page) => $page->where('messages.0.author_name', 'Anônimo'))
            ->assertDontSee('Tia Rosa');
    }

    public function test_the_host_sees_anonimo_and_cannot_find_the_author_by_name(): void
    {
        $this->post("/{$this->event->slug}/messages", ['author_name' => 'Tia Rosa', 'message' => 'Felicidades!', 'anonymous' => true]);
        $this->actingAs($this->host);

        Livewire::test(ManageEventMessages::class, ['record' => $this->event->getRouteKey()])
            ->assertSee('Anônimo')
            ->assertDontSee('Tia Rosa')
            ->searchTable('Rosa')
            ->assertCountTableRecords(0);
    }

    public function test_the_admin_sees_the_name_given(): void
    {
        $this->post("/{$this->event->slug}/messages", ['author_name' => 'Tia Rosa', 'message' => 'Felicidades!', 'anonymous' => true]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ManageEventMessages::class, ['record' => $this->event->getRouteKey()])
            ->assertSee('Tia Rosa (anônimo)')
            ->searchTable('Rosa')
            ->assertCountTableRecords(1);
    }
}
