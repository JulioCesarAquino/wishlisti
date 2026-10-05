<?php

namespace App\Notifications\Identity;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The e-mail a host gets when the admin approves their invite request (or
 * makes them a new link): the link to set their password and come in.
 */
class HostInviteLinkNotification extends Notification
{
    public function __construct(
        public string $link,
        public bool $firstTime = true,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $subject = $this->firstTime ? 'Seu acesso ao Wishlisti foi aprovado' : 'Seu novo link de acesso ao Wishlisti';
        $intro = $this->firstTime
            ? 'Seu pedido de acesso ao Wishlisti foi aprovado. Agora é só criar a sua senha para entrar e montar a página do seu evento.'
            : 'Aqui está um novo link para você criar a sua senha e entrar no Wishlisti.';

        $message = (new MailMessage)->subject($subject);

        // It's sent from a no-reply address: a reply goes to the admin.
        if ($admin = User::where('is_admin', true)->orderBy('id')->first()) {
            $message->replyTo($admin->email, config('app.name'));
        }

        return $message
            ->view(['mail.host-invite', 'mail.host-invite-text'], [
                'subject' => $subject,
                'preheader' => $this->firstTime ? 'Crie sua senha e comece a montar o seu evento.' : 'Seu novo link para criar a senha.',
                'badge' => $this->firstTime ? 'Acesso aprovado' : 'Novo link de acesso',
                'name' => Str::before($notifiable->name, ' '),
                'intro' => $intro,
                'link' => $this->link,
                'validity' => $this->validity(),
                'logoUrl' => asset('apple-touch-icon.png'),
                'homeUrl' => url('/'),
                'homeHost' => parse_url(url('/'), PHP_URL_HOST),
            ]);
    }

    /** "3 dias", "24 horas": how long the link lasts. */
    public function validity(): string
    {
        $hours = intdiv((int) config('auth.passwords.users.expire'), 60);

        return $hours >= 48 ? intdiv($hours, 24).' dias' : "{$hours} horas";
    }
}
