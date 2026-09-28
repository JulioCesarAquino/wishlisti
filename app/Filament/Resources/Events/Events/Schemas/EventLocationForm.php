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
                        // Fills the coordinates and — through OpenStreetMap's
                        // reverse geocoding (Nominatim: free, no API key) —
                        // the address too. An address the host already typed
                        // is only replaced if they agree.
                        ->alpineClickHandler(<<<'JS'
                            if (! navigator.geolocation) {
                                alert('Seu navegador não suporta geolocalização. Preencha o endereço e as coordenadas manualmente.');
                                return;
                            }

                            const describe = (address) => {
                                const street = [address.road, address.house_number].filter(Boolean).join(', ');
                                const district = address.suburb || address.neighbourhood || address.quarter || address.residential || address.city_district;
                                const city = address.city || address.town || address.village || address.municipality;
                                const state = (address['ISO3166-2-lvl4'] || '').replace(/^BR-/, '') || address.state;

                                return [[street, district].filter(Boolean).join(' - '), [city, state].filter(Boolean).join(' - ')]
                                    .filter(Boolean)
                                    .join(', ');
                            };

                            navigator.geolocation.getCurrentPosition(
                                async (position) => {
                                    const { latitude, longitude } = position.coords;

                                    $wire.set('data.latitude', latitude);
                                    $wire.set('data.longitude', longitude);

                                    let found = null;

                                    try {
                                        const response = await fetch(
                                            `https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&accept-language=pt-BR&lat=${latitude}&lon=${longitude}`,
                                            { headers: { Accept: 'application/json' } },
                                        );
                                        const place = response.ok ? await response.json() : null;
                                        found = place?.address ? describe(place.address) : null;
                                    } catch (error) {
                                        found = null;
                                    }

                                    if (! found) {
                                        alert('Localização obtida, mas não foi possível descobrir o endereço automaticamente. Preencha o endereço e confira no mapa: a localização pode não ser exata.');
                                        return;
                                    }

                                    const current = ($wire.get('data.address') || '').trim();

                                    if (! current || confirm(`Usar este endereço encontrado?\n\n${found}\n\n(substitui o que está no campo)`)) {
                                        $wire.set('data.address', found);
                                    }

                                    alert('Localização e endereço obtidos automaticamente. Eles podem não ser exatos: confira o número e o complemento, e veja o mapa na página pública.');
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
