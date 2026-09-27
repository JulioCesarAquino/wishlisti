<?php

namespace App\Filament\Resources\Events\Events\Schemas;

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
                        ->columnSpanFull(),
                ]),
            Section::make('Entrega pessoal')
                ->description('Além de pagar online, o convidado pode reservar o presente e entregá-lo pessoalmente.')
                ->relationship('giftSettings')
                ->columnSpanFull()
                ->components([
                    Toggle::make('allow_in_person')
                        ->label('Permitir que convidados escolham entregar pessoalmente')
                        ->helperText('Desligado, a lista só aceita presentes pagos online.')
                        ->default(true),
                ]),
        ];
    }
}
