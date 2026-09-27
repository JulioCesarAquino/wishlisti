<?php

namespace App\Filament\Resources\Contacts\Contacts\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Contacts\Contacts\ContactResource;
use App\Filament\Support\PremiumLock;
use App\Models\Events\Event;
use App\Services\Contacts\ContactImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected static ?string $title = 'Agenda de contatos';

    public function content(Schema $schema): Schema
    {
        if (! ContactResource::hasAccess()) {
            return $schema->components([PremiumLock::callout(Feature::Contacts)]);
        }

        return parent::content($schema);
    }

    protected function getHeaderActions(): array
    {
        if (! ContactResource::hasAccess()) {
            return [];
        }

        return [
            Action::make('importFromEvent')
                ->label('Importar convidados de um evento')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->schema([
                    Select::make('event_id')
                        ->label('Evento')
                        ->options(fn () => Event::query()
                            ->when(! auth()->user()?->isAdmin(), fn ($query) => $query->where('user_id', auth()->id()))
                            ->pluck('title', 'id'))
                        ->required(),
                ])
                ->action(function (array $data, ContactImportService $service): void {
                    $event = Event::query()
                        ->when(! auth()->user()?->isAdmin(), fn ($query) => $query->where('user_id', auth()->id()))
                        ->whereKey($data['event_id'])
                        ->firstOrFail();

                    $created = $service->execute($event);

                    Notification::make()
                        ->success()
                        ->title($created === 1 ? '1 contato adicionado à agenda.' : "{$created} contatos adicionados à agenda.")
                        ->body('Quem já estava na agenda não foi duplicado.')
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
