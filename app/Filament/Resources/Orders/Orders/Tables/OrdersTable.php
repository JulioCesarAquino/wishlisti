<?php

namespace App\Filament\Resources\Orders\Orders\Tables;

use App\Filament\Resources\Events\Events\EventResource;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use App\Services\Orders\OrderCancelService;
use App\Services\Orders\OrderReceiveService;
use App\Services\Payments\PaymentPayerLookupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        Order::STATUS_PENDING => 'Pendente',
        Order::STATUS_PAID => 'Pago',
        Order::STATUS_RESERVED => 'Reservado',
        Order::STATUS_RECEIVED => 'Recebido',
        Order::STATUS_FAILED => 'Falhou',
        Order::STATUS_CANCELLED => 'Cancelado',
        Order::STATUS_EXPIRED => 'Não concluído',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['event', 'guest']))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Evento')
                    ->searchable()
                    ->toggleable()
                    ->url(fn (Order $record) => EventResource::getUrl('edit', ['record' => $record->event])),
                TextColumn::make('guest.name')
                    ->label('Convidado')
                    ->formatStateUsing(fn (Order $record, ?string $state) => match (true) {
                        $record->hidesGiverFrom(auth()->user()) && $record->isInPerson() => 'Presente anônimo — entrega pelo convidado',
                        $record->hidesGiverFrom(auth()->user()), $state === Guest::ANONYMOUS_GIVER_NAME => Guest::ANONYMOUS_GIVER_NAME,
                        $record->is_anonymous => "{$state} (anônimo)",
                        default => $state,
                    })
                    ->tooltip(fn (Order $record): ?string => $record->hidesGiverFrom(auth()->user())
                        ? 'O convidado escolheu presentear anonimamente.'
                        : null),
                TextColumn::make('guest.whatsapp')
                    ->label('WhatsApp')
                    ->formatStateUsing(fn (Order $record, ?string $state) => $record->hidesGiverFrom(auth()->user()) ? '—' : $state),
                TextColumn::make('items')
                    ->label('Itens')
                    ->state(fn (Order $record) => $record->is_free_amount ? 'Contribuição de valor livre' : $record->items
                        ->map(fn (OrderItem $item) => "{$item->quantity}x {$item->eventProduct?->name}")
                        ->join(', '))
                    ->wrap(),
                TextColumn::make('message')
                    ->label('Mensagem')
                    ->placeholder('—')
                    ->limit(60)
                    ->tooltip(fn (Order $record): ?string => $record->message)
                    ->toggleable(),
                TextColumn::make('total_amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('fulfillment')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Order::FULFILLMENT_IN_PERSON ? 'Entrega pessoal' : 'Online')
                    ->color(fn (string $state): string => $state === Order::FULFILLMENT_IN_PERSON ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING, Order::STATUS_RESERVED => 'warning',
                        Order::STATUS_PAID, Order::STATUS_RECEIVED => 'success',
                        Order::STATUS_FAILED, Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                // Hidden on anonymous gifts: the time could be matched against
                // the guest's RSVP to find out who gave it.
                TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->formatStateUsing(fn (Order $record, $state) => $record->hidesGiverFrom(auth()->user()) ? '—' : $state?->timezone(Event::TIMEZONE)->format('d/m/Y H:i'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->formatStateUsing(fn (Order $record, $state) => $record->hidesGiverFrom(auth()->user()) ? '—' : $state?->timezone(Event::TIMEZONE)->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUS_LABELS),
                SelectFilter::make('fulfillment')
                    ->label('Tipo')
                    ->options([
                        Order::FULFILLMENT_ONLINE => 'Online',
                        Order::FULFILLMENT_IN_PERSON => 'Entrega pessoal',
                    ]),
            ])
            ->recordActions([
                // Admin only: asked of Mercado Pago on opening, never stored,
                // and logged in the audit trail (see PaymentPayerLookupService).
                Action::make('viewPayer')
                    ->label('Ver pagador')
                    ->icon(Heroicon::OutlinedIdentification)
                    ->color('gray')
                    ->visible(fn (Order $record): bool => (bool) auth()->user()?->isAdmin() && filled($record->payment_id))
                    ->modalHeading('Quem pagou')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar')
                    ->modalContent(fn (Order $record) => view('filament.payments.payer', [
                        'payer' => app(PaymentPayerLookupService::class)->execute($record, auth()->user()),
                    ])),
                Action::make('markReceived')
                    ->label('Marcar como recebido')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Confirma que este presente já foi entregue a você?')
                    ->visible(fn (Order $record): bool => $record->isReservation())
                    ->action(function (Order $record, OrderReceiveService $service): void {
                        $service->execute($record);

                        Notification::make()->success()->title('Presente marcado como recebido')->send();
                    }),
                Action::make('cancelReservation')
                    ->label('Cancelar reserva')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('O presente volta para a lista e outro convidado poderá escolhê-lo. Use quando o convidado desistir ou não for entregar.')
                    ->visible(fn (Order $record): bool => $record->isReservation())
                    ->action(function (Order $record, OrderCancelService $service): void {
                        $service->execute($record);

                        Notification::make()->success()->title('Reserva cancelada')->send();
                    }),
            ]);
    }
}
