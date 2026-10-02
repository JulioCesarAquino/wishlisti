<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Enums\Events\PageSection;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventSectionsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('sections')
                    ->label('Abas')
                    ->relationship('sections')
                    ->orderColumn('position')
                    ->reorderable()
                    ->addable(false)
                    ->deletable(false)
                    ->itemLabel(fn (array $state): ?string => self::type($state['type'] ?? null)?->label())
                    ->columnSpanFull()
                    ->helperText('Escolha quais abas aparecem no menu da página do evento e arraste para mudar a ordem. Uma aba desligada também some para quem já tinha o link dela.')
                    ->schema([
                        Hidden::make('type')->dehydrated(false),
                        Toggle::make('is_active')
                            ->label('Mostrar na página')
                            ->disabled(fn (Get $get): bool => ! (self::type($get('type'))?->canBeDisabled() ?? true))
                            ->helperText(fn (Get $get): ?string => self::type($get('type')) === PageSection::Home
                                ? 'A página sempre abre no Início, então ele não pode ser desligado.'
                                : self::type($get('type'))?->requirement()),
                    ]),
            ]);
    }

    private static function type(mixed $state): ?PageSection
    {
        return $state instanceof PageSection ? $state : PageSection::tryFrom((string) $state);
    }
}
