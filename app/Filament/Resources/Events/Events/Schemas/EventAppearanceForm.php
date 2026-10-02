<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Models\Events\EventAppearance;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class EventAppearanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fotos')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        FileUpload::make('cover_image')
                            ->label('Imagem de capa')
                            ->image()
                            ->disk('public')
                            ->imageEditor()
                            ->imagePreviewHeight('160')
                            ->directory('events/covers'),
                        FileUpload::make('share_image')
                            ->label('Imagem do link compartilhado')
                            ->helperText('A foto que aparece quando o link do evento é enviado no WhatsApp, Instagram ou Facebook. Fica no formato retangular desses cartões (1200×630). Sem ela, usamos a capa.')
                            ->image()
                            ->disk('public')
                            ->imageEditor()
                            ->imageCropAspectRatio('40:21')
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('630')
                            ->imagePreviewHeight('160')
                            ->directory('events/share'),
                        FileUpload::make('gallery')
                            ->label('Galeria de fotos')
                            ->image()
                            ->disk('public')
                            ->multiple()
                            ->reorderable()
                            ->imagePreviewHeight('120')
                            ->directory('events/gallery'),
                    ]),
                Section::make('Estilo')
                    ->relationship('appearance')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Slider::make('cover_effect_intensity')
                            ->label('Intensidade do efeito de desfoque na capa')
                            ->helperText('100 é o efeito completo (foto embaçada até o convidado passar o mouse ou tocar). 0 exibe a foto normalmente, sem nenhum efeito.')
                            ->range(minValue: 0, maxValue: 100)
                            ->step(5)
                            ->tooltips()
                            ->default(100)
                            ->columnSpanFull(),
                        Select::make('font_family')
                            ->label('Fonte')
                            ->options([
                                'default' => 'Moderna',
                                'script' => 'Manuscrita',
                                'serif' => 'Elegante',
                                'classic' => 'Clássica',
                            ])
                            ->default('default')
                            ->native(false)
                            ->columnSpanFull(),
                        ColorPicker::make('primary_color')
                            ->label('Cor primária')
                            ->helperText('Tom de fundo geral da página.'),
                        ColorPicker::make('secondary_color')
                            ->label('Cor secundária')
                            ->helperText('Usada nos detalhes decorativos.'),
                        ColorPicker::make('button_color')
                            ->label('Cor dos botões')
                            ->placeholder(EventAppearance::DEFAULT_BUTTON_COLOR)
                            ->helperText('Botões de presentear, pagar, "Como chegar" e a aba selecionada no menu. Prefira uma cor viva: o texto fica branco ou escuro sozinho, conforme a cor. Em branco, usamos o verde.')
                            ->live()
                            // Keeps the ready-made choice below in step.
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('button_color_preset', $state)),
                        ToggleButtons::make('button_color_preset')
                            ->label('Cores prontas para os botões')
                            ->options(EventAppearance::BUTTON_COLOR_PRESETS)
                            ->inline()
                            ->dehydrated(false)
                            ->afterStateHydrated(fn (ToggleButtons $component, Get $get) => $component->state($get('button_color')))
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('button_color', $state))
                            ->live(),
                        ColorPicker::make('font_color_primary')
                            ->label('Cor da fonte principal')
                            ->helperText('Usada no nome do casal e títulos.'),
                        ColorPicker::make('font_color_secondary')
                            ->label('Cor da fonte secundária')
                            ->helperText('Usada nos textos e menu.'),
                    ]),
            ]);
    }
}
