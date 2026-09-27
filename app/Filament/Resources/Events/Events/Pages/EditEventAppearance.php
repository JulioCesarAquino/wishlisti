<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventAppearanceForm;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEventAppearance extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Página pública';

    protected static ?string $navigationLabel = 'Página pública';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    public function form(Schema $schema): Schema
    {
        return EventAppearanceForm::configure($schema);
    }
}
