<?php

namespace Tests\Feature\Identity;

use App\Models\User;
use App\Notifications\Identity\PasswordResetNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Esqueceu a senha?" on the panel's login: our e-mail, sent right away.
 */
class PasswordResetEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_emails_a_link_that_resets_it(): void
    {
        Notification::fake();
        User::factory()->create(['is_admin' => true, 'email' => 'admin@example.com']);
        $host = User::factory()->create(['name' => 'Leide Souza', 'email' => 'leide@example.com']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'leide@example.com'])
            ->call('request')
            ->assertHasNoFormErrors();

        $sent = null;

        Notification::assertSentTo($host, PasswordResetNotification::class, function (PasswordResetNotification $notification) use ($host, &$sent): bool {
            $sent = $notification;
            $mail = $notification->toMail($host);
            $html = (string) $mail->render();

            return str_contains($notification->url, '/admin/password-reset/reset')
                && $mail->subject === 'Redefina sua senha do Wishlisti'
                && str_contains($html, 'Olá, Leide!')
                && str_contains($html, 'Criar nova senha')
                && str_contains($html, 'href="'.e($notification->url).'"')
                && $mail->replyTo[0][0] === 'admin@example.com';
        });

        // Never queued: there's no queue worker in production.
        $this->assertNotInstanceOf(ShouldQueue::class, $sent);

        Livewire::test(ResetPassword::class, ['email' => $host->email, 'token' => $sent->token])
            ->fillForm(['password' => 'nova-senha-123', 'passwordConfirmation' => 'nova-senha-123'])
            ->call('resetPassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('nova-senha-123', $host->fresh()->password));
    }

    public function test_an_unknown_email_gets_nothing(): void
    {
        Notification::fake();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'ninguem@example.com'])
            ->call('request');

        Notification::assertNothingSent();
    }
}
