<?php

namespace App\Filament\Resources\Events\Events\Pages;

use App\Enums\Premium\Feature;
use App\Filament\Resources\Events\Events\EventResource;
use App\Filament\Resources\Events\Events\Pages\Concerns\HasEventHeaderActions;
use App\Models\Events\Event;
use App\Models\Guests\Guest;
use App\Models\Orders\Order;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            ->modifyQueryUsing(fn (Builder $query) => $query->with('companionOf')->withCount([
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
                SelectFilter::make('rsvp_status')
                    ->label('Presença')
                    ->options([
                        Guest::RSVP_CONFIRMED => 'Confirmada',
                        Guest::RSVP_DECLINED => 'Não vai',
                    ]),
            ])
            ->headerActions([])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
