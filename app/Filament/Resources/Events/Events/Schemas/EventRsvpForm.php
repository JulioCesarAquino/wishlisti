<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;

class EventRsvpForm
{
    public static function settings(): Group
    {
        return Group::make()
            ->relationship('rsvpSettings')
            ->columnSpanFull()
            ->components([
                CheckboxList::make('required_fields')
                    ->label('Dados obrigatórios')
                    ->helperText('O nome é sempre obrigatório. Os dados marcados serão exigidos do convidado e de cada acompanhante, e também servem para reconhecer quem já foi listado por outra pessoa.')
                    ->options([
                        'whatsapp' => 'Telefone / WhatsApp',
                        'email' => 'E-mail',
                        'cpf' => 'CPF',
                    ])
                    ->default(['whatsapp'])
                    // Events created before this setting existed have it
                    // empty; start them from the free form's rule.
                    ->afterStateHydrated(function (CheckboxList $component, ?array $state): void {
                        if (blank($state)) {
                            $component->state(['whatsapp']);
                        }
                    })
                    ->required()
                    ->minItems(1)
                    ->columns(3),
                Toggle::make('collect_companions')
                    ->label('Pedir os dados de cada acompanhante')
                    ->helperText('Em vez de só informar quantas pessoas vão, o convidado preenche os dados de cada uma. Se um acompanhante confirmar presença por conta própria, ele deixa de contar para quem o listou e vira uma confirmação individual.')
                    ->default(false),
            ]);
    }
}
