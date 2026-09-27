<?php

namespace App\Filament\Resources\Orders\Orders\Tables;

use App\Filament\Resources\Events\Events\EventResource;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
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
                        $record->hidesGiverFrom(auth()->user()) => 'Anônimo',
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
                    ->state(fn (Order $record) => $record->items
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
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'Pendente',
                        Order::STATUS_PAID => 'Pago',
                        Order::STATUS_FAILED => 'Falhou',
                        Order::STATUS_CANCELLED => 'Cancelado',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'warning',
                        Order::STATUS_PAID => 'success',
                        Order::STATUS_FAILED, Order::STATUS_CANCELLED => 'danger',
                        default => 'gray',
                    }),
                // Hidden on anonymous gifts: the time could be matched against
                // the guest's RSVP to find out who gave it.
                TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->formatStateUsing(fn (Order $record, $state) => $record->hidesGiverFrom(auth()->user()) ? '—' : $state?->format('d/m/Y H:i'))
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->formatStateUsing(fn (Order $record, $state) => $record->hidesGiverFrom(auth()->user()) ? '—' : $state?->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Order::STATUS_PENDING => 'Pendente',
                        Order::STATUS_PAID => 'Pago',
                        Order::STATUS_FAILED => 'Falhou',
                        Order::STATUS_CANCELLED => 'Cancelado',
                    ]),
            ]);
    }
}
