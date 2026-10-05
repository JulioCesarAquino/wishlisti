<?php

namespace App\Services\Orders;

use App\Models\Orders\Order;

/**
 * An online gift is ordered as soon as the guest opens the checkout, so
 * every guest who gives up leaves a pending order behind. Those are closed
 * here, so the hosts' list shows the gifts — not the attempts.
 *
 * No stock is involved: online gifts only take it once paid.
 */
class OrderExpireAbandonedService
{
    public function __construct(
        protected OrderPaymentUpdateService $paymentUpdateService,
    ) {}

    /**
     * @return int how many orders were closed
     */
    public function execute(): int
    {
        $cutoff = now()->subMinutes(Order::ABANDONED_AFTER_MINUTES);
        $closed = 0;

        // No payment was even started: the guest gave up on the checkout.
        $closed += Order::where('fulfillment', Order::FULFILLMENT_ONLINE)
            ->where('status', Order::STATUS_PENDING)
            ->whereNull('payment_id')
            ->where('created_at', '<=', $cutoff)
            ->update(['status' => Order::STATUS_EXPIRED]);

        // A payment was started (a Pix, a boleto…): Mercado Pago says how it
        // ended, in case its notification never came. A Pix left unpaid ends
        // cancelled; one still open (a boleto has days) is asked again later.
        Order::where('fulfillment', Order::FULFILLMENT_ONLINE)
            ->where('status', Order::STATUS_PENDING)
            ->whereNotNull('payment_id')
            ->where('updated_at', '<=', $cutoff)
            ->with('event.paymentSettings')
            ->each(function (Order $order) use (&$closed): void {
                $this->paymentUpdateService->execute($order->event, (string) $order->payment_id);

                if ($order->refresh()->status === Order::STATUS_PENDING) {
                    $order->touch();

                    return;
                }

                $closed++;
            });

        return $closed;
    }
}
