<?php

namespace App\Filament\Resources\Guests\Guests\Tables;

use App\Filament\Resources\Events\Events\EventResource;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['event', 'companionOf'])
                ->when(! auth()->user()?->isAdmin(), fn (Builder $query) => $query->scopes('visibleToHost'))
                // Anonymous gifts don't count: the number would give them away.
                ->withCount([
                    'orders as paid_orders_count' => fn (Builder $ordersQuery) => $ordersQuery
                        ->where('status', Order::STATUS_PAID)
                        ->where('is_anonymous', false),
                ]))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Evento')
                    ->searchable()
                    ->toggleable()
                    ->url(fn (Guest $record) => EventResource::getUrl('edit', ['record' => $record->event])),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('paid_orders_count')
                    ->label('Presentes dados')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Chegou em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('rsvp_status')
                    ->label('Presença')
                    ->options([
                        Guest::RSVP_CONFIRMED => 'Confirmada',
                        Guest::RSVP_DECLINED => 'Não vai',
                    ]),
            ]);
    }
}
