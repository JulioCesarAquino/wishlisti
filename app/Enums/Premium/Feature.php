<?php

namespace App\Enums\Premium;

use App\Models\Events\Event;
use App\Models\User;
use Filament\Support\Icons\Heroicon;

/**
 * Premium features. Each one is unlocked for a single event or for a host
 * (see appliesTo()) by a FeatureGrant — today given by the admin, later
 * also bought by the host.
 */
enum Feature: string
{
    case GiftGivers = 'gift_givers';
    case GuestList = 'guest_list';
    case Contacts = 'contacts';

    public function label(): string
    {
        return match ($this) {
            self::GiftGivers => 'Ver quem deu cada presente',
            self::GuestList => 'Lista nominal de convidados',
            self::Contacts => 'Agenda de contatos',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GiftGivers => 'Sem isso, o anfitrião só vê o total arrecadado na aba de Pedidos, sem os nomes dos convidados.',
            self::GuestList => 'Libera para o anfitrião escolher os dados obrigatórios na confirmação de presença e pedir os dados de cada acompanhante.',
            self::Contacts => 'Agenda própria do anfitrião para guardar os convidados e reaproveitá-los nos próximos eventos.',
        };
    }

    /**
     * Sales copy for the premium page: a short promise...
     */
    public function headline(): string
    {
        return match ($this) {
            self::GiftGivers => 'Saiba quem te presenteou e agradeça cada pessoa',
            self::GuestList => 'Lista de convidados com nome e contato de cada pessoa',
            self::Contacts => 'Sua agenda de convidados pronta para o próximo evento',
        };
    }

    /**
     * ...and what the host concretely gets.
     *
     * @return array<int, string>
     */
    public function benefits(): array
    {
        return match ($this) {
            self::GiftGivers => [
                'Veja o nome e o WhatsApp de quem deu cada presente',
                'Leia a mensagem que cada convidado deixou junto com o presente',
                'Mande um agradecimento personalizado sem precisar adivinhar',
            ],
            self::GuestList => [
                'Escolha os dados obrigatórios: telefone, e-mail e/ou CPF',
                'Receba o nome e o contato de cada acompanhante, não só a quantidade',
                'Ideal para lista de portaria, buffet e controle de entrada',
            ],
            self::Contacts => [
                'Guarde os convidados de todos os seus eventos em um só lugar',
                'Importe a lista de um evento com um clique',
                'Convide as mesmas pessoas no próximo evento sem digitar tudo de novo',
            ],
        };
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::GiftGivers => Heroicon::OutlinedGift,
            self::GuestList => Heroicon::OutlinedClipboardDocumentList,
            self::Contacts => Heroicon::OutlinedBookOpen,
        };
    }

    /**
     * @return class-string<Event|User>
     */
    public function appliesTo(): string
    {
        return match ($this) {
            self::GiftGivers, self::GuestList => Event::class,
            self::Contacts => User::class,
        };
    }

    /**
     * @param  class-string<Event|User>  $model
     * @return array<int, self>
     */
    public static function for(string $model): array
    {
        return array_values(array_filter(self::cases(), fn (self $feature) => $feature->appliesTo() === $model));
    }
}
