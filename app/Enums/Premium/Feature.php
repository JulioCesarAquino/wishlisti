<?php

namespace App\Enums\Premium;

use App\Models\Events\Event;
use App\Models\User;

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
