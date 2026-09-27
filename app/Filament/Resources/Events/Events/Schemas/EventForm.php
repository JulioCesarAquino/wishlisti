<?php

namespace App\Filament\Resources\Events\Events\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The short form used to create an event. Everything else is filled in
 * afterwards, through the event's sub-navigation pages.
 */
class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...EventDetailsForm::basicComponents(),
                Section::make('Localização')
                    ->description('Exibida na página pública com um mapa. Vale para casamento, aniversário ou qualquer outro evento.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components(EventLocationForm::components()),
            ]);
    }
}
