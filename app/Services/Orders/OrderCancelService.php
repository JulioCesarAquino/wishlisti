<?php

namespace App\Services\Orders;

use App\Models\Catalog\EventProduct;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderCancelService
{
    /**
     * Cancels an in-person reservation — the guest giving up, or the host
     * freeing the item — and gives the stock back so someone else can pick
     * it.
     */
    public function execute(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order = Order::whereKey($order->id)->with('items')->lockForUpdate()->firstOrFail();

            if (! $order->isReservation()) {
                throw ValidationException::withMessages([
                    'order' => 'Só é possível cancelar um presente que ainda está reservado.',
                ]);
            }

            foreach ($order->items as $item) {
                EventProduct::withTrashed()
                    ->whereKey($item->event_product_id)
                    ->where('quantity_purchased', '>=', $item->quantity)
                    ->decrement('quantity_purchased', $item->quantity);
            }

            $order->update(['status' => Order::STATUS_CANCELLED]);
        });
    }
}
