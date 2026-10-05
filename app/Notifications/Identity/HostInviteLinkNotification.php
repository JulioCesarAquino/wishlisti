<?php

namespace App\Notifications\Identity;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        $hours = intdiv((int) config('auth.passwords.users.expire'), 60);
        $validity = $hours >= 48 ? intdiv($hours, 24).' dias' : "{$hours} horas";

        return (new MailMessage)
            ->subject($this->firstTime ? 'Seu acesso ao Wishlisti foi aprovado' : 'Seu novo link de acesso ao Wishlisti')
            ->greeting("Olá, {$notifiable->name}!")
            ->line($this->firstTime
                ? 'Seu pedido de acesso ao Wishlisti foi aprovado. Agora é só criar a sua senha para entrar e montar a página do seu evento.'
                : 'Aqui está um novo link para você criar a sua senha e entrar no Wishlisti.')
            ->action('Criar minha senha', $this->link)
            ->line("O link vale por {$validity}. Se ele expirar, responda este e-mail ou fale com a gente que enviamos outro.");
    }
}
