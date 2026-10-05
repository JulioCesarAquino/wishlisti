<?php

namespace App\Filament\Resources\Orders\Orders\Pages;

use App\Filament\Resources\Orders\Orders\OrderResource;
use App\Filament\Widgets\OrdersOverviewWidget;
use App\Filament\Widgets\PaymentMethodsChartWidget;
use App\Models\Orders\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Pedidos';

    /**
     * The gifts first; the ones still waiting for payment and the attempts
     * that never went through, apart — they'd bury the gifts otherwise.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $statuses = fn (array $statuses) => fn (Builder $query) => $query->whereIn('status', $statuses);

        return [
            'presentes' => Tab::make('Presentes')
                ->icon(Heroicon::OutlinedGift)
                ->modifyQueryUsing($statuses([Order::STATUS_PAID, Order::STATUS_RESERVED, Order::STATUS_RECEIVED])),
            'aguardando' => Tab::make('Aguardando pagamento')
                ->icon(Heroicon::OutlinedClock)
                ->badge(fn (): ?int => OrderResource::getEloquentQuery()->where('status', Order::STATUS_PENDING)->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing($statuses([Order::STATUS_PENDING])),
            'nao-concluidos' => Tab::make('Não concluídos')
                ->icon(Heroicon::OutlinedXCircle)
                ->modifyQueryUsing($statuses([Order::STATUS_EXPIRED, Order::STATUS_CANCELLED, Order::STATUS_FAILED])),
            'todos' => Tab::make('Todos'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OrdersOverviewWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            PaymentMethodsChartWidget::class,
        ];
    }
}
