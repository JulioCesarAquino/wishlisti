<?php

namespace Tests\Feature\Identity;

use App\Filament\Resources\Identity\InviteRequests\Pages\ListInviteRequests;
use App\Models\Identity\InviteRequest;
use App\Models\User;
use App\Notifications\Identity\HostInviteLinkNotification;
use App\Services\Identity\InviteRequestRegenerateLinkService;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class InviteRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_request_an_invite(): void
    {
        $response = $this->post('/solicitar-convite', [
            'name' => 'Maria Convidada',
            'email' => 'maria@example.com',
            'whatsapp' => '5511999999999',
        ]);

        $response->assertRedirect();
        $response->assertInertiaFlash('toast');

        $this->assertDatabaseHas('invite_requests', [
            'name' => 'Maria Convidada',
            'email' => 'maria@example.com',
            'status' => InviteRequest::STATUS_PENDING,
        ]);
    }

    public function test_hosts_cannot_access_invite_requests(): void
    {
        $host = User::factory()->create(['is_admin' => false]);

        $this->actingAs($host)
            ->get('/admin/invite-requests')
            ->assertForbidden();
    }

    public function test_admin_can_approve_a_request_and_the_generated_link_sets_a_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $inviteRequest = InviteRequest::factory()->create([
            'name' => 'Maria Convidada',
            'email' => 'maria@example.com',
        ]);

        $this->actingAs($admin);

        Livewire::test(ListInviteRequests::class)
            ->callTableAction('approve', $inviteRequest);

        $inviteRequest->refresh();
        $this->assertSame(InviteRequest::STATUS_APPROVED, $inviteRequest->status);
        $this->assertNotNull($inviteRequest->invited_user_id);

        $host = User::find($inviteRequest->invited_user_id);
        $this->assertSame('maria@example.com', $host->email);
        $this->assertNotNull($host->email_verified_at);

        // Simulate following the generated password-reset link end-to-end,
        // as the invited guest (not the admin who approved the request).
        $token = Password::broker()->createToken($host);

        $this->app['auth']->guard()->logout();

        Livewire::test(ResetPassword::class, ['email' => $host->email, 'token' => $token])
            ->fillForm([
                'password' => 'nova-senha-123',
                'passwordConfirmation' => 'nova-senha-123',
            ])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('nova-senha-123', $host->fresh()->password));
    }

    public function test_admin_can_regenerate_the_link_of_an_approved_request(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $inviteRequest = InviteRequest::factory()->create(['email' => 'maria@example.com']);

        $this->actingAs($admin);

        Livewire::test(ListInviteRequests::class)
            ->callTableAction('approve', $inviteRequest)
            ->callTableAction('regenerateLink', $inviteRequest->fresh())
            ->assertNotified();

        $link = app(InviteRequestRegenerateLinkService::class)->execute($inviteRequest->fresh());

        $this->assertStringContainsString('/admin/password-reset/reset', $link);
    }

    public function test_approving_emails_the_host_the_link_to_set_a_password(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'email' => 'admin@example.com']));
        $inviteRequest = InviteRequest::factory()->create(['name' => 'Maria', 'email' => 'maria@example.com']);

        Livewire::test(ListInviteRequests::class)
            ->callTableAction('approve', $inviteRequest)
            ->assertNotified('Convite aprovado');

        $host = User::where('email', 'maria@example.com')->firstOrFail();

        Notification::assertSentTo($host, HostInviteLinkNotification::class, function (HostInviteLinkNotification $notification) use ($host): bool {
            $mail = $notification->toMail($host);

            $html = (string) $mail->render();

            return $notification->firstTime
                && str_contains($notification->link, '/admin/password-reset/reset')
                && $mail->subject === 'Seu acesso ao Wishlisti foi aprovado'
                && str_contains($html, 'Olá, Maria!')
                && str_contains($html, 'href="'.e($notification->link).'"')
                && str_contains($html, 'O link vale por 3 dias.')
                // From a no-reply address: replies reach the admin.
                && $mail->replyTo[0][0] === 'admin@example.com';
        });
    }

    public function test_a_new_link_is_emailed_too(): void
    {
        Notification::fake();

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $inviteRequest = InviteRequest::factory()->create(['email' => 'maria@example.com']);

        Livewire::test(ListInviteRequests::class)
            ->callTableAction('approve', $inviteRequest)
            ->callTableAction('regenerateLink', $inviteRequest->fresh());

        $host = User::where('email', 'maria@example.com')->firstOrFail();

        Notification::assertSentToTimes($host, HostInviteLinkNotification::class, 2);
        Notification::assertSentTo($host, HostInviteLinkNotification::class, fn (HostInviteLinkNotification $notification): bool => ! $notification->firstTime);
    }

    public function test_approving_still_works_when_the_email_cannot_be_sent(): void
    {
        config(['mail.default' => 'failing', 'mail.mailers.failing' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1]]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $inviteRequest = InviteRequest::factory()->create(['email' => 'maria@example.com']);

        Livewire::test(ListInviteRequests::class)
            ->callTableAction('approve', $inviteRequest)
            ->assertNotified('Convite aprovado');

        // The account exists, and the admin still has the link to send by hand.
        $this->assertSame(InviteRequest::STATUS_APPROVED, $inviteRequest->fresh()->status);
        $this->assertDatabaseHas('users', ['email' => 'maria@example.com']);
    }

    public function test_the_emailed_link_still_works_the_next_day(): void
    {
        $host = User::factory()->create();
        $token = Password::broker()->createToken($host);

        $this->travel(2)->days();

        Livewire::test(ResetPassword::class, ['email' => $host->email, 'token' => $token])
            ->fillForm(['password' => 'nova-senha-123', 'passwordConfirmation' => 'nova-senha-123'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('nova-senha-123', $host->fresh()->password));
    }

    public function test_admin_can_reject_a_request(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $inviteRequest = InviteRequest::factory()->create();

        $this->actingAs($admin);

        Livewire::test(ListInviteRequests::class)
            ->callTableAction('reject', $inviteRequest);

        $this->assertSame(InviteRequest::STATUS_REJECTED, $inviteRequest->fresh()->status);
    }
}
