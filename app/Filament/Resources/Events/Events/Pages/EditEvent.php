<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Resources\Events\Events\Schemas\EventDetailsForm;
use App\Models\Events\Event;
use App\Services\Events\EventDestroyService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
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
            RestoreAction::make(),
            ActionGroup::make([
                Action::make('archive')
                    ->label('Arquivar')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->requiresConfirmation()
                    ->modalDescription('O evento sai da lista e a página pública deixa de ficar acessível para os convidados. Pedidos, convidados e presentes continuam guardados, e você pode desarquivar quando quiser.')
                    ->visible(fn (Event $record): bool => ! $record->isArchived() && ! $record->trashed())
                    ->action(function (Event $record): void {
                        $record->update(['archived_at' => now()]);

                        Notification::make()->success()->title('Evento arquivado')->send();
                    }),
                Action::make('unarchive')
                    ->label('Desarquivar')
                    ->icon(Heroicon::OutlinedArchiveBoxXMark)
                    ->visible(fn (Event $record): bool => $record->isArchived())
                    ->action(function (Event $record): void {
                        $record->update(['archived_at' => null]);

                        Notification::make()->success()->title('Evento desarquivado')->send();
                    }),
                DeleteAction::make()
                    ->label('Mover para a lixeira')
                    ->modalDescription('O evento fica 30 dias na lixeira e pode ser restaurado nesse período. Depois é excluído definitivamente.')
                    ->before(function (DeleteAction $action, Event $record): void {
                        if ($record->hasPaidOrders()) {
                            Notification::make()
                                ->danger()
                                ->title('Este evento não pode ser excluído')
                                ->body('Ele tem presentes pagos, que fazem parte do histórico financeiro. Use "Arquivar" para tirá-lo da lista e da página pública.')
                                ->persistent()
                                ->send();

                            $action->cancel();
                        }
                    }),
                ForceDeleteAction::make()
                    ->label('Excluir definitivamente')
                    ->modalDescription('Exclui o evento com TUDO o que está ligado a ele — convidados, presentes e pedidos, inclusive os pagos. O histórico financeiro do evento se perde e não há como desfazer.')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin())
                    ->using(fn (Event $record, EventDestroyService $service) => $service->execute($record)),
            ]),
        ];
    }
}
