<?php

namespace Tests\Feature\Notifications;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Identity\InviteRequests\InviteRequestResource;
use App\Models\Events\Event;
use App\Models\Guests\GuestMessage;
use App\Models\User;
use App\Notifications\Events\GuestMessageReceivedNotification;
use App\Notifications\Events\GuestMessagesDigestNotification;
use App\Notifications\Identity\InviteRequestReceivedNotification;
use Filament\Notifications\Livewire\DatabaseNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * What needs the user's attention, on the panel's bell and by e-mail: a
 * message waiting for the hosts' approval, an invite request for the
 * admins.
 */
class HostNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $coHost;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Ana Souza', 'is_admin' => false]);
        $this->coHost = User::factory()->create(['is_admin' => false]);
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->event = Event::factory()->withFeatures(Feature::Guestbook)->create([
            'user_id' => $this->owner->id,
            'title' => 'Maria Júlia',
            'is_published' => true,
        ]);
        $this->event->coHosts()->attach($this->coHost);
    }

    private function postMessage(string $author, string $message, bool $anonymous = false): void
    {
        $this->post("/{$this->event->slug}/messages", ['author_name' => $author, 'message' => $message, 'anonymous' => $anonymous])
            ->assertSessionHasNoErrors();
    }

    public function test_a_new_message_rings_the_bell_of_each_host(): void
    {
        $this->postMessage('Tia Rosa', 'Felicidades, Maju!');

        foreach ([$this->owner, $this->coHost] as $host) {
            $notification = $host->notifications()->sole();

            $this->assertSame(GuestMessageReceivedNotification::class, $notification->type);
            $this->assertSame('filament', $notification->data['format']);
            $this->assertSame('Novo recado em Maria Júlia', $notification->data['title']);
            $this->assertSame('Tia Rosa: “Felicidades, Maju!”', $notification->data['body']);
            $this->assertSame(EventResource::getUrl('messages', ['record' => $this->event]), $notification->data['actions'][0]['url']);
        }

        // Not the admin's event: not on their bell.
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_the_bell_shows_the_notification(): void
    {
        $this->postMessage('Tia Rosa', 'Felicidades, Maju!');

        $this->actingAs($this->owner);

        Livewire::test(DatabaseNotifications::class)
            ->assertSee('Novo recado em Maria Júlia')
            ->assertSee('Ver recados');
    }

    public function test_an_anonymous_author_stays_anonymous_on_the_bell(): void
    {
        $this->postMessage('Tia Rosa', 'Felicidades!', anonymous: true);

        $this->assertSame('Anônimo: “Felicidades!”', $this->owner->notifications()->sole()->data['body']);
    }

    public function test_the_hosts_get_one_email_with_the_new_messages(): void
    {
        Notification::fake();

        $this->postMessage('Tia Rosa', 'Felicidades, Maju!');
        $this->postMessage('Vovó', 'Te amo!');
        // Approved in the meantime: already seen.
        $this->postMessage('Tio João', 'Parabéns!');
        GuestMessage::where('author_name', 'Tio João')->update(['approved_at' => now()]);

        $this->artisan('messages:notify-hosts')->expectsOutput('E-mails enviados: 2.')->assertSuccessful();

        foreach ([$this->owner, $this->coHost] as $host) {
            Notification::assertSentTo($host, GuestMessagesDigestNotification::class, fn ($notification) => $notification->event->is($this->event)
                && $notification->messages->pluck('author_name')->all() === ['Tia Rosa', 'Vovó']);
        }

        Notification::assertNotSentTo($this->admin, GuestMessagesDigestNotification::class);

        // Each message goes in a single e-mail.
        $this->artisan('messages:notify-hosts')->expectsOutput('E-mails enviados: 0.');
    }

    public function test_the_email_quotes_the_messages_and_links_to_them(): void
    {
        $this->postMessage('Tia Rosa', 'Felicidades, Maju!');
        $this->postMessage('Fulano', 'Segredo', anonymous: true);

        $mail = (new GuestMessagesDigestNotification($this->event, GuestMessage::orderBy('id')->get()))->toMail($this->owner);
        $html = (string) $mail->render();

        $this->assertSame('2 recados novos em Maria Júlia', $mail->subject);
        $this->assertStringContainsString('Olá, Ana!', $html);
        $this->assertStringContainsString('Felicidades, Maju!', $html);
        $this->assertStringContainsString('Anônimo', $html);
        $this->assertStringNotContainsString('Fulano', $html);
        $this->assertStringContainsString(e(EventResource::getUrl('messages', ['record' => $this->event])), $html);
    }

    public function test_an_invite_request_rings_the_admins_bell_and_emails_them(): void
    {
        Notification::fake();
        $this->withoutDefer();

        $this->post('/solicitar-convite', [
            'name' => 'Maria Convidada',
            'email' => 'maria@example.com',
            'whatsapp' => '5511999999999',
        ])->assertRedirect();

        Notification::assertSentTo($this->admin, InviteRequestReceivedNotification::class, function ($notification, array $channels) {
            $this->assertSame(['database', 'mail'], $channels);
            $this->assertSame(InviteRequestResource::getUrl(), $notification->toDatabase($this->admin)['actions'][0]['url']);
            $this->assertStringContainsString('maria@example.com', (string) $notification->toMail($this->admin)->render());

            return true;
        });
        Notification::assertNotSentTo([$this->owner, $this->coHost], InviteRequestReceivedNotification::class);
    }

    public function test_a_failed_email_doesnt_get_in_the_way_of_the_invite_request(): void
    {
        $this->withoutDefer();
        config(['mail.default' => 'failover', 'mail.mailers.failover.mailers' => []]);

        $this->post('/solicitar-convite', [
            'name' => 'Maria Convidada',
            'email' => 'maria@example.com',
            'whatsapp' => '5511999999999',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invite_requests', ['email' => 'maria@example.com']);
    }
}
