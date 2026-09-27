<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Filament\Support\SafeDeleteBulkAction;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Services\Guests\GuestAnonymizeService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ManageEventGuests extends ManageRelatedRecords
{
    use HasEventHeaderActions;

    protected static string $resource = EventResource::class;

    protected static string $relationship = 'guests';

    protected static ?string $title = 'Convidados';

    protected static ?string $navigationLabel = 'Convidados';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    private function canViewGiftCount(): bool
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();

        return (bool) auth()->user()?->isAdmin() || $event->hasFeature(Feature::GiftGivers);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class])->with('companionOf')->withCount([
                'orders as paid_orders_count' => fn (Builder $ordersQuery) => $ordersQuery->where('status', Order::STATUS_PAID),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('paid_orders_count')
                    ->label('Presentes dados')
                    // Same premium gate as the global guests list.
                    ->formatStateUsing(fn (?int $state) => $this->canViewGiftCount()
                        ? (string) $state
                        : '🔒')
                    ->sortable(),
                TextColumn::make('cpf')
                    ->label('CPF')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('rsvp_status')
                    ->label('Presença')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        Guest::RSVP_CONFIRMED => 'Confirmada',
                        Guest::RSVP_DECLINED => 'Não vai',
                        default => 'Sem resposta',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        Guest::RSVP_CONFIRMED => 'success',
                        Guest::RSVP_DECLINED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('companionOf.name')
                    ->label('Acompanhante de')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('rsvp_guests_count')
                    ->label('Pessoas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Chegou em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()->label('Lixeira'),
                SelectFilter::make('rsvp_status')
                    ->label('Presença')
                    ->options([
                        Guest::RSVP_CONFIRMED => 'Confirmada',
                        Guest::RSVP_DECLINED => 'Não vai',
                    ]),
            ])
            ->headerActions([])
            ->recordActions([
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Mover para a lixeira')
                        ->modalDescription('O convidado fica 30 dias na lixeira e pode ser restaurado nesse período. Os acompanhantes dele vão junto.')
                        // Blocked on click rather than hidden, so the list
                        // doesn't reveal who gave gifts to a host without
                        // the "gift givers" premium feature.
                        ->before(function (DeleteAction $action, Guest $record): void {
                            if ($record->hasPaidOrders()) {
                                Notification::make()
                                    ->danger()
                                    ->title('Este convidado não pode ser excluído')
                                    ->body('Ele tem um presente pago registrado, que faz parte do histórico financeiro do evento. Se ele pediu para remover os dados pessoais, use "Anonimizar".')
                                    ->persistent()
                                    ->send();

                                $action->cancel();
                            }
                        }),
                    Action::make('anonymize')
                        ->label('Anonimizar')
                        ->icon(Heroicon::OutlinedEyeSlash)
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Anonimizar convidado')
                        ->modalDescription('Apaga nome, telefone, e-mail e CPF deste convidado, mantendo a confirmação de presença e os presentes dados no histórico. Use quando o convidado pedir a remoção dos dados pessoais (LGPD). Não pode ser desfeito.')
                        ->visible(fn (Guest $record): bool => ! $record->trashed() && $record->name !== Guest::ANONYMIZED_NAME)
                        ->action(function (Guest $record, GuestAnonymizeService $service): void {
                            $service->execute($record);

                            Notification::make()->success()->title('Dados do convidado anonimizados')->send();
                        }),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SafeDeleteBulkAction::make(
                        fn (Guest $guest): bool => $guest->hasPaidOrders(),
                        'Convidados com presentes pagos fazem parte do histórico financeiro e não podem ser excluídos. Use "Anonimizar" se eles pediram a remoção dos dados.',
                    ),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
