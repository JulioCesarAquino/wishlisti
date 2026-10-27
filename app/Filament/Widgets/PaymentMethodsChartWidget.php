<?php

namespace App\Filament\Widgets;

use App\Filament\Support\EventPicker;
use App\Models\Orders\Order;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PaymentMethodsChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Presentes pagos por forma de pagamento';

    /**
     * Maps Mercado Pago's `payment_type_id` values to the pt-BR buckets
     * hosts actually care about (Pix, Cartão, Boleto), instead of the raw
     * API taxonomy (credit_card, debit_card, ticket, bank_transfer...).
     *
     * @var array<string, string>
     */
    private const TYPE_LABELS = [
        'bank_transfer' => 'Pix',
        'credit_card' => 'Cartão',
        'debit_card' => 'Cartão',
        'ticket' => 'Boleto',
    ];

    protected function getType(): string
    {
        return 'pie';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $isAdmin = (bool) auth()->user()?->isAdmin();
        $eventId = EventPicker::chosen($this->pageFilters['event_id'] ?? null);

        $counts = Order::query()
            ->where('status', Order::STATUS_PAID)
            ->when($eventId, fn ($query) => $query->where('event_id', $eventId))
            ->when(! $isAdmin, fn ($query) => $query->whereHas(
                'event',
                fn ($eventQuery) => $eventQuery->managedBy(auth()->id()),
            ))
            ->get('payment_type')
            ->groupBy(fn (Order $order) => self::TYPE_LABELS[$order->payment_type] ?? 'Outro')
            ->map->count();

        return [
            'datasets' => [[
                'data' => $counts->values()->all(),
                'backgroundColor' => ['#22c55e', '#3b82f6', '#f59e0b', '#a1a1aa'],
            ]],
            'labels' => $counts->keys()->all(),
        ];
    }
}
