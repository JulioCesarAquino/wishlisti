<?php

namespace App\Filament\Resources\Events\Events\Tables;

use App\Filament\Support\SafeDeleteBulkAction;
use App\Models\Events\Event;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->label(''),
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Anfitrião')
                    ->searchable()
                    ->visible(fn () => auth()->user()?->isAdmin()),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (?string $state): string => Event::TYPE_LABELS[$state] ?? (string) $state)
                    ->badge(),
                TextColumn::make('event_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Publicado')
                    ->boolean(),
                TextColumn::make('archived_at')
                    ->label('Arquivado em')
                    ->date('d/m/Y')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('archived_at')
                    ->label('Arquivados')
                    ->nullable()
                    ->placeholder('Todos')
                    ->trueLabel('Só arquivados')
                    ->falseLabel('Só ativos')
                    ->default(false),
                TrashedFilter::make()->label('Lixeira'),
            ])
            ->recordActions([
                EditAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SafeDeleteBulkAction::make(
                        fn (Event $event): bool => $event->hasPaidOrders(),
                        'Eventos com presentes pagos fazem parte do histórico financeiro e não vão para a lixeira. Abra o evento e use "Arquivar".',
                    ),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
