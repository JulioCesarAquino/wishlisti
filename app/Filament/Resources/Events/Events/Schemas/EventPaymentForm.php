<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Models\Events\EventPaymentSetting;
use Closure;
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
                    // Same order as Mercado Pago's credentials page (Public
                    // Key, then Access Token), so they're copied across
                    // field by field without mixing them up.
                    TextInput::make('mp_public_key')
                        ->label('Public Key')
                        ->helperText('O primeiro campo em "Credenciais de produção" no Mercado Pago.')
                        // Not a secret: shown back, but it's a hidden
                        // attribute too, so it has to be loaded by hand.
                        ->afterStateHydrated(fn (TextInput $component, ?EventPaymentSetting $record) => $component->state($record?->mp_public_key))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->rule(fn (?EventPaymentSetting $record) => self::credentialRule(publicKey: true, saved: $record?->mp_public_key))
                        ->hintAction(
                            Action::make('mpCredentialsHelp')
                                ->label('Como obter minhas credenciais')
                                ->icon(Heroicon::OutlinedQuestionMarkCircle)
                                ->url('https://www.mercadopago.com.br/developers/pt/docs/linx/additional-content/your-integrations/credentials#bookmark_obter_credenciais')
                                ->openUrlInNewTab(),
                        )
                        ->columnSpanFull(),
                    TextInput::make('mp_access_token')
                        ->label('Access Token')
                        ->helperText('O segundo campo, logo abaixo da Public Key. Fica guardado em segredo.')
                        ->password()
                        ->revealable()
                        // The credentials are hidden attributes, so the form
                        // opens with this field empty. Saved only when
                        // typed: left blank, the current token is kept.
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->placeholder(fn (?EventPaymentSetting $record): ?string => filled($record?->mp_access_token)
                            ? 'Já configurado — deixe em branco para manter'
                            : null)
                        ->rule(fn () => self::credentialRule(publicKey: false))
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

    /**
     * The two credentials look nothing alike — the Public Key is
     * APP_USR-<uuid>, the Access Token APP_USR-<digits>-<date>-<hex>-<digits>
     * — so one pasted in the other's field is caught before Mercado Pago
     * turns every payment down ("Unauthorized use of live credentials").
     *
     * Only what's typed is checked: a key already saved (shown back in the
     * form) never stops the rest of the page from being saved.
     */
    private static function credentialRule(bool $publicKey, ?string $saved = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($publicKey, $saved): void {
            $value = trim((string) $value);

            if ($value === '' || $value === $saved) {
                return;
            }

            if (! preg_match('/^(APP_USR|TEST)-/', $value)) {
                $fail('As credenciais do Mercado Pago começam com APP_USR- (ou TEST-, nas de teste). Confira se copiou o campo inteiro.');

                return;
            }

            $looksLikePublicKey = (bool) preg_match('/^(APP_USR|TEST)-[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
            $looksLikeAccessToken = (bool) preg_match('/^(APP_USR|TEST)-\d+-\d+-[0-9a-f]+-\d+$/i', $value);

            if ($publicKey && ! $looksLikePublicKey) {
                $fail($looksLikeAccessToken
                    ? 'Isso parece o Access Token. Aqui vai a Public Key: o primeiro campo no Mercado Pago, mais curto.'
                    : 'Isso não parece uma Public Key do Mercado Pago. Confira se copiou o primeiro campo inteiro.');
            }

            if (! $publicKey && $looksLikePublicKey) {
                $fail('Isso parece a Public Key. Aqui vai o Access Token: o segundo campo no Mercado Pago, mais longo.');
            }
        };
    }
}
