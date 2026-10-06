<?php

namespace App\Filament\Resources\Guests\Guests;

use App\Filament\Resources\Guests\Guests\Pages\ListGuests;
use App\Filament\Resources\Guests\Guests\Tables\GuestsTable;
use App\Models\Guests\Guest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuestResource extends Resource
{
    protected static ?string $model = Guest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'convidado';

    protected static ?string $pluralModelLabel = 'Convidados';

    protected static ?string $navigationLabel = 'Convidados';

    protected static ?string $slug = 'guests';

    public static function getEloquentQuery(): Builder
    {
        // Guests of an event in the trash go (and come back) with it.
        $query = parent::getEloquentQuery()->whereHas('event');

        if (! auth()->user()?->isAdmin()) {
            $query->whereHas('event', fn (Builder $eventQuery) => $eventQuery->scopes(['managedBy' => [auth()->id()]]));
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return GuestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuests::route('/'),
        ];
    }
}
