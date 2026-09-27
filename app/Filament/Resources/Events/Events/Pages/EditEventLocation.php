<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventLocationForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEventLocation extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Localização';

    protected static ?string $navigationLabel = 'Localização';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    public function form(Schema $schema): Schema
    {
        return EventLocationForm::configure($schema);
    }
}
