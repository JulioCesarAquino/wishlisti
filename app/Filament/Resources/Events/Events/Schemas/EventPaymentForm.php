<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Models\Events\EventPaymentSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EventPaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    /**
     * @return array<int, Section>
     */
    public static function components(): array
    {
        return [
            Section::make('Mercado Pago')
                ->description('Necessário para o evento poder receber pagamentos via Pix, cartão ou boleto.')
                ->relationship('paymentSettings')
                ->columnSpanFull()
                ->components([
                    TextInput::make('mp_access_token')
                        ->label('Access Token')
                        ->password()
                        ->revealable()
                        // The credentials are hidden attributes, so the form
                        // opens with this field empty. Saved only when
                        // typed: left blank, the current token is kept.
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->placeholder(fn (?EventPaymentSetting $record): ?string => filled($record?->mp_access_token)
                            ? 'Já configurado — deixe em branco para manter'
                            : null)
                        ->hintAction(
                            Action::make('mpCredentialsHelp')
                                ->label('Como obter minhas credenciais')
                                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                                ->url('https://www.mercadopago.com.br/developers/pt/docs/linx/additional-content/your-integrations/credentials#bookmark_obter_credenciais')
                                ->openUrlInNewTab(),
                        )
                        ->columnSpanFull(),
                    TextInput::make('mp_public_key')
                        ->label('Public Key')
                        // Not a secret: shown back, but it's a hidden
                        // attribute too, so it has to be loaded by hand.
                        ->afterStateHydrated(fn (TextInput $component, ?EventPaymentSetting $record) => $component->state($record?->mp_public_key))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->columnSpanFull(),
                ]),
            Section::make('Formas de presentear')
                ->description('Além de pagar online um item da lista, o convidado pode reservá-lo para entregar pessoalmente ou contribuir com qualquer valor.')
                ->relationship('giftSettings')
                ->columnSpanFull()
                ->components([
                    Toggle::make('allow_in_person')
                        ->label('Permitir que convidados escolham entregar pessoalmente')
                        ->helperText('Desligado, a lista só aceita presentes pagos online.')
                        ->default(true),
                    Toggle::make('allow_free_amount')
                        ->label('Aceitar contribuição de valor livre')
                        ->helperText('O convidado escolhe quanto quer dar, sem escolher um item, e paga online. Aparece em qualquer modo de exibição dos presentes, inclusive "Sem presentes".')
                        ->default(false),
                ]),
        ];
    }
}
