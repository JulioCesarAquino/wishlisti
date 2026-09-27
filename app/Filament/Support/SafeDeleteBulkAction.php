<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Bulk delete (to the trash, by default) that skips records tied to the financial history
 * (instead of failing the whole batch) and says how many were kept.
 */
class SafeDeleteBulkAction
{
    /**
     * @param  Closure  $isProtected  Receives a record; true keeps it out of the trash.
     */
    public static function make(
        Closure $isProtected,
        string $protectedMessage,
        string $label = 'Mover para a lixeira',
        string $description = 'Os itens ficam 30 dias na lixeira e podem ser restaurados nesse período. Depois são excluídos definitivamente.',
        string $doneLabel = 'movido(s) para a lixeira',
    ): BulkAction {
        return BulkAction::make('delete')
            ->label($label)
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalDescription($description)
            ->action(function (Collection $records) use ($isProtected, $protectedMessage, $doneLabel): void {
                [$protected, $deletable] = $records->partition(fn (Model $record): bool => (bool) $isProtected($record));

                $deletable->each->delete();

                if ($protected->isNotEmpty()) {
                    Notification::make()
                        ->warning()
                        ->title("{$deletable->count()} {$doneLabel}, {$protected->count()} mantido(s)")
                        ->body($protectedMessage)
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("{$deletable->count()} {$doneLabel}")
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
