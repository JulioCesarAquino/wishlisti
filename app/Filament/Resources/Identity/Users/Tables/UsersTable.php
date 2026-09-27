<?php

namespace App\Filament\Resources\Identity\Users\Tables;

use App\Filament\Support\SafeDeleteBulkAction;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('events_count')
                    ->label('Eventos')
                    ->counts('events')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    SafeDeleteBulkAction::make(
                        fn (User $user): bool => $user->hasOrders(),
                        'Anfitriões cujos eventos têm pedidos fazem parte do histórico financeiro e não podem ser excluídos.',
                        label: 'Excluir',
                        description: 'Exclui os anfitriões selecionados e todos os eventos deles. Não pode ser desfeito.',
                        doneLabel: 'excluído(s)',
                    ),
                ]),
            ]);
    }
}
