<?php

namespace App\Filament\Resources\Orders\Orders\Tables;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Support\PremiumLock;
use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    /**
     * Guest identity (name/WhatsApp) is gated behind the event's "gift
     * givers" premium feature — the free tier only ever shows aggregate
     * totals. Admins always see everything regardless of the feature.
     */
    private static function canViewGuestIdentity(Order $order): bool
    {
        return (bool) auth()->user()?->isAdmin() || $order->event->hasFeature(Feature::GiftGivers);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['event.featureGrants', 'guest']))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Evento')
                    ->searchable()
                    ->toggleable()
                    ->url(fn (Order $record) => EventResource::getUrl('edit', ['record' => $record->event])),
                TextColumn::make('guest.name')
                    ->label('Convidado')
                    ->formatStateUsing(fn (Order $record, ?string $state) => self::canViewGuestIdentity($record)
                        ? $state
                        : '🔒 Identidade oculta')
                    ->tooltip(fn (Order $record): ?string => self::canViewGuestIdentity($record) ? null : PremiumLock::tooltip(Feature::GiftGivers))
                    ->url(fn (Order $record): ?string => self::canViewGuestIdentity($record) ? null : PremiumLock::purchaseUrl($record->event)),
                TextColumn::make('guest.whatsapp')
                    ->label('WhatsApp')
                    ->formatStateUsing(fn (Order $record, ?string $state) => self::canViewGuestIdentity($record)
                        ? $state
                        : '—'),
                TextColumn::make('items')
                    ->label('Itens')
                    ->state(fn (Order $record) => $record->items
                        ->map(fn (OrderItem $item) => "{$item->quantity}x {$item->eventProduct?->name}")
                        ->join(', '))
                    ->wrap(),
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
                TextColumn::make('paid_at')
                    ->label('Pago em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
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
