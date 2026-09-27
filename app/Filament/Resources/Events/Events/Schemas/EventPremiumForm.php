<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use App\Enums\Premium\Feature;
use App\Models\Events\Event;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Schema;

class EventPremiumForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                CheckboxList::make('premium_features')
                    ->label('Recursos premium deste evento')
                    ->options(collect(Feature::for(Event::class))->mapWithKeys(fn (Feature $feature) => [$feature->value => $feature->label()]))
                    ->descriptions(collect(Feature::for(Event::class))->mapWithKeys(fn (Feature $feature) => [$feature->value => $feature->description()]))
                    ->afterStateHydrated(fn (CheckboxList $component, ?Event $record) => $component->state(
                        array_map(fn (Feature $feature) => $feature->value, $record?->activeFeatures() ?? []),
                    ))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(fn (Event $record, ?array $state) => $record->syncFeatures($state ?? []))
                    ->columnSpanFull(),
            ]);
    }
}
