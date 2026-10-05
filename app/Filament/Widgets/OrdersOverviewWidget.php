<?php

namespace App\Filament\Widgets;

use App\Models\Orders\Order;
use App\Models\Orders\OrderItem;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class OrdersOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $isAdmin = (bool) auth()->user()?->isAdmin();

        $ordersQuery = Order::query()
            ->when(! $isAdmin, fn ($query) => $query->whereHas(
                'event',
                fn ($eventQuery) => $eventQuery->managedBy(auth()->id()),
            ));

        $raisedTotal = (float) (clone $ordersQuery)->where('status', Order::STATUS_PAID)->sum('total_amount');
        $pendingTotal = (float) (clone $ordersQuery)->where('status', Order::STATUS_PENDING)->sum('total_amount');

        $reservedCount = (clone $ordersQuery)->where('status', Order::STATUS_RESERVED)->count();

        $itemsSold = OrderItem::query()
            ->whereHas('order', fn ($query) => $query
                ->where('status', Order::STATUS_PAID)
                ->when(! $isAdmin, fn ($orderQuery) => $orderQuery->whereHas(
                    'event',
                    fn ($eventQuery) => $eventQuery->managedBy(auth()->id()),
                )))
            ->sum('quantity');

        return [
            Stat::make('Total arrecadado', Number::currency($raisedTotal, in: 'BRL', locale: 'pt_BR'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Pendente de pagamento', Number::currency($pendingTotal, in: 'BRL', locale: 'pt_BR'))
                ->icon(Heroicon::OutlinedClock)
                ->color('warning'),
            Stat::make('Itens vendidos', (string) $itemsSold)
                ->icon(Heroicon::OutlinedShoppingCart)
                ->color('info'),
            Stat::make('Reservados para entrega', (string) $reservedCount)
                ->description('Presentes que os convidados vão entregar pessoalmente')
                ->icon(Heroicon::OutlinedGift)
                ->color('warning'),
        ];
    }
}
