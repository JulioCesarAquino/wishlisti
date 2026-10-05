<?php

namespace App\Notifications\Identity;

/**
 * The "forgot my password" e-mail of the panel, in place of Filament's own
 * (bound in AppServiceProvider): our look, and sent right away — Filament's
 * is queued, and there's no queue worker. Filament fills in the url.
 */
class PasswordResetNotification extends AccessLinkNotification
{
    public string $url = '';

    public function __construct(public string $token) {}

    protected function link(): string
    {
        return $this->url;
    }

    protected function copy(): array
    {
        return [
            'subject' => 'Redefina sua senha do Wishlisti',
            'preheader' => 'Use o link para criar uma nova senha.',
            'badge' => 'Redefinir senha',
            'intro' => 'Recebemos um pedido para redefinir a senha da sua conta no Wishlisti. Clique no botão abaixo para criar uma nova.',
            'buttonLabel' => 'Criar nova senha',
            'note' => 'O link vale por '.self::validity().'. Não pediu para trocar a senha? É só ignorar este e-mail: a sua senha continua a mesma.',
            'reason' => 'Você recebeu este e-mail porque pediram para redefinir a senha da sua conta em',
        ];
    }
}
