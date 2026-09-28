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
    /** Formerly "gift_givers" (seeing who gave each gift), which it absorbed. */
    case Payments = 'payments';
    case GuestList = 'guest_list';
    case FullGiftList = 'full_gift_list';
    case Contacts = 'contacts';

    public function label(): string
    {
        return match ($this) {
            self::Payments => 'Receber presentes online',
            self::GuestList => 'Lista nominal de convidados',
            self::FullGiftList => 'Lista de presentes completa',
            self::Contacts => 'Agenda de contatos',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Payments => 'Convidados presenteiam com Pix, cartão ou boleto, e o dinheiro cai direto na conta Mercado Pago do anfitrião.',
            self::GuestList => 'Libera para o anfitrião escolher os dados obrigatórios na confirmação de presença e pedir os dados de cada acompanhante.',
            self::FullGiftList => 'Presentes personalizados (nome, foto e descrição livres), cotas com várias unidades e quantos itens quiser. No gratuito, a lista usa só itens do catálogo, uma unidade cada, até '.config('premium.free_gift_limit').' presentes.',
            self::Contacts => 'Agenda própria do anfitrião para guardar os convidados e reaproveitá-los nos próximos eventos.',
        };
    }

    /**
     * Sales copy for the premium page: a short promise...
     */
    public function headline(): string
    {
        return match ($this) {
            self::Payments => 'Receba seus presentes em dinheiro, por Pix, cartão ou boleto',
            self::GuestList => 'Lista de convidados com nome e contato de cada pessoa',
            self::FullGiftList => 'Monte a lista de presentes do seu jeito, sem limites',
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
            self::Payments => [
                'O dinheiro cai direto na sua conta Mercado Pago',
                'Veja quem deu cada presente e leia a mensagem que deixou',
                'Quem preferir pode presentear anonimamente, e você recebe do mesmo jeito',
            ],
            self::GuestList => [
                'Escolha os dados obrigatórios: telefone, e-mail e/ou CPF',
                'Receba o nome e o contato de cada acompanhante, não só a quantidade',
                'Ideal para lista de portaria, buffet e controle de entrada',
            ],
            self::FullGiftList => [
                'Crie presentes personalizados, com nome, foto e descrição próprios',
                'Ofereça cotas com várias unidades, como "noites da lua de mel"',
                'Quantos presentes quiser na lista, sem o limite do plano gratuito',
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
            self::Payments => Heroicon::OutlinedBanknotes,
            self::GuestList => Heroicon::OutlinedClipboardDocumentList,
            self::FullGiftList => Heroicon::OutlinedSquaresPlus,
            self::Contacts => Heroicon::OutlinedBookOpen,
        };
    }

    /**
     * @return class-string<Event|User>
     */
    public function appliesTo(): string
    {
        return match ($this) {
            self::Payments, self::GuestList, self::FullGiftList => Event::class,
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
