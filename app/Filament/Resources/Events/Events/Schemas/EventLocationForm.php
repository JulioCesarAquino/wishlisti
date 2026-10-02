<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Models\Events\EventLocation;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;
use Illuminate\Support\Str;

class EventLocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components(self::components());
    }

    /**
     * Also used by the create form, since every event needs at least one
     * location.
     *
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            Repeater::make('locations')
                ->label('Localizações')
                ->relationship('locations')
                ->orderColumn('position')
                ->reorderable()
                ->minItems(1)
                ->defaultItems(1)
                ->addActionLabel('Adicionar localização')
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->collapsible()
                ->columns(2)
                ->columnSpanFull()
                ->helperText('Cerimônia e festa em lugares diferentes? Cadastre cada uma: na página do evento, elas aparecem na aba Localização, e cada uma tem um link próprio para compartilhar.')
                ->schema(self::locationComponents()),
        ];
    }

    /**
     * @return array<int, Field>
     */
    private static function locationComponents(): array
    {
        return [
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(100)
                ->default('Local do evento')
                ->placeholder('Cerimônia, Festa…'),
            TextInput::make('slug')
                ->label('Endereço do link')
                ->maxLength(120)
                ->alphaDash()
                ->distinct()
                ->placeholder('Gerado a partir do nome')
                ->helperText(fn (?EventLocation $record): string => $record?->exists
                    ? "Link: {$record->publicUrl()}"
                    : 'Fica no fim do link desta localização. Em branco, é gerado a partir do nome.'),
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
                        // is only replaced if they agree. Each location of
                        // the repeater fills its own fields.
                        ->alpineClickHandler(fn (Textarea $component): string => 'const base = '.Js::from(Str::beforeLast((string) $component->getStatePath(), '.')).";\n".<<<'JS'
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

                                    $wire.set(`${base}.latitude`, latitude);
                                    $wire.set(`${base}.longitude`, longitude);

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

                                    const current = ($wire.get(`${base}.address`) || '').trim();

                                    if (! current || confirm(`Usar este endereço encontrado?\n\n${found}\n\n(substitui o que está no campo)`)) {
                                        $wire.set(`${base}.address`, found);
                                    }

                                    alert('Localização e endereço obtidos automaticamente. Eles podem não ser exatos: confira o número e o complemento, e veja o mapa na página pública.');
                                },
                                () => alert('Não foi possível obter sua localização automaticamente. Preencha o endereço e as coordenadas manualmente.'),
                            );
                            JS),
                ),
            TextInput::make('maps_url')
                ->label('Link do mapa (opcional)')
                ->url()
                ->maxLength(2048)
                ->columnSpanFull()
                ->placeholder('https://maps.app.goo.gl/…')
                ->helperText('Link do Google Maps, Waze… para o botão "Abrir no mapa". Em branco, o botão busca pelo endereço.'),
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
