<?php

namespace App\Filament\Resources\Orders\Orders;

use App\Filament\Resources\Orders\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Orders\Tables\OrdersTable;
use App\Models\Orders\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?string $navigationLabel = 'Pedidos';

    protected static ?string $slug = 'orders';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

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
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
