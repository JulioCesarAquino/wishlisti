<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Models\Events\EventRsvpSetting;
use App\Models\Guests\Guest;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;

class EventRsvpForm
{
    private const FIELD_LABELS = [
        'whatsapp' => 'Telefone / WhatsApp',
        'email' => 'E-mail',
        'cpf' => 'CPF',
        'age' => 'Idade',
    ];

    /**
     * How many people each invitation is for: on any event, free or
     * premium. A family gets more in the guest list, guest by guest.
     */
    public static function companionLimit(): Section
    {
        return Section::make('Acompanhantes por convidado')
            ->description('Quantas pessoas cada convidado pode levar ao confirmar presença.')
            ->icon('heroicon-o-users')
            ->components([
                TextInput::make('max_companions')
                    ->label('Cada convidado pode levar até')
                    ->suffix('acompanhantes')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(Guest::MAX_PARTY_SIZE - 1)
                    ->placeholder('Em branco: até '.(Guest::MAX_PARTY_SIZE - 1))
                    ->helperText('0: cada convite é só para quem foi convidado. Para quem vai levar a família toda, mude o limite da pessoa em Convidados → Editar. Quem já confirmou com mais gente continua confirmado.')
                    ->maxWidth('md'),
            ]);
    }

    /**
     * "Children under X don't pay": on any event, free or premium.
     */
    public static function children(): Section
    {
        return Section::make('Crianças')
            ->description('Para saber quantas crianças vão: o painel separa as crianças no total de pessoas.')
            ->icon('heroicon-o-face-smile')
            ->components([
                TextInput::make('child_age_limit')
                    ->label('Contar como criança quem tem menos de')
                    ->suffix('anos')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(18)
                    ->placeholder('Em branco: não separar crianças')
                    ->helperText('Com os dados de cada acompanhante e a idade pedidos a eles, o sistema conta pela idade de cada pessoa. Senão, o formulário pergunta "quantas dessas pessoas têm menos de X anos?".')
                    ->maxWidth('md'),
            ]);
    }

    /**
     * The form, field by field, and the companions: the guest list premium
     * feature.
     *
     * @return array<int, Component>
     */
    public static function settings(): array
    {
        return [
            Section::make('Dados pedidos no formulário')
                ->description('O nome é sempre pedido. Os mesmos dados valem para cada acompanhante; os de contato também identificam quem dá um presente.')
                ->icon('heroicon-o-clipboard-document-list')
                ->columns(2)
                ->components([
                    ...self::fieldModes('fields', EventRsvpSetting::DEFAULT_FIELDS),
                    Text::make('Sem telefone, e-mail ou CPF obrigatório, você não terá como falar com os convidados, e a mesma pessoa pode acabar confirmando duas vezes de outro aparelho.')
                        ->color('warning')
                        ->visible(fn (Get $get): bool => ! in_array(EventRsvpSetting::FIELD_REQUIRED, [$get('fields.whatsapp'), $get('fields.email'), $get('fields.cpf')], true))
                        ->columnSpanFull(),
                ]),
            Section::make('Acompanhantes')
                ->icon('heroicon-o-user-group')
                ->components([
                    Toggle::make('collect_companions')
                        ->label('Pedir os dados de cada acompanhante')
                        ->helperText('Em vez de só informar quantas pessoas vão, o convidado preenche os dados de cada uma. Se um acompanhante confirmar presença por conta própria, ele deixa de contar para quem o listou e vira uma confirmação individual.')
                        ->live()
                        ->default(false),
                    Grid::make(2)
                        ->visible(fn (Get $get): bool => (bool) $get('collect_companions'))
                        ->components([
                            Text::make('O que pedir de cada acompanhante (o nome é sempre pedido). Para crianças, por exemplo: só nome e idade.')
                                ->columnSpanFull(),
                            ...self::fieldModes('companion_fields', EventRsvpSetting::DEFAULT_COMPANION_FIELDS),
                        ]),
                ]),
            Section::make('Quem pode confirmar')
                ->icon('heroicon-o-lock-closed')
                ->components([
                    Toggle::make('invite_only')
                        ->label('Só pelo link do convite')
                        ->helperText('Cada convidado da lista tem um link próprio, que vai no "Enviar convite pelo WhatsApp" (ou em Convidados → Link do convite). Ligado, só quem abrir o próprio link consegue confirmar; quem chega pelo link geral do evento vê a página, mas não a confirmação.')
                        ->default(false),
                ]),
        ];
    }

    /**
     * One "Não pedir / Opcional / Obrigatório" choice per field.
     *
     * @param  array<string, string>  $defaults
     * @return array<int, ToggleButtons>
     */
    private static function fieldModes(string $statePath, array $defaults): array
    {
        return array_map(fn (string $field) => ToggleButtons::make("{$statePath}.{$field}")
            ->label(self::FIELD_LABELS[$field])
            ->options([
                EventRsvpSetting::FIELD_HIDDEN => 'Não pedir',
                EventRsvpSetting::FIELD_OPTIONAL => 'Opcional',
                EventRsvpSetting::FIELD_REQUIRED => 'Obrigatório',
            ])
            ->colors([
                EventRsvpSetting::FIELD_HIDDEN => 'gray',
                EventRsvpSetting::FIELD_OPTIONAL => 'info',
                EventRsvpSetting::FIELD_REQUIRED => 'success',
            ])
            ->inline()
            ->live()
            ->required()
            ->default($defaults[$field])
            // Events from before each field had a mode.
            ->afterStateHydrated(function (ToggleButtons $component, ?string $state) use ($defaults, $field): void {
                if (blank($state)) {
                    $component->state($defaults[$field]);
                }
            }), EventRsvpSetting::FIELDS);
    }
}
