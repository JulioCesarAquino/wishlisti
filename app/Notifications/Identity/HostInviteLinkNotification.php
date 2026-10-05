<?php

namespace App\Notifications\Identity;

/**
 * The e-mail a host gets when the admin approves their invite request (or
 * makes them a new link): the link to set their password and come in.
 */
class HostInviteLinkNotification extends AccessLinkNotification
{
    public function __construct(
        public string $link,
        public bool $firstTime = true,
    ) {}

    protected function link(): string
    {
        return $this->link;
    }

    protected function copy(): array
    {
        return [
            'subject' => $this->firstTime ? 'Seu acesso ao Wishlisti foi aprovado' : 'Seu novo link de acesso ao Wishlisti',
            'preheader' => $this->firstTime ? 'Crie sua senha e comece a montar o seu evento.' : 'Seu novo link para criar a senha.',
            'badge' => $this->firstTime ? 'Acesso aprovado' : 'Novo link de acesso',
            'intro' => $this->firstTime
                ? 'Seu pedido de acesso ao Wishlisti foi aprovado. Agora é só criar a sua senha para entrar e montar a página do seu evento.'
                : 'Aqui está um novo link para você criar a sua senha e entrar no Wishlisti.',
            'buttonLabel' => 'Criar minha senha',
            'note' => 'O link vale por '.self::validity().'. Se ele expirar, é só responder este e-mail que enviamos outro.',
            'reason' => 'Você recebeu este e-mail porque pediu acesso em',
        ];
    }
}
