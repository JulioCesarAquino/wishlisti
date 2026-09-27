<?php

namespace App\Services\Orders;

use App\Models\Orders\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

class OrderPaymentCreateService
{
    public function __construct(
        protected PaymentClient $paymentClient,
        protected OrderPaymentUpdateService $updateService,
    ) {}

    /**
     * Creates the real charge on Mercado Pago from the Payment Brick's
     * `formData`, using the order's own total (never the client-supplied
     * amount) so the charged value can't be tampered with client-side.
     *
     * @param  array<string, mixed>  $formData
     * @return array{id: string, status: string, status_detail: string}
     */
    public function execute(Order $order, array $formData): array
    {
        $event = $order->event;

        // Checked again here (not only when the order was placed): the
        // event may have lost the feature in between.
        if (! $event->acceptsOnlineGifts()) {
            throw ValidationException::withMessages([
                'formData' => 'Este evento não está recebendo presentes online no momento.',
            ]);
        }

        $requestOptions = new RequestOptions;
        $requestOptions->setAccessToken($event->paymentSettings->mp_access_token);

        $request = [
            ...$formData,
            'transaction_amount' => (float) $order->total_amount,
            'description' => "Presente - {$event->title}",
            'external_reference' => (string) $order->id,
        ];

        // Mercado Pago rejects the whole payment if notification_url isn't a
        // publicly reachable address (e.g. localhost in local dev), so we
        // only send it when it actually points somewhere they could reach.
        $notificationUrl = route('orders.mercadopago-webhook', ['event' => $event->slug]);

        if (! in_array(parse_url($notificationUrl, PHP_URL_HOST), ['localhost', '127.0.0.1'], true)) {
            $request['notification_url'] = $notificationUrl;
        }

        try {
            $payment = $this->paymentClient->create($request, $requestOptions);
        } catch (MPApiException $exception) {
            Log::error('Mercado Pago: falha ao criar pagamento via Brick', [
                'order_id' => $order->id,
                'status_code' => $exception->getApiResponse()->getStatusCode(),
                'content' => $exception->getApiResponse()->getContent(),
                'request' => [...$request, 'token' => array_key_exists('token', $request) ? '[hidden]' : null],
            ]);

            throw ValidationException::withMessages([
                'formData' => 'Não foi possível processar o pagamento. Tente novamente.',
            ]);
        }

        $this->updateService->applyPayment($event, $payment);

        return [
            'id' => (string) $payment->id,
            'status' => (string) $payment->status,
            'status_detail' => (string) $payment->status_detail,
        ];
    }
}
