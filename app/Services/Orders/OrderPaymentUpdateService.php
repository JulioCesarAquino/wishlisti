<?php

namespace App\Services\Orders;

use App\Models\Catalog\EventProduct;
use App\Models\Events\Event;
use App\Models\Orders\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Resources\Payment;

class OrderPaymentUpdateService
{
    public function __construct(
        protected PaymentClient $paymentClient,
    ) {}

    /**
     * Re-fetches the payment from Mercado Pago (never trusting the webhook
     * body directly) and applies its status to the matching order.
     */
    public function execute(Event $event, string $paymentId): void
    {
        if (blank($event->paymentSettings->mp_access_token)) {
            return;
        }

        $requestOptions = new RequestOptions;
        $requestOptions->setAccessToken($event->paymentSettings->mp_access_token);

        try {
            $payment = $this->paymentClient->get((int) $paymentId, $requestOptions);
        } catch (MPApiException $exception) {
            Log::warning('Mercado Pago: falha ao buscar pagamento do webhook', [
                'event_id' => $event->id,
                'payment_id' => $paymentId,
                'status_code' => $exception->getApiResponse()->getStatusCode(),
            ]);

            return;
        }

        $this->applyPayment($event, $payment);
    }

    /**
     * Applies an already-fetched payment's status to its matching order.
     * Stock is only adjusted on the transition into/out of "paid", so
     * calling this repeatedly for the same payment stays idempotent.
     */
    public function applyPayment(Event $event, Payment $payment): void
    {
        if (blank($payment->external_reference)) {
            return;
        }

        DB::transaction(function () use ($event, $payment) {
            $order = Order::where('event_id', $event->id)
                ->where('id', $payment->external_reference)
                ->with('items')
                ->lockForUpdate()
                ->first();

            if (! $order) {
                Log::warning('Mercado Pago: pedido não encontrado para o pagamento', [
                    'event_id' => $event->id,
                    'external_reference' => $payment->external_reference,
                ]);

                return;
            }

            $newStatus = match ($payment->status) {
                'approved' => Order::STATUS_PAID,
                'rejected' => Order::STATUS_FAILED,
                'cancelled', 'refunded', 'charged_back' => Order::STATUS_CANCELLED,
                default => Order::STATUS_PENDING,
            };

            $wasPaid = $order->status === Order::STATUS_PAID;
            $isNowPaid = $newStatus === Order::STATUS_PAID;

            $order->update([
                'status' => $newStatus,
                'payment_id' => (string) $payment->id,
                'payment_method' => $payment->payment_method_id,
                'payment_type' => $payment->payment_type_id ?? $order->payment_type,
                'paid_at' => $isNowPaid ? ($order->paid_at ?? now()) : $order->paid_at,
            ]);

            if ($isNowPaid && ! $wasPaid) {
                foreach ($order->items as $item) {
                    EventProduct::whereKey($item->event_product_id)
                        ->increment('quantity_purchased', $item->quantity);
                }
            } elseif ($wasPaid && ! $isNowPaid) {
                foreach ($order->items as $item) {
                    EventProduct::whereKey($item->event_product_id)
                        ->decrement('quantity_purchased', $item->quantity);
                }
            }
        });
    }
}
