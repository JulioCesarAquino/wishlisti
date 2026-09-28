<?php

namespace Tests\Feature\Identity;

use App\Filament\Resources\Identity\InviteRequests\Pages\ListInviteRequests;
use App\Models\Identity\InviteRequest;
use App\Models\User;
use App\Services\Identity\InviteRequestRegenerateLinkService;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
