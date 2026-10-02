<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Enums\Events\PageSection;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EventSectionsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Abas do menu')
                    ->description('Estas são as abas do menu da página do evento. Arraste pelas setas para mudar a ordem e use a chave para mostrar ou esconder cada uma. A página abre na primeira aba visível: para abrir direto na localização, por exemplo, coloque-a no topo. Uma aba escondida também deixa de abrir para quem já tinha o link dela.')
                    ->columnSpanFull()
                    ->components([self::repeater()]),
            ]);
    }

    private static function repeater(): Repeater
    {
        return Repeater::make('sections')
            ->hiddenLabel()
            ->relationship('sections')
            ->orderColumn('position')
            ->reorderable()
            ->addable(false)
            ->deletable(false)
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                if (! collect(is_array($value) ? $value : [])->contains(fn (mixed $item) => (bool) data_get($item, 'is_active'))) {
                    $fail('Deixe ao menos uma aba visível.');
                }
            })
            // The raw state: the snapshot leaves out "type", which isn't
            // saved.
            ->itemLabel(fn (Schema $item): ?string => self::type(data_get($item->getRawState(), 'type'))?->label())
            ->schema([
                Hidden::make('type')->dehydrated(false),
                Toggle::make('is_active')
                    ->label('Mostrar no menu')
                    ->helperText(fn (Get $get): ?string => self::type($get('type'))?->requirement()),
            ]);
    }

    private static function type(mixed $state): ?PageSection
    {
        return $state instanceof PageSection ? $state : PageSection::tryFrom((string) $state);
    }
}
