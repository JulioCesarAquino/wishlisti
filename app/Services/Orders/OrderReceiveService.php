<?php

namespace App\Services\Orders;

use App\Models\Orders\Order;
use Illuminate\Validation\ValidationException;

class OrderReceiveService
{
    /**
     * The host marks an in-person gift as handed over. The item stays
     * taken; the guest can no longer give up on it.
     */
    public function execute(Order $order): void
    {
        if (! $order->isReservation()) {
            throw ValidationException::withMessages([
                'order' => 'Só é possível marcar como recebido um presente reservado.',
            ]);
        }

        $order->update(['status' => Order::STATUS_RECEIVED]);
    }
}
