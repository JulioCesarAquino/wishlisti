<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventSectionsForm;
use App\Models\Events\Event;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EditEventSections extends EditRecord
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Abas da página';

    protected static ?string $navigationLabel = 'Abas da página';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    public function form(Schema $schema): Schema
    {
        return EventSectionsForm::configure($schema);
    }

    /**
     * Every tab gets a row first, so all of them are listed — including on
     * events nobody configured yet.
     */
    protected function fillForm(): void
    {
        /** @var Event $event */
        $event = $this->getRecord();
        $event->ensureSections();

        parent::fillForm();
    }
}
