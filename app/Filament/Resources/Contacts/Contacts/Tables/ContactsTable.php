<?php

namespace App\Filament\Resources\Contacts\Contacts\Tables;

use App\Models\Events\Event;
use App\Services\Guests\GuestImportService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Anfitrião')
                    ->visible(fn (): bool => (bool) auth()->user()?->isAdmin()),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Adicionado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('addToEvent')
                        ->label('Adicionar como convidados de um evento')
                        ->icon(Heroicon::OutlinedUserPlus)
                        ->schema([
                            Select::make('event_id')
                                ->label('Evento')
                                ->helperText('Eles entram na lista de convidados como "Sem resposta" e são reconhecidos quando confirmarem presença.')
                                ->options(fn () => Event::query()
                                    ->when(! auth()->user()?->isAdmin(), fn ($query) => $query->where('user_id', auth()->id()))
                                    ->pluck('title', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data, GuestImportService $service): void {
                            $event = Event::query()
                                ->when(! auth()->user()?->isAdmin(), fn ($query) => $query->where('user_id', auth()->id()))
                                ->whereKey($data['event_id'])
                                ->firstOrFail();

                            $created = $service->execute($event, $records);

                            Notification::make()
                                ->success()
                                ->title($created === 1 ? '1 convidado adicionado ao evento.' : "{$created} convidados adicionados ao evento.")
                                ->body('Quem já estava na lista de convidados não foi duplicado.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
