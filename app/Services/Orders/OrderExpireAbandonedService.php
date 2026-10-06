<?php

namespace App\Services\Orders;

use App\Models\Orders\Order;
use App\Support\MercadoPagoPayments;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

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
        protected PaymentClient $paymentClient,
        protected MercadoPagoPayments $payments,
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
        // ended, in case its notification never came.
        Order::where('fulfillment', Order::FULFILLMENT_ONLINE)
            ->where('status', Order::STATUS_PENDING)
            ->whereNotNull('payment_id')
            ->where('updated_at', '<=', $cutoff)
            ->with('event.paymentSettings')
            ->each(function (Order $order) use (&$closed): void {
                if ($this->closeStartedPayment($order)) {
                    $closed++;

                    return;
                }

                // Still open (a boleto has days): asked again later.
                $order->touch();
            });

        return $closed;
    }

    /**
     * - Unknown to the event's account (made with credentials the host has
     *   since changed, or the credentials are gone): nothing to pay there.
     * - A Pix still open hours later won't be paid: it's cancelled, so it
     *   can't be — unless Mercado Pago refuses, because it just was.
     * - A boleto or a card under review keeps waiting: they take days.
     */
    private function closeStartedPayment(Order $order): bool
    {
        $event = $order->event;

        if (blank($event->paymentSettings->mp_access_token)) {
            $order->update(['status' => Order::STATUS_EXPIRED]);

            return true;
        }

        $options = $this->paymentUpdateService->requestOptions($event);

        try {
            $payment = $this->paymentClient->get((int) $order->payment_id, $options);
        } catch (MPApiException $exception) {
            if ($exception->getApiResponse()->getStatusCode() === 404) {
                $order->update(['status' => Order::STATUS_EXPIRED]);

                return true;
            }

            return false;
        }

        $this->paymentUpdateService->applyPayment($event, $payment);

        if ($order->refresh()->status !== Order::STATUS_PENDING) {
            return true;
        }

        if ($payment->payment_method_id !== 'pix') {
            return false;
        }

        if ($this->payments->cancel((string) $order->payment_id, $options, ['order_id' => $order->id])) {
            $order->update(['status' => Order::STATUS_CANCELLED]);

            return true;
        }

        $this->paymentUpdateService->execute($event, (string) $order->payment_id);

        return $order->refresh()->status !== Order::STATUS_PENDING;
    }
}
