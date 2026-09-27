<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventDetailsForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEvent extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Detalhes';

    protected static ?string $navigationLabel = 'Detalhes';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public function form(Schema $schema): Schema
    {
        return EventDetailsForm::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->viewPublicPageAction(),
            DeleteAction::make(),
        ];
    }
}
