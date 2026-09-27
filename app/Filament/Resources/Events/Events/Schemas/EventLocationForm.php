<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EventLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::components());
    }

    /**
     * Also used by the create form, since the address is mandatory.
     *
     * @return array<int, Field>
     */
    public static function components(): array
    {
        return [
            Textarea::make('address')
                ->label('Endereço')
                ->required()
                ->rows(2)
                ->columnSpanFull()
                ->helperText('Rua, número, bairro e cidade — como deve aparecer para os convidados. Exibido na página pública com um mapa.')
                ->hintAction(
                    Action::make('useCurrentLocation')
                        ->label('Usar minha localização atual')
                        ->icon(Heroicon::OutlinedMapPin)
                        ->alpineClickHandler(<<<'JS'
                            if (! navigator.geolocation) {
                                alert('Seu navegador não suporta geolocalização. Preencha o endereço e as coordenadas manualmente.');
                                return;
                            }
                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    $wire.set('data.latitude', position.coords.latitude);
                                    $wire.set('data.longitude', position.coords.longitude);
                                    alert('Localização obtida automaticamente. Ela pode não ser exata — confira no mapa e preencha o endereço abaixo.');
                                },
                                () => alert('Não foi possível obter sua localização automaticamente. Preencha o endereço e as coordenadas manualmente.'),
                            );
                            JS),
                ),
            TextInput::make('latitude')
                ->label('Latitude')
                ->numeric()
                ->step('any')
                ->minValue(-90)
                ->maxValue(90)
                ->requiredWith('longitude'),
            TextInput::make('longitude')
                ->label('Longitude')
                ->numeric()
                ->step('any')
                ->minValue(-180)
                ->maxValue(180)
                ->requiredWith('latitude'),
        ];
    }
}
