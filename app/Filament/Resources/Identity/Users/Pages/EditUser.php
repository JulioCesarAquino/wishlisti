<?php

namespace App\Filament\Resources\Identity\Users\Pages;

use App\Filament\Resources\Identity\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected bool $isAdmin = false;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Exclui o anfitrião e todos os eventos dele. Não pode ser desfeito.')
                ->before(function (DeleteAction $action, User $record): void {
                    if ($record->hasOrders()) {
                        Notification::make()
                            ->danger()
                            ->title('Este anfitrião não pode ser excluído')
                            ->body('Os eventos dele têm pedidos, que fazem parte do histórico financeiro. Arquive os eventos em vez de excluir o anfitrião.')
                            ->persistent()
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }

    /**
     * `is_admin` isn't mass-assignable (it's a privilege, not a regular
     * profile field), so it's pulled out here and applied afterSave() via
     * forceFill() instead of going through the guarded update().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->isAdmin = (bool) ($data['is_admin'] ?? false);

        unset($data['is_admin']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->getRecord()->forceFill(['is_admin' => $this->isAdmin])->save();
    }
}
